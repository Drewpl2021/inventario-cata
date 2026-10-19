<?php

namespace App\Support;

use App\Models\Baja;
use App\Models\Movimiento;
use App\Models\Requerimiento;

/**
 * Traduce el estado interno de una baja, movimiento o requerimiento a los
 * cuatro estados que ve el solicitante: Pendiente, En proceso, Aprobada y Rechazada.
 */
final class EstadoSolicitud
{
    private const ESTADOS = [
        'pendiente' => ['etiqueta' => 'Pendiente', 'badge' => 'badge-ambar'],
        'en_proceso' => ['etiqueta' => 'En proceso', 'badge' => 'badge-azul'],
        'aprobada' => ['etiqueta' => 'Aprobada', 'badge' => 'badge-verde'],
        'rechazada' => ['etiqueta' => 'Rechazada', 'badge' => 'badge-rojo'],
    ];

    /**
     * @return array{clave: string, etiqueta: string, badge: string}
     */
    public static function de(Baja|Movimiento|Requerimiento $solicitud): array
    {
        $clave = match ($solicitud->estado) {
            'solicitada', 'solicitado', 'pendiente' => 'pendiente',
            'en_cotizacion' => 'en_proceso',
            'aprobada', 'aprobado', 'ejecutado', 'atendido' => 'aprobada',
            'rechazada', 'rechazado' => 'rechazada',
        };

        return ['clave' => $clave, ...self::ESTADOS[$clave]];
    }
}
