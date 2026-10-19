<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notificaciones de la campana (tabla `notifications` de Laravel).
 */
class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        $notificaciones = $this->usuarioActual($request)->notifications()->paginate(20);

        return view('notificaciones.index', compact('notificaciones'));
    }

    public function leer(Request $request, string $id): JsonResponse
    {
        $this->usuarioActual($request)->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function leerTodas(Request $request): JsonResponse
    {
        $this->usuarioActual($request)->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }
}
