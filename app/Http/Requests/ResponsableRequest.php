<?php

namespace App\Http\Requests;

use App\Models\Responsable;
use App\Rules\AreaPerteneceASede;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResponsableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $responsable = $this->route('responsable');

        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'dni' => ['nullable', 'string', 'max:20', Rule::unique('responsables')->ignore($responsable instanceof Responsable ? $responsable : null)],
            'cargo' => ['nullable', 'string', 'max:100'],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'area_id' => ['nullable', 'exists:areas,id', new AreaPerteneceASede($this->input('sede_id'))],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'estado' => ['nullable', 'in:activo,inactivo'],
        ];
    }
}
