<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encabezados de seguridad en todas las respuestas.
 *
 * La CSP permite solo scripts propios (los de Vite y los bloques en línea con
 * el nonce de la petición); por eso las vistas no usan onclick/onchange.
 * Los estilos en línea se permiten porque Chart.js, html5-qrcode y varias
 * vistas los usan, y no ejecutan código.
 */
class EncabezadosDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $encabezados = $response->headers;
        $encabezados->set('X-Frame-Options', 'SAMEORIGIN');
        $encabezados->set('X-Content-Type-Options', 'nosniff');
        $encabezados->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Cámara solo para este sitio (escaneo de inventario); lo demás, apagado.
        $encabezados->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()');

        if (app()->isProduction() && $request->isSecure()) {
            $encabezados->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // La CSP solo tiene sentido en páginas HTML (no en PDF, imágenes o descargas).
        if (str_contains((string) $encabezados->get('Content-Type', 'text/html'), 'text/html')) {
            $encabezados->set('Content-Security-Policy', $this->politicaDeContenido($nonce, $request->isSecure()));
        }

        return $response;
    }

    private function politicaDeContenido(string $nonce, bool $esHttps): string
    {
        // Con "npm run dev" los recursos llegan del servidor de Vite (y su websocket de recarga).
        $vite = Vite::isRunningHot() ? trim((string) file_get_contents(Vite::hotFile())) : '';
        $viteWs = $vite !== '' ? preg_replace('#^http#', 'ws', $vite) : '';

        $directivas = [
            'default-src' => "'self'",
            'script-src' => "'self' 'nonce-{$nonce}' {$vite}",
            'style-src' => "'self' 'unsafe-inline' {$vite}",
            'img-src' => "'self' data: blob:",
            'font-src' => "'self' data: {$vite}",
            'connect-src' => "'self' {$vite} {$viteWs}",
            'media-src' => "'self' blob:",
            'worker-src' => "'self' blob:",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'self'",
        ];

        $politica = collect($directivas)->map(fn ($valor, $nombre) => trim("{$nombre} {$valor}"))->implode('; ');

        // Solo con HTTPS: en la red local por http:// impediría cargar los estilos y scripts.
        return app()->isProduction() && $esHttps ? "{$politica}; upgrade-insecure-requests" : $politica;
    }
}
