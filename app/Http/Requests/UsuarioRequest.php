<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\Usuario;
use App\Rules\AreaPerteneceASede;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de cuentas. La sede y el área son obligatorias cuando el rol
 * corresponde a un responsable de bienes (rol "Usuario").
 */
class UsuarioRequest extends FormRequest
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
        $usuario = $this->route('usuario');
        $requiereUbicacion = $this->rolElegido()?->requiereResponsable() ?? false;

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'email')->ignore($usuario instanceof Usuario ? $usuario : null)],
            'rol_id' => ['required', Rule::exists('roles', 'id')->where('estado', 'activo')],
            'sede_id' => [Rule::requiredIf($requiereUbicacion), 'nullable', 'exists:sedes,id'],
            'area_id' => [Rule::requiredIf($requiereUbicacion), 'nullable', 'exists:areas,id', new AreaPerteneceASede($this->input('sede_id'))],
            'estado' => ['required', 'in:activo,inactivo'],
            'responsable_existente_id' => ['nullable', 'exists:responsables,id'],
            // Datos del responsable vinculado (rol Usuario).
            'dni' => ['nullable', 'string', 'max:20', Rule::unique('responsables', 'dni')->ignore($this->responsableVinculadoId())],
            'cargo' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sede_id.required' => 'La sede es obligatoria para las cuentas de responsables de bienes.',
            'area_id.required' => 'El área es obligatoria para las cuentas de responsables de bienes.',
            'rol_id.exists' => 'El rol seleccionado no existe o está inactivo.',
        ];
    }

    /**
     * Responsable cuyo DNI no debe contar como repetido: el ya vinculado a la
     * cuenta o el responsable existente que se está eligiendo.
     */
    private function responsableVinculadoId(): ?int
    {
        if ($this->filled('responsable_existente_id')) {
            return (int) $this->input('responsable_existente_id');
        }

        $usuario = $this->route('usuario');

        return $usuario instanceof Usuario ? $usuario->responsable?->id : null;
    }

    private function rolElegido(): ?Role
    {
        return $this->filled('rol_id') ? Role::find($this->integer('rol_id')) : null;
    }
}
