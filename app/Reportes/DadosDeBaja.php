<?php

namespace App\Reportes;

use App\Models\Baja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Bienes con baja aprobada, filtrables por la fecha de la baja.
 */
class DadosDeBaja extends Reporte
{
    public const MOTIVOS = [
        'deterioro' => 'Deterioro',
        'obsolescencia' => 'Obsolescencia',
        'perdida' => 'Pérdida',
        'robo' => 'Robo',
        'sin_reparacion' => 'Sin reparación',
        'otro' => 'Otro',
    ];

    public function clave(): string
    {
        return 'dados-de-baja';
    }

    public function titulo(): string
    {
        return 'Bienes dados de baja';
    }

    public function descripcion(): string
    {
        return 'Historial de bienes dados de baja.';
    }

    public function icono(): string
    {
        return 'fa-user-xmark';
    }

    public function filtros(): array
    {
        return ['sede_id', 'categoria_id', 'estado', 'desde', 'hasta'];
    }

    /** En este reporte el filtro "estado" es el motivo de la baja. */
    public function estados(): array
    {
        return self::MOTIVOS;
    }

    public function columnas(): array
    {
        return [
            'codigo' => ['Código', 'texto'],
            'nombre' => ['Bien', 'texto'],
            'categoria' => ['Categoría', 'texto'],
            'sede' => ['Sede', 'texto'],
            'motivo' => ['Motivo', 'texto'],
            'fecha_baja' => ['Fecha de baja', 'fecha'],
            'documento' => ['Documento', 'texto'],
            'aprobado_por' => ['Aprobado por', 'texto'],
            'valor' => ['Valor de adquisición (S/)', 'moneda'],
            'valor_residual' => ['Valor residual (S/)', 'moneda'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Baja>
     */
    protected function consulta(array $filtros): Builder
    {
        return Baja::query()
            ->with(['bien' => fn ($q) => $q->withTrashed()->with(['categoria' => fn ($c) => $c->withTrashed(), 'sede' => fn ($s) => $s->withTrashed()]), 'aprobador'])
            ->where('estado', 'aprobada')
            ->when($filtros['sede_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('bien', fn ($b) => $b->withTrashed()->where('sede_id', $v)))
            ->when($filtros['categoria_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('bien', fn ($b) => $b->withTrashed()->where('categoria_id', $v)))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $v) => $q->where('motivo', $v))
            ->when($this->desde($filtros), fn (Builder $q, $v) => $q->where('fecha_baja', '>=', $v->toDateString()))
            ->when($this->hasta($filtros), fn (Builder $q, $v) => $q->where('fecha_baja', '<=', $v->toDateString()))
            ->orderByDesc('fecha_baja')
            ->orderByDesc('id');
    }

    protected function fila(Model $registro): array
    {
        /** @var Baja $registro */
        return [
            'codigo' => $registro->bien?->codigo_barras,
            'nombre' => $registro->bien?->nombre,
            'categoria' => $registro->bien?->categoria?->nombre,
            'sede' => $registro->bien?->sede?->nombre,
            'motivo' => self::MOTIVOS[$registro->motivo],
            'fecha_baja' => $registro->fecha_baja,
            'documento' => $registro->documento_referencia,
            'aprobado_por' => $registro->aprobador?->nombre_completo,
            'valor' => $registro->bien?->valor_adquisicion !== null ? (float) $registro->bien->valor_adquisicion : null,
            'valor_residual' => (float) $registro->valor_residual,
        ];
    }
}
