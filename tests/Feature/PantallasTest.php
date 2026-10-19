<?php

namespace Tests\Feature;

use App\Models\Bien;
use App\Models\Inventario;
use App\Models\Requerimiento;
use App\Models\Role;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renderiza cada pantalla con los datos de demostración para detectar errores
 * en las vistas (variables faltantes, rutas inexistentes, relaciones nulas).
 */
class PantallasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        Usuario::query()->update(['debe_cambiar_password' => false]);
    }

    public function test_el_administrador_puede_abrir_todas_las_pantallas(): void
    {
        $admin = Usuario::where('email', 'admin@sibip.local')->firstOrFail();
        $bien = Bien::firstOrFail();

        $rutas = [
            route('dashboard'),
            route('bienes.index'),
            route('bienes.index', ['buscar' => 'Laptop', 'sede_id' => $bien->sede_id]),
            route('bienes.create'),
            route('bienes.show', $bien),
            route('bienes.edit', $bien),
            route('movimientos.index'),
            route('movimientos.index', ['estado' => 'solicitado']),
            route('movimientos.create'),
            route('bajas.index'),
            route('bajas.create'),
            route('requerimientos.index'),
            route('requerimientos.create'),
            route('requerimientos.show', Requerimiento::firstOrFail()),
            route('mantenimientos.index'),
            route('mantenimientos.create'),
            route('documentos.index'),
            route('sedes.index'),
            route('areas.index'),
            route('categorias.index'),
            route('responsables.index'),
            route('usuarios.index'),
            route('usuarios.create'),
            route('usuarios.edit', Usuario::where('email', 'usuario@sibip.local')->firstOrFail()),
            route('roles.index'),
            route('roles.create'),
            route('roles.edit', Role::firstOrFail()),
            route('auditoria.index'),
            route('password.cambiar'),
            route('etiquetas.index'),
            route('etiquetas.index', ['sede_id' => $bien->sede_id, 'solo_sin_etiqueta' => 1]),
            route('configuracion.etiquetas'),
            route('configuracion.etiquetas.vista-previa'),
            route('etiquetas.prueba', 'html'),
            route('etiquetas.prueba', 'pdf'),
            route('bienes.index', ['etiqueta' => 'sin']),
            route('inventarios.index'),
            route('inventarios.create'),
        ];

        foreach ($rutas as $ruta) {
            $this->actingAs($admin)->get($ruta)->assertOk();
        }

        // Toma de inventario: escaneo, avance y resultado cerrado.
        $this->actingAs($admin)->post(route('inventarios.store'), ['nombre' => 'Prueba', 'sede_id' => $bien->sede_id, 'fecha_inicio' => now()->toDateString()]);
        $inventario = Inventario::firstOrFail();
        $this->actingAs($admin)->postJson(route('inventarios.lecturas', $inventario), ['codigo' => $bien->codigo_barras])->assertOk();
        $this->actingAs($admin)->get(route('inventarios.escanear', $inventario))->assertOk();
        $this->actingAs($admin)->get(route('inventarios.show', $inventario))->assertOk();
        $this->actingAs($admin)->patch(route('inventarios.cerrar', $inventario));
        $this->actingAs($admin)->get(route('inventarios.show', $inventario))->assertOk()->assertSee('Faltantes');
    }

    public function test_el_usuario_responsable_puede_abrir_sus_pantallas(): void
    {
        $usuario = Usuario::where('email', 'usuario@sibip.local')->firstOrFail();
        $suBien = Bien::where('responsable_id', $usuario->responsable->id)->firstOrFail();

        foreach ([
            route('mis-bienes.index'),
            route('bienes.show', $suBien),
            route('bajas.create'),
            route('movimientos.create', ['bien_id' => $suBien->id]),
            route('requerimientos.create'),
            route('requerimientos.show', Requerimiento::where('usuario_id', $usuario->id)->firstOrFail()),
        ] as $ruta) {
            $this->actingAs($usuario)->get($ruta)->assertOk();
        }
    }
}
