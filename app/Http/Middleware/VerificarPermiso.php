<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige al menos uno de los permisos indicados.
 *
 * Uso en rutas: ->middleware('permiso:bienes.ver') o 'permiso:bajas.crear,mis_solicitudes.crear'
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $usuario = $request->user();

        foreach ($permisos as $permiso) {
            if ($usuario?->can($permiso)) {
                return $next($request);
            }
        }

        abort(403, 'No tiene permiso para acceder a esta sección.');
    }
}
