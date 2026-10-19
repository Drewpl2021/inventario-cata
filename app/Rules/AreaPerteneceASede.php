<?php

namespace App\Rules;

use App\Models\Area;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que el área elegida pertenezca a la sede indicada.
 *
 * Uso: 'area_id' => ['nullable', 'exists:areas,id', new AreaPerteneceASede($this->input('sede_id'))]
 */
class AreaPerteneceASede implements ValidationRule
{
    public function __construct(private readonly int|string|null $sedeId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (blank($this->sedeId)) {
            $fail('Seleccione primero la sede a la que pertenece el área.');

            return;
        }

        $perteneceASede = Area::whereKey($value)->where('sede_id', $this->sedeId)->exists();

        if (! $perteneceASede) {
            $fail('El área seleccionada no pertenece a la sede indicada.');
        }
    }
}
