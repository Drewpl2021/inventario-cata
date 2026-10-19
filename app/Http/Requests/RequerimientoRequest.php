<?php

namespace App\Http\Requests;

use App\Rules\ArchivoPermitido;
use App\Rules\AreaPerteneceASede;
use Illuminate\Foundation\Http\FormRequest;

class RequerimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El formulario simplificado (rol Usuario) no pide cantidad, prioridad ni sede:
     * se usan valores por defecto y la sede/área de la cuenta.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'cantidad' => 1,
            'prioridad' => 'media',
            'sede_id' => $this->user()?->sede_id,
            'area_id' => $this->user()?->area_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_requerimiento' => ['required', 'string', 'max:100'],
            'descripcion' => ['required', 'string'],
            'justificacion' => ['required', 'string'],
            'observaciones' => ['nullable', 'string'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'costo_estimado' => ['nullable', 'numeric', 'min:0'],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'area_id' => ['nullable', 'exists:areas,id', new AreaPerteneceASede($this->input('sede_id'))],
            'prioridad' => ['required', 'in:baja,media,alta,urgente'],
            'archivo_tipo' => ['nullable', 'string', 'max:100'],
            // Adjunto validado: solo PDF o imagen, hasta 10 MB.
            'archivo_adjunto' => ['nullable', ArchivoPermitido::imagenOPdf()],
        ];
    }
}
