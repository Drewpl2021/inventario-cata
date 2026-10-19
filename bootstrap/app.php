<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\EncabezadosDeSeguridad;
use App\Http\Middleware\RechazarTextoNoUtf8;
use App\Http\Middleware\VerificarCuentaActiva;
use App\Http\Middleware\VerificarPermiso;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminOnly::class,
            'permiso' => VerificarPermiso::class,
        ]);

        // Detrás de un proxy o túnel HTTPS (cloudflared, balanceador), confiar en sus
        // encabezados X-Forwarded-* para saber que la conexión original fue HTTPS.
        if (filled(env('SIBIP_PROXIES_CONFIABLES'))) {
            $middleware->trustProxies(at: env('SIBIP_PROXIES_CONFIABLES') === '*' ? '*' : explode(',', (string) env('SIBIP_PROXIES_CONFIABLES')));
        }

        $middleware->append(EncabezadosDeSeguridad::class);

        $middleware->web(append: [
            RechazarTextoNoUtf8::class,
            VerificarCuentaActiva::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
