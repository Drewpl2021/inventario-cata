<?php

namespace App\Http\Requests;

use App\Rules\ArchivoPermitido;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitud de baja. La regla "una sola baja activa por bien" se valida dentro
 * de la transacción en GestionBajas, con el bien bloqueado.
 */
class BajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // En las solicitudes la fecha de reporte es la del día.
        $this->mergeIfMissing(['fecha_baja' => now()->toDateString()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bien_id' => ['required', 'exists:bienes,id'],
            'motivo' => ['required', 'in:deterioro,obsolescencia,perdida,robo,sin_reparacion,otro'],
            'fecha_baja' => ['required', 'date', 'before_or_equal:today'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'documento_referencia' => ['nullable', 'string', 'max:100'],
            'valor_residual' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'fotografia' => ['nullable', ArchivoPermitido::imagen()],
            'inventario_id' => ['nullable', 'exists:inventarios,id'],
        ];
    }
}
