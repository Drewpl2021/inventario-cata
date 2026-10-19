<?php

namespace Database\Factories;

use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sede>
 */
class SedeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Sede '.fake()->unique()->city(),
            'codigo' => 'S'.fake()->unique()->numberBetween(10, 99),
            'direccion' => fake()->streetAddress(),
            'estado' => 'activo',
        ];
    }
}
