<?php

namespace Tests;

use App\Enums\RolSistema;
use App\Models\Usuario;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Los roles base y sus permisos se siembran en cada prueba con base de datos. */
    protected bool $seed = true;

    protected string $seeder = RolesYPermisosSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Las pruebas no necesitan los assets compilados por Vite.
        $this->withoutVite();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function usuarioCon(RolSistema $rol, array $atributos = []): Usuario
    {
        return Usuario::factory()->rol($rol)->create($atributos);
    }
}
