<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Requerimiento;
use App\Models\Sede;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegridadYAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_bien_no_se_registra_con_un_area_de_otra_sede(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $area = Area::factory()->create();

        $this->actingAs($admin)->post(route('bienes.store'), [
            'nombre' => 'Proyector',
            'categoria_id' => Categoria::factory()->create()->id,
            'sede_id' => Sede::factory()->create()->id,
            'area_id' => $area->id,
            'estado' => 'bueno',
        ])->assertSessionHasErrors(['area_id' => 'El área seleccionada no pertenece a la sede indicada.']);

        $this->assertDatabaseCount('bienes', 0);
    }

    public function test_los_cambios_de_un_bien_quedan_en_la_auditoria_con_datos_anteriores_y_nuevos(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $bien = Bien::factory()->create(['nombre' => 'Laptop']);

        $this->actingAs($admin)->put(route('bienes.update', $bien), [
            'codigo_patrimonial' => $bien->codigo_patrimonial,
            'nombre' => 'Laptop Lenovo',
            'categoria_id' => $bien->categoria_id,
            'sede_id' => $bien->sede_id,
            'area_id' => $bien->area_id,
            'estado' => 'regular',
        ])->assertRedirect(route('bienes.index'));

        $registro = Auditoria::where('tabla', 'bienes')->where('accion', 'actualizar')->where('registro_id', $bien->id)->firstOrFail();
        $this->assertSame($admin->id, $registro->usuario_id);
        $this->assertSame('127.0.0.1', $registro->ip);
        $this->assertEquals(['nombre' => 'Laptop', 'estado' => 'bueno'], array_intersect_key($registro->datos_anteriores, ['nombre' => 1, 'estado' => 1]));
        $this->assertEquals(['nombre' => 'Laptop Lenovo', 'estado' => 'regular'], array_intersect_key($registro->datos_nuevos, ['nombre' => 1, 'estado' => 1]));
    }

    public function test_la_auditoria_de_usuarios_no_guarda_la_contrasena(): void
    {
        $usuario = $this->usuarioCon(RolSistema::Operador);

        $registro = Auditoria::where('tabla', 'usuarios')->where('registro_id', $usuario->id)->firstOrFail();

        $this->assertArrayNotHasKey('password', $registro->datos_nuevos);
        $this->assertArrayHasKey('email', $registro->datos_nuevos);
    }

    public function test_un_bien_con_historial_no_se_elimina_y_uno_sin_historial_se_retira_con_soft_delete(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $conHistorial = Bien::factory()->create();
        $conHistorial->bajas()->create([
            'motivo' => 'deterioro', 'estado' => 'rechazada', 'fecha_baja' => now()->toDateString(), 'usuario_id' => $admin->id,
        ]);
        $sinHistorial = Bien::factory()->create();

        $this->actingAs($admin)->delete(route('bienes.destroy', $conHistorial))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('bienes.destroy', $sinHistorial))->assertRedirect(route('bienes.index'));

        $this->assertNotSoftDeleted($conHistorial);
        $this->assertSoftDeleted($sinHistorial);
    }

    public function test_el_requerimiento_solo_avanza_por_transiciones_validas(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $requerimiento = Requerimiento::create([
            'codigo' => 'REQ-2026-0001',
            'usuario_id' => $this->usuarioCon(RolSistema::Usuario)->id,
            'tipo_requerimiento' => 'Mobiliario',
            'descripcion' => 'Dos sillas',
            'justificacion' => 'Sala de docentes',
            'cantidad' => 2,
            'prioridad' => 'media',
            'estado' => 'pendiente',
            'fecha_solicitud' => now(),
        ]);

        $this->actingAs($admin)->patch(route('requerimientos.estado', $requerimiento), ['estado' => 'atendido'])
            ->assertSessionHasErrors('estado');
        $this->actingAs($admin)->patch(route('requerimientos.estado', $requerimiento), ['estado' => 'rechazado'])
            ->assertSessionHasErrors('motivo_rechazo');

        $this->actingAs($admin)->patch(route('requerimientos.estado', $requerimiento), ['estado' => 'aprobado'])
            ->assertSessionHasNoErrors();

        $requerimiento->refresh();
        $this->assertSame('aprobado', $requerimiento->estado);
        $this->assertSame($admin->id, $requerimiento->aprobado_por);
        $this->assertNotNull($requerimiento->fecha_resolucion);
    }

    public function test_los_datos_de_demostracion_son_consistentes_y_conservan_las_tildes(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseHas('categorias', ['nombre' => 'Equipos informáticos']);
        $this->assertSame(3, Sede::count());
        $this->assertGreaterThanOrEqual(50, Bien::count());

        $inconsistentes = Bien::query()
            ->join('areas', 'areas.id', '=', 'bienes.area_id')
            ->whereColumn('areas.sede_id', '!=', 'bienes.sede_id')
            ->count();
        $this->assertSame(0, $inconsistentes);

        foreach (RolSistema::cases() as $rol) {
            $this->assertTrue(Usuario::whereHas('rol', fn ($q) => $q->where('nombre', $rol->value))->exists(), "Falta un usuario de rol {$rol->value}");
        }
    }
}
