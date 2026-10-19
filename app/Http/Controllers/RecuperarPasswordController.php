<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as ReglaPassword;

/**
 * Recuperación de contraseña por correo: enlace de un solo uso que vence
 * a los 60 minutos. Solo para cuentas activas.
 */
class RecuperarPasswordController extends Controller
{
    /** Mismo mensaje exista o no el correo: no revela qué cuentas existen. */
    private const MENSAJE_ENVIADO = 'Si el correo pertenece a una cuenta activa, le enviamos un enlace para restablecer la contraseña. Revise también la carpeta de correo no deseado.';

    public function create(): View
    {
        return view('auth.recuperar-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:150']]);

        Password::sendResetLink(['email' => $request->input('email'), 'estado' => 'activo']);

        return back()->with('success', self::MENSAJE_ENVIADO);
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.restablecer-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', ReglaPassword::defaults()],
        ]);

        $estado = Password::reset(
            [...$request->only('email', 'password', 'password_confirmation', 'token'), 'estado' => 'activo'],
            function (Usuario $usuario, string $password) {
                $usuario->forceFill(['password' => $password, 'debe_cambiar_password' => false])->save();
                $usuario->cerrarSesiones();

                Auditoria::registrar('restablecer_password', 'usuarios', $usuario->id, null, ['medio' => 'correo']);
                event(new PasswordReset($usuario));
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __('passwords.token')])->onlyInput('email');
        }

        return redirect()->route('login')->with('success', 'Su contraseña fue restablecida. Ya puede iniciar sesión con la nueva contraseña.');
    }
}
