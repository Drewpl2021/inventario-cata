<?php

namespace App\Reportes;

use App\Models\Area;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Responsable;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Un reporte: filtros admitidos, columnas, consulta y forma de cada fila.
 *
 * Las filas se devuelven con valores "tipados" (fechas Carbon y montos float)
 * para que el PDF y el Excel les den el formato correcto. Si el reporte agrupa
 * (por sede, categoría, responsable...), cada fila trae la clave `_grupo`.
 */
abstract class Reporte
{
    /** Tipos de columna: texto, fecha, fechahora, moneda, numero. */
    public const FILTROS = [
        'sede_id' => 'Sede',
        'area_id' => 'Área',
        'categoria_id' => 'Categoría',
        'estado' => 'Estado',
        'responsable_id' => 'Responsable',
        'inventario_id' => 'Campaña',
        'desde' => 'Desde',
        'hasta' => 'Hasta',
    ];

    abstract public function clave(): string;

    abstract public function titulo(): string;

    abstract public function descripcion(): string;

    abstract public function icono(): string;

    /**
     * Filtros que admite el reporte (claves de FILTROS).
     *
     * @return list<string>
     */
    abstract public function filtros(): array;

    /**
     * clave => [etiqueta, tipo]
     *
     * @return array<string, array{0: string, 1: string}>
     */
    abstract public function columnas(): array;

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<covariant Model>
     */
    abstract protected function consulta(array $filtros): Builder;

    /**
     * @return array<string, mixed>
     */
    abstract protected function fila(Model $registro): array;

    /** Etiqueta del grupo al que pertenece el registro (null si el reporte no agrupa). */
    protected function grupo(Model $registro): ?string
    {
        return null;
    }

    /** Título de la columna de agrupación en el Excel. */
    public function tituloGrupo(): ?string
    {
        return null;
    }

    /** Columna que se suma en los totales (null si no hay montos). */
    public function columnaValor(): ?string
    {
        return 'valor';
    }

    /** Qué se cuenta: "bienes", "movimientos", "lecturas"... */
    public function unidad(): string
    {
        return 'bienes';
    }

    /** Filtros obligatorios. */
    /** @return list<string> */
    public function filtrosRequeridos(): array
    {
        return [];
    }

    public function vistaPdf(): string
    {
        return 'reportes.pdf.tabla';
    }

    /**
     * Reglas de validación de los filtros admitidos.
     *
     * @return array<string, mixed>
     */
    public function reglas(): array
    {
        $reglas = [
            'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'estado' => ['nullable', 'string', 'max:30'],
            'responsable_id' => ['nullable', 'integer', 'exists:responsables,id'],
            'inventario_id' => ['nullable', 'integer', 'exists:inventarios,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ];

        $reglas = array_intersect_key($reglas, array_flip($this->filtros()));
        foreach ($this->filtrosRequeridos() as $requerido) {
            $reglas[$requerido][0] = 'required';
        }

        return $reglas;
    }

    /**
     * Opciones para el filtro "estado" (vacío si el reporte no lo usa).
     *
     * @return array<string, string>
     */
    public function estados(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function contar(array $filtros): int
    {
        return $this->consulta($filtros)->count();
    }

    /**
     * Filas del reporte, ya ordenadas (y agrupadas si corresponde).
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, non-empty-array<string, mixed>>
     */
    public function filas(array $filtros): Collection
    {
        return $this->consulta($filtros)->get()
            ->map(fn (Model $registro) => [...$this->fila($registro), '_grupo' => $this->grupo($registro)])
            ->when($this->tituloGrupo() !== null, fn (Collection $filas) => $filas->sortBy('_grupo', SORT_NATURAL | SORT_FLAG_CASE)->values());
    }

    /**
     * Total de registros, suma de valores y subtotales por grupo.
     *
     * @param  Collection<int, non-empty-array<string, mixed>>  $filas
     * @return array{cantidad: int, valor: float|null, grupos: array<string, array{cantidad: int, valor: float|null}>}
     */
    public function totales(Collection $filas): array
    {
        $columna = $this->columnaValor();
        $suma = fn (Collection $c) => $columna ? round((float) $c->sum(fn ($f) => (float) ($f[$columna] ?? 0)), 2) : null;

        return [
            'cantidad' => $filas->count(),
            'valor' => $suma($filas),
            'grupos' => $this->tituloGrupo() === null ? [] : $filas->groupBy('_grupo')
                ->map(fn (Collection $g) => ['cantidad' => $g->count(), 'valor' => $suma($g)])
                ->all(),
        ];
    }

    /**
     * Filtros aplicados en texto legible, para el encabezado del PDF y del Excel.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, string>
     */
    public function filtrosLegibles(array $filtros): array
    {
        $legibles = [];
        foreach (array_intersect_key(array_filter($filtros, fn ($v) => filled($v)), self::FILTROS) as $clave => $valor) {
            $legibles[self::FILTROS[$clave]] = match ($clave) {
                'sede_id' => Sede::withTrashed()->find((int) $valor)?->nombre,
                'area_id' => Area::withTrashed()->find((int) $valor)?->nombre,
                'categoria_id' => Categoria::withTrashed()->find((int) $valor)?->nombre,
                'responsable_id' => Responsable::withTrashed()->find((int) $valor)?->nombre_completo,
                'inventario_id' => ($i = Inventario::find((int) $valor)) ? "{$i->codigo} · {$i->nombre}" : null,
                'estado' => $this->estados()[$valor] ?? $valor,
                'desde', 'hasta' => Carbon::parse($valor)->format('d/m/Y'),
            } ?? (string) $valor;
        }

        return $legibles;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    protected function desde(array $filtros): ?Carbon
    {
        return filled($filtros['desde'] ?? null) ? Carbon::parse($filtros['desde'])->startOfDay() : null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    protected function hasta(array $filtros): ?Carbon
    {
        return filled($filtros['hasta'] ?? null) ? Carbon::parse($filtros['hasta'])->endOfDay() : null;
    }
}
