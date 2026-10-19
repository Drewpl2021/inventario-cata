<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Usuario con sesión iniciada. Las rutas que lo usan exigen el middleware "auth".
     */
    protected function usuarioActual(?Request $request = null): Usuario
    {
        $usuario = ($request ?? request())->user();
        abort_unless($usuario instanceof Usuario, 401);

        return $usuario;
    }
}
