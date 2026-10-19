<?php

namespace App\Reportes;

use App\Models\Bien;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de los reportes del inventario de bienes (general, por sede, por
 * categoría, por responsable y por ubicación). Los dados de baja se excluyen
 * salvo que se filtre por ese estado.
 */
abstract class ReporteDeBienes extends Reporte
{
    public function filtros(): array
    {
        return ['sede_id', 'area_id', 'categoria_id', 'estado', 'desde', 'hasta'];
    }

    public function estados(): array
    {
        return Bien::ESTADOS;
    }

    public function columnas(): array
    {
        return [
            'codigo' => ['Código', 'texto'],
            'patrimonial' => ['Cód. patrimonial', 'texto'],
            'nombre' => ['Bien', 'texto'],
            'categoria' => ['Categoría', 'texto'],
            'sede' => ['Sede', 'texto'],
            'area' => ['Área', 'texto'],
            'responsable' => ['Responsable', 'texto'],
            'estado' => ['Estado', 'texto'],
            'fecha_adquisicion' => ['Adquisición', 'fecha'],
            'valor' => ['Valor (S/)', 'moneda'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Bien>
     */
    protected function consulta(array $filtros): Builder
    {
        return Bien::query()
            ->with(['categoria' => fn ($q) => $q->withTrashed(), 'sede' => fn ($q) => $q->withTrashed(), 'area' => fn ($q) => $q->withTrashed(), 'responsable' => fn ($q) => $q->withTrashed()])
            ->when($filtros['sede_id'] ?? null, fn (Builder $q, $v) => $q->where('sede_id', $v))
            ->when($filtros['area_id'] ?? null, fn (Builder $q, $v) => $q->where('area_id', $v))
            ->when($filtros['categoria_id'] ?? null, fn (Builder $q, $v) => $q->where('categoria_id', $v))
            ->when($filtros['responsable_id'] ?? null, fn (Builder $q, $v) => $q->where('responsable_id', $v))
            ->when(
                $filtros['estado'] ?? null,
                fn (Builder $q, $v) => $q->where('estado', $v),
                fn (Builder $q) => $q->where('estado', '!=', 'dado_de_baja'),
            )
            ->when($this->desde($filtros), fn (Builder $q, $v) => $q->where('fecha_adquisicion', '>=', $v->toDateString()))
            ->when($this->hasta($filtros), fn (Builder $q, $v) => $q->where('fecha_adquisicion', '<=', $v->toDateString()))
            ->orderBy('codigo_barras');
    }

    protected function fila(Model $registro): array
    {
        /** @var Bien $registro */
        return [
            'codigo' => $registro->codigo_barras,
            'patrimonial' => $registro->codigo_patrimonial,
            'nombre' => $registro->nombre,
            'categoria' => $registro->categoria?->nombre,
            'sede' => $registro->sede?->nombre,
            'area' => $registro->area?->nombre,
            'responsable' => $registro->responsable?->nombre_completo,
            'estado' => Bien::ESTADOS[$registro->estado],
            'fecha_adquisicion' => $registro->fecha_adquisicion,
            'valor' => $registro->valor_adquisicion !== null ? (float) $registro->valor_adquisicion : null,
        ];
    }
}
