<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Responsable;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlujoBajasTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuario;

    private Bien $bien;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = $this->usuarioCon(RolSistema::Usuario);
        $responsable = Responsable::factory()->create(['usuario_id' => $this->usuario->id]);
        $this->bien = Bien::factory()->create(['responsable_id' => $responsable->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function solicitud(array $cambios = []): array
    {
        return [
            'bien_id' => $this->bien->id,
            'motivo' => 'deterioro',
            'fecha_baja' => now()->toDateString(),
            'descripcion' => 'La pantalla no enciende.',
            ...$cambios,
        ];
    }

    public function test_el_responsable_solicita_la_baja_de_su_bien_y_queda_pendiente(): void
    {
        $this->actingAs($this->usuario)->post(route('bajas.store'), $this->solicitud())
            ->assertRedirect(route('mis-bienes.index'));

        $this->assertDatabaseHas('bajas', ['bien_id' => $this->bien->id, 'estado' => 'solicitada', 'usuario_id' => $this->usuario->id]);
        $this->assertSame('bueno', $this->bien->fresh()->estado);
    }

    public function test_el_responsable_no_puede_solicitar_la_baja_de_un_bien_ajeno(): void
    {
        $ajeno = Bien::factory()->create();

        $this->actingAs($this->usuario)->post(route('bajas.store'), $this->solicitud(['bien_id' => $ajeno->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('bajas', 0);
    }

    public function test_al_aprobar_la_baja_el_bien_queda_dado_de_baja_y_se_registra_quien_la_aprobo(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $this->actingAs($this->usuario)->post(route('bajas.store'), $this->solicitud());
        $baja = Baja::firstOrFail();

        $this->actingAs($admin)->patch(route('bajas.aprobar', $baja), ['documento_referencia' => 'RD-045-2026'])
            ->assertSessionHasNoErrors();

        $baja->refresh();
        $this->assertSame('aprobada', $baja->estado);
        $this->assertSame($admin->id, $baja->aprobado_por);
        $this->assertNotNull($baja->fecha_resolucion);
        $this->assertSame('dado_de_baja', $this->bien->fresh()->estado);
    }

    public function test_no_se_permiten_dos_bajas_activas_para_el_mismo_bien(): void
    {
        $this->actingAs($this->usuario)->post(route('bajas.store'), $this->solicitud());

        $this->actingAs($this->usuario)->post(route('bajas.store'), $this->solicitud())
            ->assertSessionHasErrors(['bien_id' => 'Este bien ya tiene una solicitud de baja en trámite o aprobada.']);

        $this->assertDatabaseCount('bajas', 1);
    }

    public function test_una_baja_rechazada_puede_volver_a_solicitarse(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $this->actingAs($this->usuario)->post(route('bajas.store'), $this->solicitud());
        $baja = Baja::firstOrFail();

        $this->actingAs($admin)->patch(route('bajas.rechazar', $baja), ['motivo_rechazo' => 'Falta informe técnico'])
            ->assertSessionHasNoErrors();
        $this->assertSame('rechazada', $baja->fresh()->estado);
        $this->assertSame('bueno', $this->bien->fresh()->estado);

        $this->actingAs($this->usuario)->post(route('bajas.store'), $this->solicitud())
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->bien->bajas()->count());
    }

    public function test_una_baja_ya_resuelta_no_se_puede_volver_a_aprobar(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $this->actingAs($this->usuario)->post(route('bajas.store'), $this->solicitud());
        $baja = Baja::firstOrFail();
        $this->actingAs($admin)->patch(route('bajas.rechazar', $baja), ['motivo_rechazo' => 'No corresponde']);

        $this->actingAs($admin)->patch(route('bajas.aprobar', $baja))->assertSessionHasErrors('baja');

        $this->assertSame('rechazada', $baja->fresh()->estado);
        $this->assertSame('bueno', $this->bien->fresh()->estado);
    }

    public function test_el_operador_registra_solicitudes_pero_no_puede_aprobarlas(): void
    {
        $operador = $this->usuarioCon(RolSistema::Operador);

        $this->actingAs($operador)->post(route('bajas.store'), $this->solicitud())->assertSessionHasNoErrors();
        $baja = Baja::firstOrFail();
        $this->assertSame('solicitada', $baja->estado);

        $this->actingAs($operador)->patch(route('bajas.aprobar', $baja))->assertForbidden();
    }

    public function test_el_administrador_registra_la_baja_aprobada_directamente(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);

        $this->actingAs($admin)->post(route('bajas.store'), $this->solicitud())->assertRedirect(route('bajas.index'));

        $this->assertDatabaseHas('bajas', ['bien_id' => $this->bien->id, 'estado' => 'aprobada', 'aprobado_por' => $admin->id]);
        $this->assertSame('dado_de_baja', $this->bien->fresh()->estado);
    }
}
