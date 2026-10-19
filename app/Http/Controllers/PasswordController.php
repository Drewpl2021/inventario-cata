<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarPasswordRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cambio de contraseña del propio usuario. Es obligatorio cuando la cuenta
 * tiene una contraseña temporal (creada o restablecida por el Administrador).
 */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.cambiar-password', [
            'obligatorio' => (bool) $this->usuarioActual($request)->debe_cambiar_password,
        ]);
    }

    public function update(CambiarPasswordRequest $request): RedirectResponse
    {
        $usuario = $this->usuarioActual($request);

        $usuario->update([
            'password' => $request->validated('password'),
            'debe_cambiar_password' => false,
        ]);

        // Cierra las demás sesiones abiertas con la contraseña anterior.
        $request->session()->regenerate();
        $usuario->cerrarSesiones(exceptoSesionId: $request->session()->getId());

        return redirect()->route('dashboard')->with('success', 'Contraseña actualizada correctamente.');
    }
}
