<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso de que un reporte generado en segundo plano está listo (o falló).
 */
class ReporteListo extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $titulo,
        private readonly string $mensaje,
        private readonly string $url,
        private readonly string $icono = 'fa-file-excel',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
            'icono' => $this->icono,
            'tipo' => 'reporte',
        ];
    }
}
