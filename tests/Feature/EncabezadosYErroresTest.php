<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Http\Middleware\RechazarTextoNoUtf8;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class EncabezadosYErroresTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_paginas_llevan_los_encabezados_de_seguridad(): void
    {
        $respuesta = $this->get(route('login'))->assertOk();

        $respuesta->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()')
            ->assertHeaderMissing('Strict-Transport-Security');

        $csp = $respuesta->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-(inline|eval)'/", $csp, 'Los scripts en línea solo con nonce.');
    }

    public function test_los_scripts_en_linea_llevan_el_nonce_de_la_csp(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);

        $respuesta = $this->actingAs($admin)->get(route('bienes.create'))->assertOk();

        preg_match("/'nonce-([^']+)'/", $respuesta->headers->get('Content-Security-Policy'), $coincidencia);
        $this->assertNotEmpty($coincidencia[1] ?? null);
        $respuesta->assertSee('<script nonce="'.$coincidencia[1].'">', false);
        $this->assertDoesNotMatchRegularExpression('/<script>/', $respuesta->getContent());
        $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit|input|load)="/', $respuesta->getContent());
    }

    public function test_en_produccion_con_https_se_envia_hsts(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $respuesta = $this->get('https://localhost/login');

        $respuesta->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->assertStringEndsWith('upgrade-insecure-requests', $respuesta->headers->get('Content-Security-Policy'));
    }

    public function test_en_produccion_por_http_en_la_red_local_no_se_fuerza_https(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $respuesta = $this->get('http://192.168.1.50:8080/login');

        $respuesta->assertHeaderMissing('Strict-Transport-Security');
        $this->assertStringNotContainsString('upgrade-insecure-requests', $respuesta->headers->get('Content-Security-Policy'));
    }

    public function test_las_paginas_de_error_estan_en_espanol_y_sin_detalles_tecnicos(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_prueba-error/{codigo}', function (int $codigo) {
            if ($codigo === 500) {
                throw new RuntimeException('SQLSTATE detalle interno secreto');
            }
            abort($codigo);
        });

        $esperado = [
            403 => 'Acceso no permitido',
            404 => 'Página no encontrada',
            405 => 'No se pudo completar la solicitud',
            419 => 'La página expiró',
            429 => 'Demasiadas solicitudes',
            500 => 'Ocurrió un problema',
            503 => 'Sistema en mantenimiento',
        ];

        foreach ($esperado as $codigo => $titulo) {
            $this->get("/_prueba-error/{$codigo}")
                ->assertStatus($codigo)
                ->assertSee($titulo)
                ->assertSee('SIBIP')
                ->assertDontSee('SQLSTATE')
                ->assertDontSee('Exception')
                ->assertDontSee('vendor/laravel');
        }

        $this->get('/no-existe-esta-pagina')->assertNotFound()->assertSee('Página no encontrada');
    }

    public function test_texto_que_no_es_utf8_es_un_error_de_validacion_y_no_un_500(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);

        $this->actingAs($admin)->from(route('bienes.create'))->post(route('bienes.store'), [
            'nombre' => "Escritorio de \xE1rbol", // "á" en Latin-1
            'observaciones' => ['ok', "mal \xFF"],
        ])->assertRedirect(route('bienes.create'))
            ->assertSessionHasErrors(['nombre' => RechazarTextoNoUtf8::MENSAJE, 'observaciones.1' => RechazarTextoNoUtf8::MENSAJE]);

        $this->actingAs($admin)->getJson(route('buscar', ['q' => "silla\xFF"]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q' => RechazarTextoNoUtf8::MENSAJE]);
    }

    public function test_el_texto_utf8_valido_con_tildes_y_enie_se_acepta(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);

        $this->actingAs($admin)->getJson(route('buscar', ['q' => 'Señalización árbol']))->assertOk();
    }
}
