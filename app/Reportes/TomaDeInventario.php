<?php

namespace App\Reportes;

use App\Models\InventarioDetalle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resultado de una campaña de toma de inventario, agrupado por clasificación.
 * El valor sumado es el de adquisición de los bienes (sirve para valorizar faltantes).
 */
class TomaDeInventario extends Reporte
{
    private const ORDEN = ['faltante', 'fuera_de_ubicacion', 'sobrante_no_registrado', 'dado_de_baja', 'encontrado'];

    public function clave(): string
    {
        return 'toma-de-inventario';
    }

    public function titulo(): string
    {
        return 'Resultado de toma de inventario';
    }

    public function descripcion(): string
    {
        return 'Encontrados, faltantes, fuera de ubicación y sobrantes de cada campaña.';
    }

    public function icono(): string
    {
        return 'fa-barcode';
    }

    public function filtros(): array
    {
        return ['inventario_id'];
    }

    /** @return list<string> */
    public function filtrosRequeridos(): array
    {
        return ['inventario_id'];
    }

    public function unidad(): string
    {
        return 'registros';
    }

    public function tituloGrupo(): ?string
    {
        return 'Resultado';
    }

    public function columnas(): array
    {
        return [
            'codigo' => ['Código leído', 'texto'],
            'nombre' => ['Bien', 'texto'],
            'ubicacion' => ['Ubicación registrada', 'texto'],
            'contado_en' => ['Contado en', 'texto'],
            'estado_fisico' => ['Estado físico', 'texto'],
            'escaneado_por' => ['Escaneado por', 'texto'],
            'fecha_hora' => ['Fecha y hora', 'fechahora'],
            'valor' => ['Valor (S/)', 'moneda'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<InventarioDetalle>
     */
    protected function consulta(array $filtros): Builder
    {
        return InventarioDetalle::query()
            ->with(['bien.sede', 'bien.area', 'areaEscaneo', 'escaneadoPor'])
            ->where('inventario_id', $filtros['inventario_id'] ?? 0)
            ->whereNull('anulado_at')
            ->orderBy('codigo_leido');
    }

    protected function fila(Model $registro): array
    {
        /** @var InventarioDetalle $registro */
        $bien = $registro->resultado === 'sobrante_no_registrado' ? null : $registro->bien;

        return [
            'codigo' => $registro->codigo_leido,
            'nombre' => $bien?->nombre,
            'ubicacion' => $bien ? ($bien->sede->nombre ?? '').' / '.($bien->area->nombre ?? 'sin área') : null,
            'contado_en' => $registro->resultado === 'faltante' ? 'No escaneado' : ($registro->areaEscaneo->nombre ?? 'Sede, sin área'),
            'estado_fisico' => InventarioDetalle::ESTADOS_FISICOS[$registro->estado_fisico] ?? null,
            'escaneado_por' => $registro->escaneadoPor?->nombre_completo,
            'fecha_hora' => $registro->resultado === 'faltante' ? null : $registro->fecha_hora,
            'valor' => $bien?->valor_adquisicion !== null ? (float) $bien->valor_adquisicion : null,
        ];
    }

    /** "1. Faltante", "2. Fuera de ubicación"... para que el orden de los grupos sea el útil. */
    protected function grupo(Model $registro): ?string
    {
        /** @var InventarioDetalle $registro */
        $posicion = array_search($registro->resultado, self::ORDEN, true);

        return ($posicion + 1).'. '.InventarioDetalle::RESULTADOS[$registro->resultado]['etiqueta'];
    }
}
