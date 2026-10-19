<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Intentos fallidos permitidos por minuto para un mismo correo desde la misma IP. */
    public const INTENTOS_POR_MINUTO = 5;

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $loginValue = trim((string) $request->input('login', $request->input('email')));
        if ($loginValue === '') {
            return back()->withErrors(['login' => 'Debe ingresar su usuario o correo electrónico.'])->onlyInput('login');
        }

        $claveLimite = 'login:'.Str::transliterate(Str::lower($loginValue)).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($claveLimite, self::INTENTOS_POR_MINUTO)) {
            return $this->bloqueado($claveLimite, $loginValue);
        }

        // Se acepta el correo o el nombre de usuario.
        $usuario = Usuario::where('email', $loginValue)
            ->orWhere('nombre', $loginValue)
            ->first();

        $credenciales = [
            'email' => $usuario->email ?? $loginValue,
            'password' => $request->password,
            'estado' => 'activo',
        ];

        // Sin "recordarme": la sesión debe cerrarse tras el tiempo de inactividad.
        if (! Auth::attempt($credenciales)) {
            $intentos = RateLimiter::hit($claveLimite, 60);
            Auditoria::registrar('inicio_sesion_fallido', 'usuarios', $usuario?->id, null, ['login' => $loginValue]);

            return $intentos >= self::INTENTOS_POR_MINUTO
                ? $this->bloqueado($claveLimite, $loginValue)
                : back()->withErrors(['login' => __('auth.failed')])->onlyInput('login');
        }

        /** @var Usuario $autenticado */
        $autenticado = Auth::user();

        if (! $autenticado->rol?->estaActivo()) {
            Auth::logout();
            Auditoria::registrar('inicio_sesion_fallido', 'usuarios', $autenticado->id, null, ['login' => $loginValue, 'motivo' => 'rol desactivado']);

            return back()->withErrors(['login' => 'Su rol de acceso está desactivado. Comuníquese con el Administrador.'])->onlyInput('login');
        }

        RateLimiter::clear($claveLimite);
        $request->session()->regenerate();
        $autenticado->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();
        Auditoria::registrar('inicio_sesion', 'usuarios', $autenticado->id);

        if ($autenticado->debe_cambiar_password) {
            return redirect()->route('password.cambiar');
        }

        return redirect()->intended(route('dashboard'))->with('success', '¡Bienvenido al sistema SIBIP!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Sesión cerrada correctamente.');
    }

    /**
     * Mantiene viva la sesión cuando el usuario elige "Seguir conectado" en el aviso de inactividad.
     */
    public function renovarSesion(): Response
    {
        return response()->noContent();
    }

    /**
     * Demasiados intentos: mensaje con el tiempo de espera. El bloqueo queda en la
     * auditoría una sola vez mientras dure.
     */
    private function bloqueado(string $claveLimite, string $loginValue): RedirectResponse
    {
        $segundos = max(1, RateLimiter::availableIn($claveLimite));

        if (Cache::add('login-bloqueo-auditado:'.sha1($claveLimite), true, $segundos)) {
            Auditoria::registrar('inicio_sesion_bloqueado', 'usuarios', Usuario::where('email', $loginValue)->value('id'), null, [
                'login' => $loginValue,
                'segundos' => $segundos,
            ]);
        }

        $espera = $segundos >= 60 ? '1 minuto' : "{$segundos} segundos";

        return back()->withErrors([
            'login' => "Demasiados intentos fallidos. Por seguridad, espere {$espera} antes de volver a intentarlo.",
        ])->onlyInput('login');
    }
}
