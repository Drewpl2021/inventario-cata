<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Area;
use App\Models\Bien;
use App\Models\Movimiento;
use App\Models\Responsable;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlujoMovimientosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $operador;

    private Bien $bien;

    private Area $destino;

    private Responsable $nuevoResponsable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operador = $this->usuarioCon(RolSistema::Operador);
        $this->bien = Bien::factory()->create();
        $this->destino = Area::factory()->create();
        $this->nuevoResponsable = Responsable::factory()->create(['area_id' => $this->destino->id, 'sede_id' => $this->destino->sede_id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function solicitud(array $cambios = []): array
    {
        return [
            'bien_id' => $this->bien->id,
            'tipo' => 'traslado',
            'sede_destino_id' => $this->destino->sede_id,
            'area_destino_id' => $this->destino->id,
            'responsable_nuevo_id' => $this->nuevoResponsable->id,
            'motivo' => 'Reorganización de ambientes',
            ...$cambios,
        ];
    }

    public function test_la_solicitud_no_cambia_la_ubicacion_del_bien_hasta_ser_aprobada(): void
    {
        $sedeOriginal = $this->bien->sede_id;

        $this->actingAs($this->operador)->post(route('movimientos.store'), $this->solicitud())
            ->assertRedirect(route('movimientos.index'));

        $movimiento = Movimiento::firstOrFail();
        $this->assertSame('solicitado', $movimiento->estado);
        $this->assertSame($sedeOriginal, $this->bien->fresh()->sede_id);
    }

    public function test_al_aprobar_se_actualizan_sede_area_y_responsable_del_bien(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $this->actingAs($this->operador)->post(route('movimientos.store'), $this->solicitud());
        $movimiento = Movimiento::firstOrFail();

        $this->actingAs($admin)->patch(route('movimientos.aprobar', $movimiento))->assertSessionHasNoErrors();

        $bien = $this->bien->fresh();
        $this->assertSame($this->destino->sede_id, $bien->sede_id);
        $this->assertSame($this->destino->id, $bien->area_id);
        $this->assertSame($this->nuevoResponsable->id, $bien->responsable_id);

        $movimiento->refresh();
        $this->assertSame('aprobado', $movimiento->estado);
        $this->assertSame($admin->id, $movimiento->aprobado_por);
    }

    public function test_al_rechazar_el_bien_no_cambia(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $this->actingAs($this->operador)->post(route('movimientos.store'), $this->solicitud());
        $movimiento = Movimiento::firstOrFail();

        $this->actingAs($admin)->patch(route('movimientos.rechazar', $movimiento), ['motivo_rechazo' => 'El ambiente no está listo'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rechazado', $movimiento->fresh()->estado);
        $this->assertSame($this->bien->area_id, $this->bien->fresh()->area_id);
    }

    public function test_un_movimiento_aprobado_puede_marcarse_como_ejecutado_una_sola_vez(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $this->actingAs($admin)->post(route('movimientos.store'), $this->solicitud());
        $movimiento = Movimiento::firstOrFail();
        $this->assertSame('aprobado', $movimiento->estado);

        $this->actingAs($admin)->patch(route('movimientos.ejecutar', $movimiento))->assertSessionHasNoErrors();
        $this->assertSame('ejecutado', $movimiento->fresh()->estado);

        $this->actingAs($admin)->patch(route('movimientos.aprobar', $movimiento))->assertSessionHasErrors('movimiento');
    }

    public function test_rechaza_un_area_de_destino_que_no_pertenece_a_la_sede_de_destino(): void
    {
        $areaDeOtraSede = Area::factory()->create();

        $this->actingAs($this->operador)->post(route('movimientos.store'), $this->solicitud(['area_destino_id' => $areaDeOtraSede->id]))
            ->assertSessionHasErrors(['area_destino_id' => 'El área seleccionada no pertenece a la sede indicada.']);

        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_no_se_permiten_dos_solicitudes_pendientes_para_el_mismo_bien(): void
    {
        $this->actingAs($this->operador)->post(route('movimientos.store'), $this->solicitud());

        $this->actingAs($this->operador)->post(route('movimientos.store'), $this->solicitud())
            ->assertSessionHasErrors('bien_id');

        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_un_bien_dado_de_baja_no_se_puede_mover(): void
    {
        $this->bien->update(['estado' => 'dado_de_baja']);

        $this->actingAs($this->operador)->post(route('movimientos.store'), $this->solicitud())->assertForbidden();
    }

    public function test_el_operador_no_puede_aprobar_movimientos(): void
    {
        $this->actingAs($this->operador)->post(route('movimientos.store'), $this->solicitud());

        $this->actingAs($this->operador)->patch(route('movimientos.aprobar', Movimiento::firstOrFail()))->assertForbidden();
    }
}
