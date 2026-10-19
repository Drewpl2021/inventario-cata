<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Responsable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Responsable>
 */
class ResponsableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'dni' => fake()->unique()->numerify('########'),
            'area_id' => Area::factory(),
            'sede_id' => fn (array $atributos) => Area::findOrFail((int) $atributos['area_id'])->sede_id,
            'estado' => 'activo',
        ];
    }
}
