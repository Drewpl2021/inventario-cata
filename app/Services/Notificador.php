<?php

namespace App\Services;

use App\Models\Baja;
use App\Models\Movimiento;
use App\Models\Requerimiento;
use App\Models\Usuario;
use App\Notifications\SolicitudRegistrada;
use App\Notifications\SolicitudResuelta;
use App\Support\EstadoSolicitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Notificaciones de la campana:
 * - Quien puede aprobar recibe las solicitudes nuevas.
 * - El solicitante recibe la resolución de las suyas.
 * Se envían al confirmarse la transacción, para no avisar de algo que se revirtió.
 */
class Notificador
{
    public function solicitudRegistrada(Baja|Movimiento|Requerimiento $solicitud): void
    {
        DB::afterCommit(function () use ($solicitud) {
            $solicitud->loadMissing(['usuario', ...($solicitud instanceof Requerimiento ? [] : ['bien'])]);
            $modulo = $this->modulo($solicitud);

            $destinatarios = Usuario::with('rol')
                ->where('estado', 'activo')
                ->whereKeyNot($solicitud->usuario_id)
                ->get()
                ->filter(fn (Usuario $u) => $u->can("{$modulo}.aprobar"));

            Notification::send($destinatarios, new SolicitudRegistrada([
                'titulo' => 'Nueva solicitud de '.$this->nombre($solicitud),
                'mensaje' => trim(($solicitud->usuario->nombre_completo ?? 'Un usuario').' · '.$this->descripcion($solicitud)),
                'url' => route('aprobaciones.index', ['pestana' => $modulo]),
                'icono' => $this->icono($solicitud),
            ]));
        });
    }

    public function solicitudResuelta(Baja|Movimiento|Requerimiento $solicitud, Usuario $resolutor): void
    {
        DB::afterCommit(function () use ($solicitud, $resolutor) {
            $solicitud->loadMissing(['usuario', ...($solicitud instanceof Requerimiento ? [] : ['bien'])]);
            $solicitante = $solicitud->usuario;

            if (! $solicitante || $solicitante->id === $resolutor->id || ! $solicitante->estaActivo()) {
                return;
            }

            $estado = EstadoSolicitud::de($solicitud);

            $solicitante->notify(new SolicitudResuelta([
                'titulo' => 'Su solicitud de '.$this->nombre($solicitud).': '.$estado['etiqueta'],
                'mensaje' => $this->descripcion($solicitud).($solicitud->motivo_rechazo && $estado['clave'] === 'rechazada' ? ' · Motivo: '.$solicitud->motivo_rechazo : ''),
                'url' => route('solicitudes.index', ['pestana' => $this->modulo($solicitud)]),
                'icono' => $this->icono($solicitud),
                'estado' => $estado['clave'],
            ]));
        });
    }

    private function modulo(Baja|Movimiento|Requerimiento $solicitud): string
    {
        return match (true) {
            $solicitud instanceof Baja => 'bajas',
            $solicitud instanceof Movimiento => 'movimientos',
            default => 'requerimientos',
        };
    }

    private function nombre(Baja|Movimiento|Requerimiento $solicitud): string
    {
        return match (true) {
            $solicitud instanceof Baja => 'baja',
            $solicitud instanceof Movimiento => 'movimiento',
            default => 'requerimiento',
        };
    }

    private function icono(Baja|Movimiento|Requerimiento $solicitud): string
    {
        return match (true) {
            $solicitud instanceof Baja => 'fa-ban',
            $solicitud instanceof Movimiento => 'fa-right-left',
            default => 'fa-clipboard-list',
        };
    }

    private function descripcion(Baja|Movimiento|Requerimiento $solicitud): string
    {
        return $solicitud instanceof Requerimiento
            ? "{$solicitud->codigo} · {$solicitud->tipo_requerimiento}"
            : ($solicitud->bien->codigo_barras ?? '').' · '.($solicitud->bien->nombre ?? '');
    }
}
