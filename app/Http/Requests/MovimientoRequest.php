<?php

namespace App\Http\Requests;

use App\Models\Bien;
use App\Rules\AreaPerteneceASede;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de movimiento. La propiedad del bien la valida BienPolicy::solicitar.
 */
class MovimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El formulario del responsable (rol Usuario) no pide tipo ni nuevo responsable:
     * el tipo se deduce (cambia de sede = traslado; si no, reubicación) y el bien
     * conserva su responsable.
     */
    protected function prepareForValidation(): void
    {
        $bien = $this->filled('bien_id') ? Bien::find($this->integer('bien_id')) : null;
        if (! $bien) {
            return;
        }

        if (! $this->has('tipo')) {
            $this->merge(['tipo' => (int) $this->input('sede_destino_id') === $bien->sede_id ? 'reubicacion' : 'traslado']);
        }

        if (! $this->has('responsable_nuevo_id')) {
            $this->merge(['responsable_nuevo_id' => $bien->responsable_id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bien_id' => ['required', 'exists:bienes,id'],
            'tipo' => ['required', 'in:traslado,asignacion,devolucion,reubicacion'],
            'sede_destino_id' => ['required', 'exists:sedes,id'],
            'area_destino_id' => ['nullable', 'exists:areas,id', new AreaPerteneceASede($this->input('sede_destino_id'))],
            'responsable_nuevo_id' => ['nullable', 'exists:responsables,id'],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'inventario_id' => ['nullable', 'exists:inventarios,id'],
        ];
    }
}
