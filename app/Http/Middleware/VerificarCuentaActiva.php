<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * En cada petición autenticada:
 * - Cierra la sesión si la cuenta o su rol fueron desactivados.
 * - Obliga a cambiar la contraseña temporal antes de usar el sistema.
 */
class VerificarCuentaActiva
{
    /** Rutas disponibles mientras la contraseña temporal no se ha cambiado. */
    private const RUTAS_PERMITIDAS = ['password.cambiar', 'password.cambiar.update', 'sesion.renovar', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            return $next($request);
        }

        if (! $usuario->estaActivo() || ! $usuario->rol?->estaActivo()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => 'Su cuenta fue desactivada. Comuníquese con el Administrador.',
            ]);
        }

        if ($usuario->debe_cambiar_password && ! $request->routeIs(...self::RUTAS_PERMITIDAS)) {
            return redirect()->route('password.cambiar')
                ->with('warning', 'Por seguridad, debe cambiar su contraseña temporal antes de continuar.');
        }

        return $next($request);
    }
}
