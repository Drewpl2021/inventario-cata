<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Area;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Movimiento;
use App\Models\Requerimiento;
use App\Models\Responsable;
use App\Models\Role;
use App\Models\Sede;
use App\Models\Usuario;
use App\Notifications\SolicitudRegistrada;
use App\Notifications\SolicitudResuelta;
use App\Reportes\CatalogoReportes;
use Database\Seeders\AdministradorInicialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InterfazYNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $usuario;

    private Responsable $responsable;

    private Bien $suBien;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuarioCon(RolSistema::Administrador);
        $this->usuario = $this->usuarioCon(RolSistema::Usuario);
        $this->responsable = Responsable::factory()->create(['usuario_id' => $this->usuario->id]);
        $this->suBien = Bien::factory()->create(['responsable_id' => $this->responsable->id, 'nombre' => 'Laptop de secretaría']);
    }

    public function test_quien_aprueba_recibe_la_solicitud_y_el_solicitante_la_resolucion(): void
    {
        $operador = $this->usuarioCon(RolSistema::Operador);

        $this->actingAs($this->usuario)->post(route('bajas.store'), ['bien_id' => $this->suBien->id, 'motivo' => 'deterioro'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->admin->notifications()->where('type', SolicitudRegistrada::class)->count());
        $this->assertSame(0, $operador->notifications()->count(), 'El Operador no aprueba bajas.');

        $this->actingAs($this->admin)->patch(route('bajas.rechazar', Baja::firstOrFail()), ['motivo_rechazo' => 'Aún funciona'])
            ->assertSessionHasNoErrors();

        $resolucion = $this->usuario->notifications()->where('type', SolicitudResuelta::class)->sole();
        $this->assertSame('rechazada', $resolucion->data['estado']);
        $this->assertStringContainsString('Aún funciona', $resolucion->data['mensaje']);
        $this->assertSame(0, $this->admin->notifications()->where('type', SolicitudResuelta::class)->count());
    }

    public function test_las_notificaciones_se_marcan_como_leidas(): void
    {
        $this->actingAs($this->usuario)->post(route('requerimientos.store'), [
            'tipo_requerimiento' => 'Equipos de Audio y Video', 'descripcion' => 'Parlante', 'justificacion' => 'Actuaciones',
        ])->assertSessionHasNoErrors();
        $notificacion = $this->admin->unreadNotifications()->sole();

        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('Nueva solicitud de requerimiento');
        $this->actingAs($this->admin)->patchJson(route('notificaciones.leer', $notificacion->id))->assertOk();

        $this->assertNotNull($notificacion->fresh()->read_at);
        $this->actingAs($this->usuario)->patchJson(route('notificaciones.leer', $notificacion->id))->assertNotFound();
    }

    public function test_la_bandeja_de_aprobacion_lista_lo_pendiente_y_el_menu_muestra_el_contador(): void
    {
        Baja::create(['bien_id' => $this->suBien->id, 'motivo' => 'robo', 'estado' => 'solicitada', 'fecha_baja' => now(), 'usuario_id' => $this->usuario->id]);
        Movimiento::forceCreate([
            'bien_id' => Bien::factory()->create()->id, 'sede_destino_id' => Sede::factory()->create()->id,
            'tipo' => 'traslado', 'estado' => 'solicitado', 'fecha_movimiento' => now(), 'usuario_id' => $this->usuario->id,
        ]);

        $this->actingAs($this->admin)->get(route('aprobaciones.index'))
            ->assertOk()
            ->assertSee($this->suBien->codigo_barras)
            ->assertSee('Aprobar');

        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('2 pendientes', false);

        $this->actingAs($this->usuarioCon(RolSistema::Operador))->get(route('aprobaciones.index'))->assertForbidden();
    }

    public function test_mis_solicitudes_muestra_solo_las_propias_con_su_estado_y_motivo_de_rechazo(): void
    {
        Baja::create(['bien_id' => $this->suBien->id, 'motivo' => 'robo', 'estado' => 'rechazada', 'motivo_rechazo' => 'Falta denuncia policial', 'fecha_baja' => now(), 'usuario_id' => $this->usuario->id]);
        Requerimiento::create([
            'codigo' => 'REQ-2026-0009', 'usuario_id' => $this->usuario->id, 'tipo_requerimiento' => 'Mobiliario',
            'descripcion' => 'Silla', 'justificacion' => 'Oficina', 'cantidad' => 1, 'prioridad' => 'media',
            'estado' => 'en_cotizacion', 'fecha_solicitud' => now(),
        ]);
        Requerimiento::create([
            'codigo' => 'REQ-2026-0010', 'usuario_id' => $this->admin->id, 'tipo_requerimiento' => 'Ajeno',
            'descripcion' => 'X', 'justificacion' => 'X', 'cantidad' => 1, 'prioridad' => 'media', 'estado' => 'pendiente', 'fecha_solicitud' => now(),
        ]);

        $this->actingAs($this->usuario)->get(route('solicitudes.index'))
            ->assertOk()
            ->assertSee('Rechazada')
            ->assertSee('Falta denuncia policial')
            ->assertSee('En proceso')
            ->assertDontSee('REQ-2026-0010');

        $this->actingAs($this->usuario)->get(route('solicitudes.index', ['pestana' => 'requerimientos']))
            ->assertSee('REQ-2026-0009')
            ->assertDontSee('Falta denuncia policial');
    }

    public function test_el_buscador_global_respeta_lo_que_cada_usuario_puede_ver(): void
    {
        $ajeno = Bien::factory()->create(['nombre' => 'Laptop de dirección', 'categoria_id' => Categoria::factory()->create(['nombre' => 'Cómputo'])->id]);

        $admin = $this->actingAs($this->admin)->getJson(route('buscar', ['q' => 'laptop']))->assertOk()->json('resultados');
        $this->assertCount(2, $admin);

        $porCategoria = $this->actingAs($this->admin)->getJson(route('buscar', ['q' => 'Cómputo']))->json('resultados');
        $this->assertSame([$ajeno->codigo_barras], array_column($porCategoria, 'codigo'));

        $propios = $this->actingAs($this->usuario)->getJson(route('buscar', ['q' => 'laptop']))->json('resultados');
        $this->assertSame([$this->suBien->codigo_barras], array_column($propios, 'codigo'));
    }

    public function test_el_selector_de_bienes_solo_ofrece_los_solicitables(): void
    {
        $conBajaEnTramite = Bien::factory()->create(['responsable_id' => $this->responsable->id]);
        Baja::create(['bien_id' => $conBajaEnTramite->id, 'motivo' => 'robo', 'estado' => 'solicitada', 'fecha_baja' => now(), 'usuario_id' => $this->usuario->id]);
        Bien::factory()->create();

        $codigos = array_column($this->actingAs($this->usuario)->getJson(route('buscar.bienes-solicitables', ['para' => 'bajas']))->json('resultados'), 'codigo');

        $this->assertSame([$this->suBien->codigo_barras], $codigos);
    }

    public function test_mis_bienes_solo_aparece_en_el_menu_con_un_responsable_vinculado(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertDontSee(route('mis-bienes.index'));

        Responsable::factory()->create(['usuario_id' => $this->admin->id]);
        $this->actingAs($this->admin->fresh())->get(route('dashboard'))->assertSee(route('mis-bienes.index'));
    }

    public function test_el_inicio_del_usuario_muestra_sus_bienes(): void
    {
        $this->actingAs($this->usuario)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('¡Hola, '.$this->usuario->nombre.'!')
            ->assertSee($this->suBien->codigo_barras)
            ->assertSee('Ver todos mis bienes');
    }

    public function test_el_movimiento_del_responsable_conserva_el_responsable_y_deduce_el_tipo(): void
    {
        $destino = Area::factory()->create(['sede_id' => $this->suBien->sede_id]);

        $this->actingAs($this->usuario)->post(route('movimientos.store'), [
            'bien_id' => $this->suBien->id, 'sede_destino_id' => $this->suBien->sede_id, 'area_destino_id' => $destino->id,
        ])->assertSessionHasNoErrors();

        $movimiento = Movimiento::sole();
        $this->assertSame('reubicacion', $movimiento->tipo);
        $this->assertSame($this->responsable->id, $movimiento->responsable_nuevo_id);
        $this->assertSame('solicitado', $movimiento->estado);
    }

    public function test_el_requerimiento_acepta_solo_pdf_o_imagen(): void
    {
        Storage::fake('local');
        $datos = ['tipo_requerimiento' => 'Mobiliario', 'descripcion' => 'Mesa', 'justificacion' => 'Aula'];

        $this->actingAs($this->usuario)->post(route('requerimientos.store'), [...$datos, 'archivo_adjunto' => UploadedFile::fake()->create('cotizacion.docx', 100)])
            ->assertSessionHasErrors('archivo_adjunto');

        $this->actingAs($this->usuario)->post(route('requerimientos.store'), [...$datos, 'archivo_adjunto' => UploadedFile::fake()->createWithContent('cotizacion.pdf', "%PDF-1.4\n%%EOF\n")])
            ->assertSessionHasNoErrors();

        $requerimiento = Requerimiento::sole();
        $this->assertSame(1, $requerimiento->cantidad);
        $this->assertSame('media', $requerimiento->prioridad);
        Storage::disk('local')->assertExists($requerimiento->archivo_adjunto);
    }

    public function test_la_cuenta_de_rol_usuario_guarda_dni_cargo_y_telefono_en_su_responsable(): void
    {
        $area = Area::factory()->create();

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'nombre' => 'Elena', 'apellido' => 'Pari', 'email' => 'elena@colegio.test',
            'rol_id' => Role::delSistema(RolSistema::Usuario)->id, 'sede_id' => $area->sede_id, 'area_id' => $area->id, 'estado' => 'activo',
            'dni' => '41234567', 'cargo' => 'Docente de Primaria', 'telefono' => '951234567',
        ])->assertSessionHas('password_temporal');

        $this->assertDatabaseHas('responsables', ['dni' => '41234567', 'cargo' => 'Docente de Primaria', 'telefono' => '951234567', 'usuario_id' => Usuario::where('email', 'elena@colegio.test')->value('id')]);
        $this->actingAs($this->admin)->get(route('usuarios.index'))->assertSee('Imprimir ficha de acceso');
    }

    public function test_el_seeder_crea_el_administrador_inicial_desde_la_configuracion(): void
    {
        config(['sibip.admin_inicial' => ['email' => 'Direccion@Colegio.test', 'password' => 'Temporal2026', 'nombre' => 'Dirección', 'apellido' => 'CATA']]);

        $this->seed(AdministradorInicialSeeder::class);
        $this->seed(AdministradorInicialSeeder::class);

        $admin = Usuario::where('email', 'direccion@colegio.test')->sole();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->debe_cambiar_password);
    }

    public function test_reportes_muestra_el_catalogo_con_sus_ocho_reportes(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('reportes.index'))->assertOk();

        foreach (array_keys(CatalogoReportes::todos()) as $clave) {
            $respuesta->assertSee(route('reportes.show', $clave));
        }
        $respuesta->assertDontSee('Próximamente');
    }
}
