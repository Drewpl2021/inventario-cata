<?php

namespace App\Reportes;

use App\Exports\ReporteExport;
use App\Models\Auditoria;
use App\Models\Responsable;
use App\Models\Usuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Genera los reportes en PDF y Excel con el encabezado institucional
 * (logo, colegio, título, filtros, fecha y hora de Lima y usuario).
 */
class ExportadorReportes
{
    /**
     * Datos comunes de la vista previa, el PDF y el Excel.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{reporte: Reporte, filas: Collection<int, non-empty-array<string, mixed>>, totales: array{cantidad: int, valor: float|null, grupos: array<string, array{cantidad: int, valor: float|null}>}, filtrosLegibles: array<string, string>, generadoPor: string, generado: Carbon}
     */
    public function contexto(Reporte $reporte, array $filtros, Usuario $usuario): array
    {
        $filas = $reporte->filas($filtros);

        return [
            'reporte' => $reporte,
            'filas' => $filas,
            'totales' => $reporte->totales($filas),
            'filtrosLegibles' => $reporte->filtrosLegibles($filtros),
            'generadoPor' => $usuario->nombre_completo,
            'generado' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function pdf(Reporte $reporte, array $filtros, Usuario $usuario): string
    {
        $contexto = $this->contexto($reporte, $filtros, $usuario);

        if ($reporte instanceof PorResponsable) {
            $contexto['responsables'] = Responsable::withTrashed()
                ->with(['sede', 'area'])
                ->whereIn('id', $contexto['filas']->pluck('_responsable_id')->filter()->unique())
                ->get()
                ->keyBy('id');
        }

        $pdf = Pdf::loadView($reporte->vistaPdf(), $contexto)
            ->setPaper('a4', $reporte instanceof PorResponsable ? 'portrait' : 'landscape')
            ->setOption('isFontSubsettingEnabled', true); // solo los caracteres usados: PDF mucho más liviano
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        // Pie de cada página: "Página X de Y".
        $lienzo = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('DejaVu Sans') ?? 'DejaVu Sans';
        $lienzo->page_text($lienzo->get_width() - 110, $lienzo->get_height() - 28, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 8, [0.39, 0.45, 0.55]);

        return (string) $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function excel(Reporte $reporte, array $filtros, Usuario $usuario): ReporteExport
    {
        return new ReporteExport($this->contexto($reporte, $filtros, $usuario));
    }

    /**
     * Cada exportación queda en la auditoría.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function auditar(Reporte $reporte, array $filtros, string $formato, int $registros, bool $enCola = false): void
    {
        Auditoria::registrar('exportar_reporte', 'reportes', null, null, [
            'reporte' => $reporte->clave(),
            'formato' => $formato,
            'filtros' => array_filter($filtros, fn ($v) => filled($v)),
            'registros' => $registros,
            'en_cola' => $enCola,
        ]);
    }

    /**
     * Nombre de archivo: "inventario-general-20261017-1530".
     */
    public function nombreArchivo(Reporte $reporte): string
    {
        return $reporte->clave().'-'.now()->format('Ymd-Hi');
    }

    /**
     * @param  Collection<int, non-empty-array<string, mixed>>  $filas
     * @return Collection<array-key, Collection<int, non-empty-array<string, mixed>>>
     */
    public static function agrupadas(Reporte $reporte, Collection $filas): Collection
    {
        return $reporte->tituloGrupo() === null ? collect(['' => $filas]) : $filas->groupBy('_grupo');
    }
}
