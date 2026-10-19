<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RolRequest extends FormRequest
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
        $rol = $this->route('rol');

        return [
            'nombre' => ['required', 'string', 'max:50', Rule::unique('roles', 'nombre')->ignore($rol instanceof Role ? $rol : null)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['integer', 'exists:permisos,id'],
        ];
    }
}
