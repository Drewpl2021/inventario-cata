<?php

namespace Database\Seeders;

use App\Enums\RolSistema;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Crea la primera cuenta de Administrador con los datos de SIBIP_ADMIN_* del .env.
 * Si la cuenta ya existe no la modifica (no pisa una contraseña ya cambiada).
 */
class AdministradorInicialSeeder extends Seeder
{
    public function run(): void
    {
        $datos = config('sibip.admin_inicial');

        if (blank($datos['email']) || blank($datos['password'])) {
            $this->command->warn('No se creó el Administrador inicial: defina SIBIP_ADMIN_EMAIL y SIBIP_ADMIN_PASSWORD en el .env.');

            return;
        }

        if (Usuario::withTrashed()->where('email', mb_strtolower($datos['email']))->exists()) {
            $this->command->info("El Administrador inicial {$datos['email']} ya existe; no se modificó.");

            return;
        }

        Usuario::create([
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'email' => mb_strtolower($datos['email']),
            'password' => $datos['password'],
            'rol_id' => Role::delSistema(RolSistema::Administrador)->id,
            'estado' => 'activo',
            'debe_cambiar_password' => true,
        ]);

        $this->command->info("Administrador inicial creado: {$datos['email']} (deberá cambiar la contraseña en su primer ingreso).");
    }
}
