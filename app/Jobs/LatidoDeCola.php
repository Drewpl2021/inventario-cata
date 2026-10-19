<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Trabajo mínimo que el programador encola cada 5 minutos. Si el worker de la
 * cola está en marcha, deja la hora en la caché y sibip:verificar-produccion
 * puede comprobar que la cola procesa.
 */
class LatidoDeCola implements ShouldQueue
{
    use Queueable;

    public const CLAVE = 'sibip:latido-cola';

    public function handle(): void
    {
        Cache::forever(self::CLAVE, now()->timestamp);
    }
}
