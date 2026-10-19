<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_raiz_envia_al_visitante_al_login_sin_mostrar_datos(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_la_raiz_envia_al_usuario_autenticado_al_panel(): void
    {
        $this->actingAs($this->usuarioCon(RolSistema::Operador))
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}
