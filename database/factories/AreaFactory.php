<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sede_id' => Sede::factory(),
            'nombre' => 'Área '.fake()->unique()->word(),
            'codigo' => strtoupper(fake()->unique()->bothify('A##')),
            'estado' => 'activo',
        ];
    }
}
