<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Area;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Permiso;
use App\Models\Requerimiento;
use App\Models\Responsable;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Revisión de autorización: cada ruta exige sesión y permiso, un Usuario no
 * alcanza datos de otro (IDOR) y el Operador no aprueba ni con peticiones directas.
 */
class AutorizacionTest extends TestCase
{
    use RefreshDatabase;

    /** Rutas públicas por diseño: acceso y recuperación de contraseña. */
    private const PUBLICAS = [
        'home',             // solo redirige al login o al panel
        'login',
        'login.post',
        'logout',           // cerrar sesión sin sesión no hace nada
        'password.request',
        'password.email',
        'password.reset',
        'password.update',
    ];

    /**
     * Rutas de la propia cuenta: cualquier persona con sesión las necesita
     * (cambiar su contraseña, renovar su sesión, leer sus notificaciones).
     * Exigen sesión, pero no un permiso de módulo.
     */
    private const CUENTA_PROPIA = [
        'password.cambiar',
        'password.cambiar.update',
        'sesion.renovar',
        'notificaciones.index',
        'notificaciones.leer-todas',
        'notificaciones.leer',
    ];

    /**
     * @return list<string> middleware de la ruta
     */
    private function middlewareDe(Ruta $ruta): array
    {
        return array_values(array_map('strval', $ruta->gatherMiddleware()));
    }

