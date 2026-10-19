<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Area;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Responsable;
use App\Models\Sede;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuarioCon(RolSistema::Administrador);
    }

    public function test_las_pantallas_de_catalogos_cargan_con_sus_filtros(): void
    {
        Sede::factory()->create(['nombre' => 'Sede Jerusalén', 'codigo' => 'S07']);
        Sede::factory()->create(['nombre' => 'Sede Huancané', 'codigo' => 'S08', 'estado' => 'inactivo']);

        $this->actingAs($this->admin)->get(route('sedes.index', ['buscar' => 'Jerus']))
            ->assertOk()->assertSee('Sede Jerusalén')->assertDontSee('Sede Huancané');
        $this->actingAs($this->admin)->get(route('sedes.index', ['estado' => 'inactivo']))
            ->assertSee('Sede Huancané')->assertDontSee('Sede Jerusalén');

        foreach (['areas.index', 'categorias.index', 'responsables.index', 'mantenimientos.index', 'documentos.index', 'auditoria.index'] as $ruta) {
            $this->actingAs($this->admin)->get(route($ruta, ['buscar' => 'x']))->assertOk();
        }
    }

    public function test_el_estado_vacio_se_muestra_cuando_no_hay_resultados(): void
    {
        $this->actingAs($this->admin)->get(route('categorias.index', ['buscar' => 'no-existe']))
            ->assertOk()
            ->assertSee('No hay categorías')
            ->assertSee('Pruebe con otros filtros.');
    }

    public function test_se_edita_y_se_desactiva_una_sede_desde_el_formulario_del_catalogo(): void
    {
        $sede = Sede::factory()->create(['codigo' => 'S05', 'nombre' => 'Sede vieja']);

        $this->actingAs($this->admin)->put(route('sedes.update', $sede), [
            'codigo' => 'S05', 'nombre' => 'Sede renovada', 'estado' => 'inactivo', '_url_edicion' => route('sedes.update', $sede),
        ])->assertRedirect(route('sedes.index'));

        $this->assertSame(['Sede renovada', 'inactivo'], [$sede->fresh()->nombre, $sede->fresh()->estado]);
    }

    public function test_un_area_con_bienes_no_cambia_de_sede_ni_se_elimina(): void
    {
        $area = Area::factory()->create();
        Bien::factory()->create(['area_id' => $area->id, 'sede_id' => $area->sede_id]);

        $this->actingAs($this->admin)->put(route('areas.update', $area), [
            'sede_id' => Sede::factory()->create()->id, 'nombre' => $area->nombre, 'estado' => 'activo',
        ])->assertSessionHas('error');
        $this->actingAs($this->admin)->delete(route('areas.destroy', $area))->assertSessionHas('error');

        $this->assertNotSoftDeleted($area);
    }

    public function test_una_categoria_sin_bienes_se_retira_con_soft_delete(): void
    {
        $categoria = Categoria::factory()->create();

        $this->actingAs($this->admin)->delete(route('categorias.destroy', $categoria))->assertRedirect(route('categorias.index'));

        $this->assertSoftDeleted($categoria);
    }

    public function test_el_responsable_se_edita_con_un_area_de_su_sede(): void
    {
        $responsable = Responsable::factory()->create();
        $areaAjena = Area::factory()->create();

        $this->actingAs($this->admin)->put(route('responsables.update', $responsable), [
            'nombres' => 'Ana', 'apellidos' => 'Quispe', 'sede_id' => $responsable->sede_id, 'area_id' => $areaAjena->id,
        ])->assertSessionHasErrors('area_id');
    }

    public function test_la_auditoria_filtra_por_tabla_y_fechas(): void
    {
        Bien::factory()->create();

        $this->actingAs($this->admin)->get(route('auditoria.index', ['tabla' => 'bienes', 'desde' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Ver cambios');
        $this->actingAs($this->admin)->get(route('auditoria.index', ['desde' => '2026-10-10', 'hasta' => '2026-10-01']))
            ->assertSessionHasErrors('hasta');
    }
}
