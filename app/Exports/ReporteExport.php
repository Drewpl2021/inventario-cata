<?php

namespace App\Exports;

use App\Reportes\Reporte;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel de un reporte: encabezado institucional (filas 1 a 4), títulos de
 * columna en negrita con filtros automáticos, anchos ajustados, montos en
 * formato S/ y fechas como fechas de Excel. Al final, la fila de totales.
 */
class ReporteExport implements FromArray, WithColumnFormatting, WithCustomStartCell, WithEvents, WithHeadings, WithTitle
{
    private const FILA_TITULOS = 6;

    private const FORMATOS = [
        'moneda' => '"S/" #,##0.00',
        'fecha' => 'dd/mm/yyyy',
        'fechahora' => 'dd/mm/yyyy hh:mm',
        'numero' => '#,##0',
    ];

    private readonly Reporte $reporte;

    /** @var Collection<int, non-empty-array<string, mixed>> */
    private readonly Collection $filas;

    /**
     * @param  array{reporte: Reporte, filas: Collection<int, non-empty-array<string, mixed>>, totales: array{cantidad: int, valor: float|null, grupos: array<string, array{cantidad: int, valor: float|null}>}, filtrosLegibles: array<string, string>, generadoPor: string, generado: Carbon}  $contexto  Ver ExportadorReportes::contexto().
     */
    public function __construct(private readonly array $contexto)
    {
        $this->reporte = $contexto['reporte'];
        $this->filas = $contexto['filas'];
    }

    public function title(): string
    {
        return mb_substr($this->reporte->titulo(), 0, 31);
    }

    public function startCell(): string
    {
        return 'A'.self::FILA_TITULOS;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_values(array_map(fn (array $c) => $c[0], $this->columnas()));
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return array_values($this->filas->map(function (array $fila) {
            return array_map(function (string $clave) use ($fila) {
                $valor = $fila[$clave] ?? null;

                return $valor instanceof CarbonInterface ? Date::dateTimeToExcel($valor) : $valor;
            }, array_keys($this->columnas()));
        })->all());
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $formatos = [];
        $indice = 1;
        foreach ($this->columnas() as [, $tipo]) {
            if (isset(self::FORMATOS[$tipo])) {
                $formatos[Coordinate::stringFromColumnIndex($indice)] = self::FORMATOS[$tipo];
            }
            $indice++;
        }

        return $formatos;
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $evento) {
                $hoja = $evento->getSheet()->getDelegate();
                $ultimaColumna = Coordinate::stringFromColumnIndex(count($this->columnas()));
                $ultimaFila = self::FILA_TITULOS + max(1, $this->filas->count());

                // Encabezado institucional
                $filtros = collect($this->contexto['filtrosLegibles'])->map(fn ($v, $k) => "{$k}: {$v}")->join(' · ');
                $hoja->setCellValue('A1', 'Colegio Adventista Túpac Amaru · SIBIP');
                $hoja->setCellValue('A2', $this->reporte->titulo());
                $hoja->setCellValue('A3', 'Filtros: '.($filtros ?: 'ninguno'));
                $hoja->setCellValue('A4', 'Generado el '.$this->contexto['generado']->format('d/m/Y H:i').' (hora de Lima) por '.$this->contexto['generadoPor']);
                $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('0B3B70');
                $hoja->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $hoja->getStyle('A3:A4')->getFont()->setItalic(true)->getColor()->setRGB('475569');

                // Títulos de columna en negrita, con fondo y filtros automáticos
                $rango = 'A'.self::FILA_TITULOS.":{$ultimaColumna}".self::FILA_TITULOS;
                $hoja->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B3B70');
                $hoja->setAutoFilter('A'.self::FILA_TITULOS.":{$ultimaColumna}{$ultimaFila}");
                $hoja->freezePane('A'.(self::FILA_TITULOS + 1));

                // Totales debajo de los datos (fuera del rango del filtro)
                $filaTotal = $ultimaFila + 2;
                $totales = $this->contexto['totales'];
                $hoja->setCellValue("A{$filaTotal}", "Total: {$totales['cantidad']} {$this->reporte->unidad()}");
                $hoja->getStyle("A{$filaTotal}")->getFont()->setBold(true);
                if ($totales['valor'] !== null && ($columnaValor = $this->letraDe($this->reporte->columnaValor()))) {
                    $hoja->setCellValue("{$columnaValor}{$filaTotal}", $totales['valor']);
                    $hoja->getStyle("{$columnaValor}{$filaTotal}")->getNumberFormat()->setFormatCode(self::FORMATOS['moneda']);
                    $hoja->getStyle("{$columnaValor}{$filaTotal}")->getFont()->setBold(true);
                }

                // Anchos ajustados al contenido
                for ($i = 1; $i <= count($this->columnas()); $i++) {
                    $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
                }
                $hoja->calculateColumnWidths();
            },
        ];
    }

    /**
     * Columnas del Excel: las del reporte, precedidas por la de agrupación si la hay.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function columnas(): array
    {
        $columnas = $this->reporte->columnas();

        return $this->reporte->tituloGrupo() === null
            ? $columnas
            : ['_grupo' => [$this->reporte->tituloGrupo(), 'texto'], ...$columnas];
    }

    private function letraDe(?string $clave): ?string
    {
        $posicion = $clave === null ? false : array_search($clave, array_keys($this->columnas()), true);

        return $posicion === false ? null : Coordinate::stringFromColumnIndex($posicion + 1);
    }
}
