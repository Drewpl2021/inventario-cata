<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Auditoria;
use App\Models\Usuario;
use App\Notifications\RestablecerPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SeguridadDeAccesoTest extends TestCase
{
    use RefreshDatabase;

    private function intentarLogin(string $correo, string $clave, string $ip = '10.0.0.1'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('login'))
            ->post(route('login.post'), ['login' => $correo, 'password' => $clave]);
    }

    public function test_tras_5_intentos_fallidos_bloquea_el_correo_desde_esa_ip_con_el_tiempo_de_espera(): void
    {
        $this->freezeTime();
        $this->usuarioCon(RolSistema::Operador, ['email' => 'operador@colegio.test']);

        foreach (range(1, 4) as $intento) {
            $this->intentarLogin('operador@colegio.test', 'mala')->assertSessionHasErrors(['login' => __('auth.failed')]);
        }
        $this->intentarLogin('operador@colegio.test', 'mala')
            ->assertSessionHasErrors(['login' => 'Demasiados intentos fallidos. Por seguridad, espere 1 minuto antes de volver a intentarlo.']);
        $this->travel(20)->seconds();
        $this->intentarLogin('operador@colegio.test', 'mala')
            ->assertSessionHasErrors(['login' => 'Demasiados intentos fallidos. Por seguridad, espere 40 segundos antes de volver a intentarlo.']);

        // Ni con la contraseña correcta mientras dure el bloqueo…
        $this->intentarLogin('Operador@Colegio.test', 'Password123')->assertSessionHasErrors('login');
        $this->assertGuest();

        // …pero sí desde otra IP.
        $this->intentarLogin('operador@colegio.test', 'Password123', '10.0.0.2')->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_el_bloqueo_se_levanta_pasado_el_minuto(): void
    {
        $this->usuarioCon(RolSistema::Operador, ['email' => 'operador@colegio.test']);
        foreach (range(1, 5) as $intento) {
            $this->intentarLogin('operador@colegio.test', 'mala');
        }

        $this->travel(61)->seconds();

        $this->intentarLogin('operador@colegio.test', 'Password123')->assertRedirect(route('dashboard'));
    }

    public function test_registra_en_la_auditoria_los_inicios_correctos_fallidos_y_el_bloqueo(): void
    {
        $usuario = $this->usuarioCon(RolSistema::Operador, ['email' => 'operador@colegio.test']);

        foreach (range(1, 6) as $intento) {
            $this->intentarLogin('operador@colegio.test', 'mala');
        }
        $this->intentarLogin('operador@colegio.test', 'Password123', '10.0.0.9');

        $this->assertSame(5, Auditoria::where('accion', 'inicio_sesion_fallido')->where('registro_id', $usuario->id)->count());
        $this->assertSame(1, Auditoria::where('accion', 'inicio_sesion_bloqueado')->count(), 'El bloqueo se registra una sola vez.');
        $this->assertDatabaseHas('auditoria', ['accion' => 'inicio_sesion', 'usuario_id' => $usuario->id, 'registro_id' => $usuario->id, 'ip' => '10.0.0.9']);

        $fallido = Auditoria::where('accion', 'inicio_sesion_fallido')->firstOrFail();
        $this->assertSame(['login' => 'operador@colegio.test'], $fallido->datos_nuevos);
    }

    public function test_el_login_no_ofrece_mantener_la_sesion_iniciada(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('name="remember"', false)
            ->assertSee(route('password.request'));
    }

    public function test_envia_el_correo_de_recuperacion_en_espanol_solo_a_cuentas_activas_sin_revelar_cuales_existen(): void
    {
        Notification::fake();
        $activo = $this->usuarioCon(RolSistema::Usuario, ['email' => 'docente@colegio.test']);
        $inactivo = $this->usuarioCon(RolSistema::Usuario, ['email' => 'retirado@colegio.test', 'estado' => 'inactivo']);

        $mensajes = collect(['docente@colegio.test', 'retirado@colegio.test', 'nadie@colegio.test'])
            ->map(fn ($correo) => $this->from(route('password.request'))->post(route('password.email'), ['email' => $correo])
                ->assertRedirect(route('password.request'))
                ->getSession()->get('success'));

        $this->assertCount(1, $mensajes->unique(), 'La respuesta es la misma exista o no la cuenta.');
        Notification::assertSentTo($activo, RestablecerPassword::class, function (RestablecerPassword $aviso) use ($activo) {
            $html = (string) $aviso->toMail($activo)->render();

            return $aviso->toMail($activo)->subject === 'Restablecer su contraseña · SIBIP'
                && str_contains($html, 'Restablecer contraseña')
                && str_contains($html, 'vence en 60 minutos')
                && str_contains($html, url('restablecer-password').'/');
        });
        Notification::assertNotSentTo($inactivo, RestablecerPassword::class);
    }

    public function test_restablece_la_contrasena_con_el_enlace_cierra_sesiones_y_lo_audita(): void
    {
        config(['session.driver' => 'database']);
        $usuario = $this->usuarioCon(RolSistema::Usuario, ['email' => 'docente@colegio.test', 'debe_cambiar_password' => true]);
        DB::table('sessions')->insert(['id' => 'sesion-vieja', 'user_id' => $usuario->id, 'payload' => '', 'last_activity' => time()]);
        $token = Password::createToken($usuario);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'docente@colegio.test', 'password' => 'solo-letras', 'password_confirmation' => 'solo-letras',
        ])->assertSessionHasErrors('password');

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'docente@colegio.test', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026',
        ])->assertRedirect(route('login'));

        $usuario->refresh();
        $this->assertTrue(Hash::check('NuevaClave2026', $usuario->password));
        $this->assertFalse($usuario->debe_cambiar_password);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-vieja']);
        $this->assertDatabaseHas('auditoria', ['accion' => 'restablecer_password', 'registro_id' => $usuario->id]);

        // El enlace es de un solo uso.
        $this->from(route('password.reset', $token))->post(route('password.update'), [
            'token' => $token, 'email' => 'docente@colegio.test', 'password' => 'OtraClave2026', 'password_confirmation' => 'OtraClave2026',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);
    }

    public function test_al_cambiar_la_contrasena_se_cierran_las_demas_sesiones(): void
    {
        config(['session.driver' => 'database']);
        $usuario = $this->usuarioCon(RolSistema::Operador);
        DB::table('sessions')->insert([
            ['id' => 'celular', 'user_id' => $usuario->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'otra-cuenta', 'user_id' => $this->usuarioCon(RolSistema::Operador)->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($usuario)->put(route('password.cambiar.update'), [
            'password_actual' => 'Password123', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('sessions', ['id' => 'celular']);
        $this->assertDatabaseHas('sessions', ['id' => 'otra-cuenta']);
    }

    public function test_la_sesion_dura_120_minutos_de_inactividad_y_se_puede_renovar(): void
    {
        $usuario = $this->usuarioCon(RolSistema::Usuario);
        $this->assertSame(120, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));

        $this->actingAs($usuario)->get(route('dashboard'))
            ->assertSee('<meta name="sesion-minutos" content="120">', false);
        $this->actingAs($usuario)->post(route('sesion.renovar'))->assertNoContent();
        $this->post(route('logout'));
        $this->post(route('sesion.renovar'))->assertRedirect(route('login'));
    }

    public function test_la_pagina_de_recuperacion_no_esta_disponible_con_sesion_iniciada(): void
    {
        $this->actingAs(Usuario::factory()->rol(RolSistema::Usuario)->create())
            ->get(route('password.request'))
            ->assertRedirect();
    }
}
