<?php

use App\Jobs\LatidoDeCola;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| Requieren el cron del servidor que ejecuta cada minuto:
|   php artisan schedule:run
| (ver DEPLOY.md).
|
*/

// Latido del cron: sibip:verificar-produccion comprueba que esté al día.
Schedule::call(fn () => Cache::forever('sibip:latido-cron', now()->timestamp))
    ->name('sibip:latido-cron')
    ->everyMinute();

// Latido de la cola: si el worker procesa, actualiza su propia marca de hora.
Schedule::job(new LatidoDeCola)->name('sibip:latido-cola')->everyFiveMinutes();

// Respaldo diario de la base y de los archivos subidos.
Schedule::command('sibip:respaldar')
    ->dailyAt(config('sibip.respaldos.hora'))
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();
