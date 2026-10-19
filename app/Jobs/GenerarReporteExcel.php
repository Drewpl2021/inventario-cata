<?php

namespace App\Jobs;

use App\Models\Usuario;
use App\Notifications\ReporteListo;
use App\Reportes\CatalogoReportes;
use App\Reportes\ExportadorReportes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

/**
 * Genera en segundo plano el Excel de un reporte grande y avisa al usuario
 * (campana de notificaciones) cuando está listo para descargar.
 */
class GenerarReporteExcel implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        public readonly int $usuarioId,
        public readonly string $clave,
        public readonly array $filtros,
        public readonly string $archivo,
    ) {}

    public function handle(ExportadorReportes $exportador): void
    {
        $usuario = Usuario::findOrFail($this->usuarioId);
        $reporte = CatalogoReportes::buscar($this->clave) ?? throw new RuntimeException("El reporte \"{$this->clave}\" ya no existe.");

        Excel::store($exportador->excel($reporte, $this->filtros, $usuario), "reportes/{$this->archivo}", 'local');

        $usuario->notify(new ReporteListo(
            titulo: "Reporte listo: {$reporte->titulo()}",
            mensaje: 'El Excel ya se puede descargar.',
            url: route('reportes.descargar', $this->archivo),
        ));
    }

    public function failed(?Throwable $error): void
    {
        Usuario::find($this->usuarioId)?->notify(new ReporteListo(
            titulo: 'No se pudo generar el reporte',
            mensaje: 'Inténtelo de nuevo o use filtros más acotados.',
            url: route('reportes.index'),
            icono: 'fa-triangle-exclamation',
        ));
    }
}
