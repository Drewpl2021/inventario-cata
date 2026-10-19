<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Base (siempre): roles, módulos, permisos y el Administrador inicial del .env.
     * Demostración (nunca en producción): sedes, bienes y una cuenta por rol.
     */
    public function run(): void
    {
        $this->call([
            RolesYPermisosSeeder::class,
            AdministradorInicialSeeder::class,
        ]);

        if (app()->environment('production')) {
            $this->command->warn('Entorno de producción: no se cargan datos de demostración.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
