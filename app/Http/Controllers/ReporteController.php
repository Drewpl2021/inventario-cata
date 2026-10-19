<?php

namespace App\Http\Controllers;

use App\Jobs\GenerarReporteExcel;
use App\Models\Area;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Responsable;
use App\Models\Sede;
use App\Reportes\CatalogoReportes;
use App\Reportes\ExportadorReportes;
use App\Reportes\PorResponsable;
use App\Reportes\Reporte;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes con filtros, vista previa y exportación a PDF y Excel.
 * Los Excel grandes se generan en la cola y se avisa al terminar.
 */
class ReporteController extends Controller
{
    public function __construct(private readonly ExportadorReportes $exportador) {}

    public function index(): View
    {
        return view('reportes.index', ['reportes' => CatalogoReportes::todos()]);
    }

    public function show(Request $request, string $clave): View
    {
        $reporte = $this->reporte($clave);
        $filtros = $this->filtros($request, $reporte, exigirRequeridos: false);
        $listo = collect($reporte->filtrosRequeridos())->every(fn ($f) => filled($filtros[$f] ?? null));

        $contexto = $listo ? $this->exportador->contexto($reporte, $filtros, $this->usuarioActual($request)) : null;

        // La vista previa se pagina (los totales se calculan sobre todo el reporte).
        $pagina = $contexto ? new LengthAwarePaginator(
            $contexto['filas']->forPage(LengthAwarePaginator::resolveCurrentPage(), config('sibip.reportes.filas_por_pagina'))->values(),
            $contexto['filas']->count(),
            config('sibip.reportes.filas_por_pagina'),
            null,
            ['path' => $request->url(), 'query' => $request->query()],
        ) : null;

        return view('reportes.show', [
            'reporte' => $reporte,
            'filtros' => $filtros,
            'listo' => $listo,
            'contexto' => $contexto,
            'pagina' => $pagina,
            'limiteExcel' => config('sibip.reportes.limite_excel_directo'),
            ...$this->catalogosDeFiltros($reporte),
        ]);
    }

    public function exportar(Request $request, string $clave, string $formato): RedirectResponse|Response|BinaryFileResponse
    {
        abort_unless(in_array($formato, ['pdf', 'xlsx'], true), 404);
        $reporte = $this->reporte($clave);
        $filtros = $this->filtros($request, $reporte);
        $usuario = $this->usuarioActual($request);
        $registros = $reporte->contar($filtros);
        $nombre = $this->exportador->nombreArchivo($reporte);

        if ($formato === 'xlsx' && $registros > config('sibip.reportes.limite_excel_directo')) {
            $archivo = $usuario->id.'-'.$nombre.'-'.Str::lower(Str::random(8)).'.xlsx';
            GenerarReporteExcel::dispatch($usuario->id, $reporte->clave(), $filtros, $archivo);
            $this->exportador->auditar($reporte, $filtros, $formato, $registros, enCola: true);

            return back()->with('success', "El reporte tiene {$registros} registros: se está generando y le avisaremos en la campana de notificaciones cuando esté listo.");
        }

        $this->exportador->auditar($reporte, $filtros, $formato, $registros);

        return $formato === 'pdf'
            ? response($this->exportador->pdf($reporte, $filtros, $usuario), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$nombre.'.pdf"',
            ])
            : Excel::download($this->exportador->excel($reporte, $filtros, $usuario), "{$nombre}.xlsx");
    }

    /**
     * Descarga de un Excel generado en la cola. Solo para quien lo pidió.
     */
    public function descargar(Request $request, string $archivo): StreamedResponse
    {
        abort_unless(preg_match('/^\d+-[a-z0-9-]+\.xlsx$/', $archivo) === 1, 404);
        abort_unless(Str::before($archivo, '-') === (string) $this->usuarioActual($request)->id, 404);
        abort_unless(Storage::disk('local')->exists("reportes/{$archivo}"), 404);

        return Storage::disk('local')->download("reportes/{$archivo}", Str::after($archivo, '-'));
    }

    /**
     * "Mis bienes": el responsable descarga el acta de asignación de SUS bienes.
     */
    public function actaPropia(Request $request): Response
    {
        $responsable = $this->usuarioActual($request)->responsable;
        abort_unless($responsable !== null, 404);

        $reporte = new PorResponsable;
        $filtros = ['responsable_id' => $responsable->id];
        $this->exportador->auditar($reporte, $filtros, 'pdf', $reporte->contar($filtros));

        return response($this->exportador->pdf($reporte, $filtros, $this->usuarioActual($request)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="acta-de-asignacion-'.Str::slug($responsable->nombre_completo).'.pdf"',
        ]);
    }

    private function reporte(string $clave): Reporte
    {
        return CatalogoReportes::buscar($clave) ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request, Reporte $reporte, bool $exigirRequeridos = true): array
    {
        $reglas = $reporte->reglas();
        if (! $exigirRequeridos) {
            $reglas = array_map(fn (array $r) => ['nullable', ...array_slice($r, 1)], $reglas);
        }

        return array_intersect_key($request->validate($reglas), array_flip($reporte->filtros()));
    }

    /**
     * Opciones de los filtros que usa el reporte.
     *
     * @return array<string, mixed>
     */
    private function catalogosDeFiltros(Reporte $reporte): array
    {
        $usa = fn (string $filtro) => in_array($filtro, $reporte->filtros(), true);

        return [
            'sedes' => $usa('sede_id') ? Sede::orderBy('nombre')->get() : collect(),
            'areas' => $usa('area_id') ? Area::orderBy('nombre')->get() : collect(),
            'categorias' => $usa('categoria_id') ? Categoria::orderBy('nombre')->get() : collect(),
            'responsables' => $usa('responsable_id') ? Responsable::orderBy('apellidos')->get() : collect(),
            'inventarios' => $usa('inventario_id') ? Inventario::with('sede')->latest('id')->get() : collect(),
        ];
    }
}
