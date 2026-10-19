<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Acceso denegado: Este módulo está reservado exclusivamente para la Administración Patrimonial.');
        }

        return $next($request);
    }
}
