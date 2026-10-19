<?php

namespace App\Exports;

use App\Models\Inventario;
use App\Models\InventarioDetalle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Resultado de una toma de inventario: una fila por lectura vigente y por faltante.
 * Los reportes completos con encabezado institucional llegan en la Fase 5.
 */
class ResultadoInventarioExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** Orden de presentación de las clasificaciones. */
    private const ORDEN = ['faltante', 'fuera_de_ubicacion', 'sobrante_no_registrado', 'dado_de_baja', 'encontrado'];

    public function __construct(private readonly Inventario $inventario) {}

    public function title(): string
    {
        return $this->inventario->codigo;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Resultado', 'Código', 'Bien', 'Ubicación registrada', 'Área donde se contó', 'Estado físico', 'Escaneado por', 'Fecha y hora'];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return $this->filas();
    }

    /**
     * @return list<list<string>>
     */
    public function filas(): array
    {
        return array_values($this->inventario->lecturas()
            ->with(['bien.sede', 'bien.area', 'areaEscaneo', 'escaneadoPor'])
            ->get()
            ->sortBy(fn (InventarioDetalle $d) => [array_search($d->resultado, self::ORDEN, true), $d->codigo_leido])
            ->map(fn (InventarioDetalle $d) => [
                InventarioDetalle::RESULTADOS[$d->resultado]['etiqueta'],
                $d->codigo_leido,
                $d->bien->nombre ?? '—',
                $d->bien ? ($d->bien->sede->nombre ?? '').' / '.($d->bien->area->nombre ?? 'sin área') : '—',
                $d->areaEscaneo->nombre ?? '—',
                InventarioDetalle::ESTADOS_FISICOS[$d->estado_fisico] ?? '—',
                $d->escaneadoPor->nombre_completo ?? '—',
                $d->resultado === 'faltante' ? 'No escaneado' : $d->fecha_hora->format('d/m/Y H:i:s'),
            ])
            ->all());
    }
}
