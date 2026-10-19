<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso a quienes aprueban: hay una solicitud nueva (baja, movimiento o requerimiento).
 */
class SolicitudRegistrada extends Notification
{
    use Queueable;

    /**
     * @param  array{titulo: string, mensaje: string, url: string, icono: string}  $datos
     */
    public function __construct(private readonly array $datos) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [...$this->datos, 'tipo' => 'solicitud'];
    }
}
