<?php

namespace Database\Factories;

use App\Enums\RolSistema;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= 'Password123',
            'rol_id' => fn () => Role::delSistema(RolSistema::Operador)->id,
            'estado' => 'activo',
            'debe_cambiar_password' => false,
        ];
    }

    /**
     * Requiere que los roles base estén sembrados (RolesYPermisosSeeder).
     */
    public function rol(RolSistema $rol): static
    {
        return $this->state(fn () => ['rol_id' => Role::delSistema($rol)->id]);
    }

    public function inactivo(): static
    {
        return $this->state(['estado' => 'inactivo']);
    }
}