    /**
     * Rutas de la aplicación. Se excluyen las de herramientas de desarrollo que no
     * existen en producción: el chequeo de salud "/up" (no devuelve datos) y las de
     * Laravel Boost (paquete require-dev).
     *
     * @return list<Ruta>
     */
    private function rutasDeLaAplicacion(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (Ruta $ruta) => $ruta->uri() !== 'up' && ! str_starts_with((string) $ruta->getName(), 'boost.'),
        ));
    }

    public function test_todas_las_rutas_exigen_sesion_y_permiso_salvo_acceso_y_recuperacion(): void
    {
        $sinProteccion = [];

        foreach ($this->rutasDeLaAplicacion() as $ruta) {
            $nombre = $ruta->getName() ?? $ruta->methods()[0].' '.$ruta->uri();
            $middleware = $this->middlewareDe($ruta);
            $tieneSesion = in_array('auth', $middleware, true);
            $tienePermiso = collect($middleware)->contains(fn ($m) => str_starts_with($m, 'permiso:'));

            if (in_array($nombre, self::PUBLICAS, true)) {
                continue;
            }

            if (in_array($nombre, self::CUENTA_PROPIA, true)) {
                $tieneSesion || $sinProteccion[] = "{$nombre}: falta auth";

                continue;
            }

            if (! $tieneSesion || ! $tienePermiso) {
                $sinProteccion[] = "{$nombre} ({$ruta->uri()}): ".(! $tieneSesion ? 'falta auth' : 'falta permiso:');
            }
        }

        $this->assertSame([], $sinProteccion, "Rutas sin la protección esperada:\n".implode("\n", $sinProteccion));
    }

    public function test_las_listas_de_excepciones_no_tienen_rutas_inexistentes(): void
    {
        foreach ([...self::PUBLICAS, ...self::CUENTA_PROPIA] as $nombre) {
            $this->assertTrue(Route::has($nombre), "La excepción \"{$nombre}\" ya no existe: quítela de la lista.");
        }
    }

    public function test_los_permisos_de_las_rutas_existen_en_el_catalogo(): void
    {
        $existentes = Permiso::with('modulo')->get()->map(fn ($p) => "{$p->modulo->slug}.{$p->accion}")->all();

        foreach ($this->rutasDeLaAplicacion() as $ruta) {
            foreach ($this->middlewareDe($ruta) as $middleware) {
                if (str_starts_with($middleware, 'permiso:')) {
                    foreach (explode(',', substr($middleware, 8)) as $permiso) {
                        $this->assertContains($permiso, $existentes, "La ruta {$ruta->getName()} pide \"{$permiso}\", que no existe: nadie salvo el Administrador podría usarla.");
                    }
                }
            }
        }
    }

    public function test_una_cuenta_sin_permisos_recibe_403_en_todas_las_pantallas(): void
    {
        $rolVacio = Role::create(['nombre' => 'Sin permisos', 'estado' => 'activo']);
        $usuario = Usuario::factory()->create(['rol_id' => $rolVacio->id]);
        $revisadas = 0;

        foreach ($this->rutasDeLaAplicacion() as $ruta) {
            $nombre = (string) $ruta->getName();
            if (! in_array('GET', $ruta->methods(), true) || $ruta->parameterNames() !== []
                || in_array($nombre, [...self::PUBLICAS, ...self::CUENTA_PROPIA], true)) {
                continue;
            }

            $this->actingAs($usuario)->get(route($nombre))->assertForbidden();
            $revisadas++;
        }

        $this->assertGreaterThan(20, $revisadas);
    }

    // -------------------------------------------------------------------------
    // IDOR: el Usuario A no alcanza los datos del Usuario B
    // -------------------------------------------------------------------------

    /**
     * @return array{0: Usuario, 1: Usuario, 2: Bien, 3: Responsable}
     */
    private function dosResponsables(): array
    {
        $usuarioA = $this->usuarioCon(RolSistema::Usuario);
        $usuarioB = $this->usuarioCon(RolSistema::Usuario);
        Responsable::factory()->create(['usuario_id' => $usuarioA->id]);
        $responsableB = Responsable::factory()->create(['usuario_id' => $usuarioB->id]);
        $bienDeB = Bien::factory()->create(['responsable_id' => $responsableB->id, 'nombre' => 'Laptop Lenovo de B']);

        return [$usuarioA, $usuarioB, $bienDeB, $responsableB];
    }

    public function test_un_usuario_no_ve_ni_descarga_los_bienes_de_otro(): void
    {
        [$usuarioA, , $bienDeB] = $this->dosResponsables();

        $this->actingAs($usuarioA)->get(route('bienes.show', $bienDeB))->assertForbidden();
        $this->actingAs($usuarioA)->get(route('bienes.codigo-barras', $bienDeB))->assertForbidden();
        $this->actingAs($usuarioA)->get(route('bienes.etiqueta', [$bienDeB, 'png']))->assertForbidden();
        $this->actingAs($usuarioA)->get(route('bajas.create', ['bien_id' => $bienDeB->id]))->assertOk()->assertDontSee($bienDeB->codigo_barras);

        $this->actingAs($usuarioA)->getJson(route('buscar', ['q' => 'Lenovo de B']))->assertOk()->assertJsonCount(0, 'resultados');
        $this->actingAs($usuarioA)->getJson(route('buscar.bienes-solicitables', ['para' => 'bajas', 'q' => 'Lenovo de B']))->assertOk()->assertDontSee($bienDeB->codigo_barras);
    }

    public function test_un_usuario_no_ve_ni_borra_las_solicitudes_de_otro(): void
    {
        Storage::fake('local');
        [$usuarioA, $usuarioB, $bienDeB] = $this->dosResponsables();

        $this->actingAs($usuarioB)->post(route('requerimientos.store'), [
            'tipo_requerimiento' => 'Mobiliario', 'descripcion' => 'Mesa', 'justificacion' => 'Aula',
            'archivo_adjunto' => UploadedFile::fake()->createWithContent('cotizacion.pdf', "%PDF-1.4\n%%EOF\n"),
        ])->assertSessionHasNoErrors();
        $this->actingAs($usuarioB)->post(route('bajas.store'), [
            'bien_id' => $bienDeB->id, 'motivo' => 'deterioro', 'fecha_baja' => now()->toDateString(), 'descripcion' => 'Rota',
            'fotografia' => UploadedFile::fake()->image('evidencia.jpg'),
        ])->assertSessionHasNoErrors();
        $requerimiento = Requerimiento::sole();
        $baja = Baja::sole();

        $this->actingAs($usuarioA)->get(route('requerimientos.show', $requerimiento))->assertForbidden();
        $this->actingAs($usuarioA)->get(route('archivos.requerimientos.adjunto', $requerimiento))->assertForbidden();
        $this->actingAs($usuarioA)->delete(route('requerimientos.destroy', $requerimiento))->assertForbidden();
        $this->actingAs($usuarioA)->get(route('archivos.bajas.foto', $baja))->assertForbidden();
        $this->actingAs($usuarioA)->get(route('solicitudes.index'))->assertOk()->assertDontSee($requerimiento->codigo);

        $this->assertModelExists($requerimiento);
    }

    public function test_un_usuario_solo_descarga_su_propia_acta_de_asignacion(): void
    {
        [$usuarioA, , , $responsableB] = $this->dosResponsables();

        $this->actingAs($usuarioA)->get(route('mis-bienes.acta', ['responsable_id' => $responsableB->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="acta-de-asignacion-'.Str::slug($usuarioA->responsable->nombre_completo).'.pdf"');

        $this->actingAs($usuarioA)->get(route('reportes.exportar', ['por-responsable', 'pdf', 'responsable_id' => $responsableB->id]))->assertForbidden();
        $this->actingAs($usuarioA)->get(route('reportes.show', ['por-responsable', 'responsable_id' => $responsableB->id]))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // El Operador no aprueba, ni con peticiones directas
    // -------------------------------------------------------------------------

    public function test_el_operador_no_puede_aprobar_ni_con_peticiones_directas(): void
    {
        $operador = $this->usuarioCon(RolSistema::Operador);
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $bien = Bien::factory()->create();
        $destino = Area::factory()->create();

        $this->actingAs($operador)->post(route('movimientos.store'), [
            'bien_id' => $bien->id, 'tipo' => 'traslado', 'sede_destino_id' => $destino->sede_id, 'area_destino_id' => $destino->id, 'motivo' => 'Reorganización',
        ])->assertSessionHasNoErrors();
        $this->actingAs($operador)->post(route('bajas.store'), [
            'bien_id' => Bien::factory()->create()->id, 'motivo' => 'deterioro', 'fecha_baja' => now()->toDateString(), 'descripcion' => 'Rota',
        ])->assertSessionHasNoErrors();
        $this->actingAs($operador)->post(route('requerimientos.store'), ['tipo_requerimiento' => 'Mobiliario', 'descripcion' => 'Mesa', 'justificacion' => 'Aula'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('inventarios.store'), ['sede_id' => $bien->sede_id, 'nombre' => 'Inventario 2026', 'fecha_inicio' => now()->toDateString()])->assertSessionHasNoErrors();
        $inventario = Inventario::sole();
        $this->actingAs($admin)->patch(route('inventarios.cerrar', $inventario))->assertSessionHasNoErrors();

        $movimiento = Movimiento::sole();
        $baja = Baja::sole();
        $requerimiento = Requerimiento::sole();

        $this->actingAs($operador);
        $this->get(route('aprobaciones.index'))->assertForbidden();
        $this->patch(route('movimientos.aprobar', $movimiento))->assertForbidden();
        $this->patch(route('movimientos.rechazar', $movimiento), ['motivo_rechazo' => 'x'])->assertForbidden();
        $this->patch(route('movimientos.ejecutar', $movimiento))->assertForbidden();
        $this->patch(route('bajas.aprobar', $baja))->assertForbidden();
        $this->patch(route('bajas.rechazar', $baja), ['motivo_rechazo' => 'x'])->assertForbidden();
        $this->patch(route('requerimientos.estado', $requerimiento), ['estado' => 'aprobado'])->assertForbidden();
        $this->patch(route('inventarios.reabrir', $inventario))->assertForbidden();
        $this->patchJson(route('movimientos.aprobar', $movimiento))->assertForbidden();

        $this->assertSame('solicitado', $movimiento->fresh()->estado);
        $this->assertSame('solicitada', $baja->fresh()->estado);
        $this->assertSame('pendiente', $requerimiento->fresh()->estado);
        $this->assertFalse($inventario->fresh()->estaAbierto());
    }
}
