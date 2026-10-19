<?php

namespace App\Services;

use App\Models\Baja;
use App\Models\Bien;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flujo de bajas: solicitada → aprobada (el bien pasa a 'dado_de_baja') o rechazada.
 * Un bien no puede tener dos bajas 'solicitada' o 'aprobada' al mismo tiempo.
 */
class GestionBajas
{
    public function __construct(private readonly Notificador $notificador) {}

    /**
     * Registra una solicitud de baja. Si quien la registra puede aprobar bajas,
     * queda aprobada en el mismo acto, salvo que venga de una toma de inventario
     * (esas quedan siempre como solicitud para revisarlas).
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrar(Usuario $solicitante, array $datos): Baja
    {
        return DB::transaction(function () use ($solicitante, $datos) {
            $bien = Bien::lockForUpdate()->findOrFail((int) $datos['bien_id']);

            if ($bien->estaDadoDeBaja()) {
                throw ValidationException::withMessages(['bien_id' => 'El bien ya fue dado de baja.']);
            }

            $tieneBajaActiva = $bien->bajas()->whereIn('estado', Baja::ESTADOS_ACTIVOS)->exists();

            if ($tieneBajaActiva) {
                throw ValidationException::withMessages(['bien_id' => 'Este bien ya tiene una solicitud de baja en trámite o aprobada.']);
            }

            $baja = Baja::create([
                ...$datos,
                'estado' => 'solicitada',
                'usuario_id' => $solicitante->id,
            ]);

            if ($solicitante->can('bajas.aprobar') && empty($datos['inventario_id'])) {
                $this->aprobar($baja, $solicitante);
            } else {
                $this->notificador->solicitudRegistrada($baja);
            }

            return $baja;
        });
    }

    /**
     * @param  array{documento_referencia?: string|null, valor_residual?: float|string|null}  $datos
     */
    public function aprobar(Baja $baja, Usuario $aprobador, array $datos = []): Baja
    {
        return DB::transaction(function () use ($baja, $aprobador, $datos) {
            $baja = Baja::lockForUpdate()->findOrFail($baja->id);
            $this->exigirSolicitada($baja);

            $baja->update([
                'estado' => 'aprobada',
                'aprobado_por' => $aprobador->id,
                'fecha_resolucion' => now(),
                'documento_referencia' => $datos['documento_referencia'] ?? $baja->documento_referencia,
                'valor_residual' => $datos['valor_residual'] ?? $baja->valor_residual,
            ]);

            $bien = Bien::lockForUpdate()->findOrFail($baja->bien_id);
            $bien->update(['estado' => 'dado_de_baja']);
            $this->notificador->solicitudResuelta($baja, $aprobador);

            return $baja;
        });
    }

    public function rechazar(Baja $baja, Usuario $aprobador, string $motivo): Baja
    {
        return DB::transaction(function () use ($baja, $aprobador, $motivo) {
            $baja = Baja::lockForUpdate()->findOrFail($baja->id);
            $this->exigirSolicitada($baja);

            $baja->update([
                'estado' => 'rechazada',
                'motivo_rechazo' => $motivo,
                'aprobado_por' => $aprobador->id,
                'fecha_resolucion' => now(),
            ]);
            $this->notificador->solicitudResuelta($baja, $aprobador);

            return $baja;
        });
    }

    private function exigirSolicitada(Baja $baja): void
    {
        if (! $baja->estaSolicitada()) {
            throw ValidationException::withMessages(['baja' => 'Esta solicitud de baja ya fue resuelta.']);
        }
    }
}
