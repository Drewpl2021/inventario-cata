<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inicia_sesion_con_correo_y_contrasena(): void
    {
        $usuario = $this->usuarioCon(RolSistema::Operador, ['email' => 'operador@colegio.test']);

        $this->post(route('login.post'), ['login' => 'operador@colegio.test', 'password' => 'Password123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
        $this->assertNotNull($usuario->fresh()->ultimo_acceso_at);
    }

    public function test_rechaza_credenciales_incorrectas_con_mensaje_en_espanol(): void
    {
        $this->usuarioCon(RolSistema::Operador, ['email' => 'operador@colegio.test']);

        $this->from(route('login'))
            ->post(route('login.post'), ['login' => 'operador@colegio.test', 'password' => 'otra-clave'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_una_cuenta_inactiva_no_puede_iniciar_sesion(): void
    {
        $this->usuarioCon(RolSistema::Operador, ['email' => 'inactivo@colegio.test', 'estado' => 'inactivo']);

        $this->post(route('login.post'), ['login' => 'inactivo@colegio.test', 'password' => 'Password123'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_una_cuenta_con_rol_inactivo_no_puede_iniciar_sesion(): void
    {
        Role::delSistema(RolSistema::Auditor)->update(['estado' => 'inactivo']);
        $this->usuarioCon(RolSistema::Auditor, ['email' => 'auditor@colegio.test']);

        $this->post(route('login.post'), ['login' => 'auditor@colegio.test', 'password' => 'Password123'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_el_registro_publico_ya_no_existe(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'intruso@colegio.test'])->assertNotFound();
    }

    public function test_la_sesion_de_una_cuenta_desactivada_se_cierra_en_la_siguiente_peticion(): void
    {
        $usuario = $this->usuarioCon(RolSistema::Operador);
        $this->actingAs($usuario);
        $usuario->update(['estado' => 'inactivo']);

        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_obliga_a_cambiar_la_contrasena_temporal_antes_de_usar_el_sistema(): void
    {
        $usuario = $this->usuarioCon(RolSistema::Operador, ['debe_cambiar_password' => true]);

        $this->actingAs($usuario)->get(route('bienes.index'))->assertRedirect(route('password.cambiar'));

        $this->put(route('password.cambiar.update'), [
            'password_actual' => 'Password123',
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($usuario->fresh()->debe_cambiar_password);
        $this->get(route('bienes.index'))->assertOk();
    }

    public function test_la_nueva_contrasena_debe_ser_distinta_y_tener_letras_y_numeros(): void
    {
        $usuario = $this->usuarioCon(RolSistema::Operador, ['debe_cambiar_password' => true]);

        $this->actingAs($usuario)->put(route('password.cambiar.update'), [
            'password_actual' => 'Password123',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertSessionHasErrors(['password' => 'La nueva contraseña debe ser distinta de la actual (o de la temporal que le entregaron).']);

        $this->put(route('password.cambiar.update'), [
            'password_actual' => 'Password123',
            'password' => 'solo-letras',
            'password_confirmation' => 'solo-letras',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($usuario->fresh()->debe_cambiar_password);
    }
}
