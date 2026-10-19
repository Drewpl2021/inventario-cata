<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Bien;
use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bien>
 */
class BienFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo_patrimonial' => fake()->unique()->numerify('740805######'),
            'codigo_interno' => fake()->unique()->numerify('#####'),
            'nombre' => fake()->randomElement(['Laptop', 'Proyector', 'Escritorio', 'Silla', 'Impresora']),
            'categoria_id' => Categoria::factory(),
            'area_id' => Area::factory(),
            'sede_id' => fn (array $atributos) => Area::findOrFail((int) $atributos['area_id'])->sede_id,
            'estado' => 'bueno',
            'tipo_ingreso' => 'con_comprobante',
        ];
    }

    public function dadoDeBaja(): static
    {
        return $this->state(['estado' => 'dado_de_baja']);
    }
}
