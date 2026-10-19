<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\Movimiento;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flujo de movimientos: solicitado → aprobado → ejecutado, o solicitado → rechazado.
 * La sede, el área y el responsable del bien solo cambian al aprobar.
 */
class GestionMovimientos
{
    public function __construct(private readonly Notificador $notificador) {}

    /**
     * Registra la solicitud. Si quien la registra puede aprobar movimientos, se
     * aprueba en el mismo acto, salvo que venga de una toma de inventario (esas
     * quedan siempre como solicitud para revisarlas).
     *
     * @param  array<string, mixed>  $datos
     */
    public function solicitar(Usuario $solicitante, array $datos): Movimiento
    {
        return DB::transaction(function () use ($solicitante, $datos) {
            $bien = Bien::lockForUpdate()->findOrFail((int) $datos['bien_id']);

            if ($bien->estaDadoDeBaja()) {
                throw ValidationException::withMessages(['bien_id' => 'No se puede mover un bien dado de baja.']);
            }

            $tieneSolicitudPendiente = $bien->movimientos()->where('estado', 'solicitado')->exists();

            if ($tieneSolicitudPendiente) {
                throw ValidationException::withMessages(['bien_id' => 'Este bien ya tiene un movimiento pendiente de aprobación.']);
            }

            $movimiento = Movimiento::create([
                ...$datos,
                'estado' => 'solicitado',
                'fecha_movimiento' => now(),
                'usuario_id' => $solicitante->id,
                ...$this->ubicacionActual($bien),
            ]);

            if ($solicitante->can('movimientos.aprobar') && empty($datos['inventario_id'])) {
                $this->aprobar($movimiento, $solicitante);
            } else {
                $this->notificador->solicitudRegistrada($movimiento);
            }

            return $movimiento;
        });
    }

    /**
     * Aprueba la solicitud y aplica el cambio de sede, área y responsable al bien.
     */
    public function aprobar(Movimiento $movimiento, Usuario $aprobador): Movimiento
    {
        return DB::transaction(function () use ($movimiento, $aprobador) {
            $movimiento = Movimiento::lockForUpdate()->findOrFail($movimiento->id);
            $this->exigirEstado($movimiento, 'solicitado');

            $bien = Bien::lockForUpdate()->findOrFail($movimiento->bien_id);

            $movimiento->update([
                'estado' => 'aprobado',
                'aprobado_por' => $aprobador->id,
                'fecha_resolucion' => now(),
                // El bien pudo cambiar desde que se solicitó: el origen se toma al aprobar.
                ...$this->ubicacionActual($bien),
            ]);

            $bien->update([
                'sede_id' => $movimiento->sede_destino_id,
                'area_id' => $movimiento->area_destino_id,
                'responsable_id' => $movimiento->responsable_nuevo_id,
            ]);
            $this->notificador->solicitudResuelta($movimiento, $aprobador);

            return $movimiento;
        });
    }

    public function rechazar(Movimiento $movimiento, Usuario $aprobador, string $motivo): Movimiento
    {
        return DB::transaction(function () use ($movimiento, $aprobador, $motivo) {
            $movimiento = Movimiento::lockForUpdate()->findOrFail($movimiento->id);
            $this->exigirEstado($movimiento, 'solicitado');

            $movimiento->update([
                'estado' => 'rechazado',
                'motivo_rechazo' => $motivo,
                'aprobado_por' => $aprobador->id,
                'fecha_resolucion' => now(),
            ]);
            $this->notificador->solicitudResuelta($movimiento, $aprobador);

            return $movimiento;
        });
    }

    /**
     * Confirma que el traslado físico ya se realizó.
     */
    public function marcarEjecutado(Movimiento $movimiento): Movimiento
    {
        return DB::transaction(function () use ($movimiento) {
            $movimiento = Movimiento::lockForUpdate()->findOrFail($movimiento->id);
            $this->exigirEstado($movimiento, 'aprobado');

            $movimiento->update(['estado' => 'ejecutado']);

            return $movimiento;
        });
    }

    /**
     * @return array{sede_origen_id: int|null, area_origen_id: int|null, responsable_anterior_id: int|null}
     */
    private function ubicacionActual(Bien $bien): array
    {
        return [
            'sede_origen_id' => $bien->sede_id,
            'area_origen_id' => $bien->area_id,
            'responsable_anterior_id' => $bien->responsable_id,
        ];
    }

    private function exigirEstado(Movimiento $movimiento, string $estado): void
    {
        if ($movimiento->estado !== $estado) {
            throw ValidationException::withMessages([
                'movimiento' => "El movimiento está en estado '{$movimiento->estado}' y no admite esta acción.",
            ]);
        }
    }
}
