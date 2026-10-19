<?php

namespace App\Http\Requests;

use App\Models\Bien;
use App\Rules\ArchivoPermitido;
use App\Rules\AreaPerteneceASede;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de bienes. La autorización la resuelve BienPolicy en el controlador.
 */
class BienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->bienActual()) {
            return;
        }

        if (! $this->filled('codigo_interno')) {
            $this->merge(['codigo_interno' => Bien::siguienteCodigoInterno()]);
        }

    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bien = $this->bienActual();

        // El estado 'dado_de_baja' solo se asigna al aprobar una baja.
        $estadosPermitidos = array_keys(array_diff_key(Bien::ESTADOS, ['dado_de_baja' => true]));

        return [
            // Código oficial del colegio: opcional. El código de barras lo asigna el sistema.
            'codigo_patrimonial' => ['nullable', 'string', 'max:50', Rule::unique('bienes')->ignore($bien)],
            'codigo_interno' => ['nullable', 'string', 'max:50', Rule::unique('bienes')->ignore($bien)],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['required', 'exists:categorias,id'],
            'sede_id' => ['required', 'exists:sedes,id'],
            'area_id' => ['nullable', 'exists:areas,id', new AreaPerteneceASede($this->input('sede_id'))],
            'responsable_id' => ['nullable', 'exists:responsables,id'],
            'fecha_adquisicion' => ['nullable', 'date', 'before_or_equal:today'],
            'valor_adquisicion' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', Rule::in($estadosPermitidos)],
            'tipo_ingreso' => ['nullable', 'string', 'in:con_comprobante,sin_comprobante,donacion,transferencia,otro'],
            'numero_comprobante' => ['nullable', 'string', 'max:100'],
            'comprobante_archivo' => ['nullable', ArchivoPermitido::imagenOPdf()],
            'fotografia' => ['nullable', ArchivoPermitido::imagen()],
            'observaciones' => ['nullable', 'string'],
            // Sobrante de una toma de inventario que se registra como bien nuevo.
            'inventario_detalle_id' => ['nullable', Rule::exists('inventario_detalles', 'id')->where('resultado', 'sobrante_no_registrado')->whereNull('bien_id')],
        ];
    }

    private function bienActual(): ?Bien
    {
        $bien = $this->route('biene');

        return $bien instanceof Bien ? $bien : null;
    }
}
