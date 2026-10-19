<?php

namespace App\Reportes;

use App\Models\Movimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Movimientos de bienes en un rango de fechas, por sede (origen o destino) y estado.
 */
class HistorialMovimientos extends Reporte
{
    public const ESTADOS = [
        'solicitado' => 'Solicitado',
        'aprobado' => 'Aprobado',
        'ejecutado' => 'Ejecutado',
        'rechazado' => 'Rechazado',
    ];

    public function clave(): string
    {
        return 'historial-movimientos';
    }

    public function titulo(): string
    {
        return 'Historial de movimientos';
    }

    public function descripcion(): string
    {
        return 'Traslados y reubicaciones de bienes por rango de fechas.';
    }

    public function icono(): string
    {
        return 'fa-right-left';
    }

    public function filtros(): array
    {
        return ['desde', 'hasta', 'sede_id', 'estado'];
    }

    public function estados(): array
    {
        return self::ESTADOS;
    }

    public function columnaValor(): ?string
    {
        return null;
    }

    public function unidad(): string
    {
        return 'movimientos';
    }

    public function columnas(): array
    {
        return [
            'fecha' => ['Fecha', 'fechahora'],
            'codigo' => ['Código', 'texto'],
            'nombre' => ['Bien', 'texto'],
            'tipo' => ['Tipo', 'texto'],
            'origen' => ['Origen', 'texto'],
            'destino' => ['Destino', 'texto'],
            'responsable' => ['Nuevo responsable', 'texto'],
            'estado' => ['Estado', 'texto'],
            'solicitante' => ['Registrado por', 'texto'],
            'aprobador' => ['Resuelto por', 'texto'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Movimiento>
     */
    protected function consulta(array $filtros): Builder
    {
        return Movimiento::query()
            ->with(['bien' => fn ($q) => $q->withTrashed(), 'sedeOrigen', 'areaOrigen', 'sedeDestino', 'areaDestino', 'responsableNuevo', 'usuario', 'aprobador'])
            ->when($this->desde($filtros), fn (Builder $q, $v) => $q->where('fecha_movimiento', '>=', $v))
            ->when($this->hasta($filtros), fn (Builder $q, $v) => $q->where('fecha_movimiento', '<=', $v))
            ->when($filtros['sede_id'] ?? null, fn (Builder $q, $v) => $q->where(fn ($s) => $s->where('sede_origen_id', $v)->orWhere('sede_destino_id', $v)))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $v) => $q->where('estado', $v))
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id');
    }

    protected function fila(Model $registro): array
    {
        /** @var Movimiento $registro */
        $lugar = fn ($sede, $area) => trim(($sede->nombre ?? '—').($area ? ' / '.$area->nombre : ''));

        return [
            'fecha' => $registro->fecha_movimiento,
            'codigo' => $registro->bien?->codigo_barras,
            'nombre' => $registro->bien?->nombre,
            'tipo' => Movimiento::TIPOS[$registro->tipo],
            'origen' => $lugar($registro->sedeOrigen, $registro->areaOrigen),
            'destino' => $lugar($registro->sedeDestino, $registro->areaDestino),
            'responsable' => $registro->responsableNuevo?->nombre_completo,
            'estado' => self::ESTADOS[$registro->estado],
            'solicitante' => $registro->usuario?->nombre_completo,
            'aprobador' => $registro->aprobador?->nombre_completo,
        ];
    }
}
