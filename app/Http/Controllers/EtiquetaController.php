<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Sede;
use App\Services\GeneradorEtiquetas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impresión de etiquetas con código de barras, individual o por lote, en tres
 * formatos: impresión directa desde el navegador, PDF del tamaño exacto y PNG
 * (un ZIP en los lotes) para las apps de las mini impresoras Bluetooth.
 */
class EtiquetaController extends Controller
{
    public const FORMATOS = ['html', 'pdf', 'png', 'zip'];

    /** Máximo de etiquetas por lote, para no agotar memoria ni tiempo de respuesta. */
    public const MAXIMO_POR_LOTE = 300;

    public function index(Request $request): View
    {
        $filtros = $request->validate($this->reglasDeFiltro());
        $query = $this->consultaFiltrada($filtros);

        $total = (clone $query)->count();
        $bienes = $query->with(['sede', 'area', 'categoria'])->orderBy('id')->limit(self::MAXIMO_POR_LOTE)->get();

        return view('etiquetas.index', [
            'bienes' => $bienes,
            'total' => $total,
            'filtros' => $filtros,
            'sedes' => Sede::where('estado', 'activo')->orderBy('nombre')->get(),
            'areas' => Area::where('estado', 'activo')->orderBy('nombre')->get(),
            'categorias' => Categoria::where('estado', 'activo')->orderBy('nombre')->get(),
            'generador' => GeneradorEtiquetas::conConfiguracionActual(),
        ]);
    }

    /**
     * Lote: bienes seleccionados en la tabla o todos los que cumplen el filtro.
     */
    public function generar(Request $request): RedirectResponse|Response|View
    {
        $datos = $request->validate([
            ...$this->reglasDeFiltro(),
            'formato' => ['required', Rule::in(['html', 'pdf', 'zip'])],
            'alcance' => ['required', 'in:seleccion,filtro'],
            'bien_ids' => ['required_if:alcance,seleccion', 'array', 'max:'.self::MAXIMO_POR_LOTE],
            'bien_ids.*' => ['integer'],
        ], [
            'bien_ids.required_if' => 'Seleccione al menos un bien o elija imprimir todos los filtrados.',
            'bien_ids.max' => 'Se pueden imprimir hasta '.self::MAXIMO_POR_LOTE.' etiquetas por lote.',
        ]);

        $query = $this->consultaFiltrada($datos);
        if ($datos['alcance'] === 'seleccion') {
            $query->whereIn('id', $datos['bien_ids']);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            return back()->with('error', 'No hay bienes para imprimir con los criterios elegidos.');
        }
        if ($total > self::MAXIMO_POR_LOTE) {
            return back()->with('error', "El filtro abarca {$total} bienes. Afine el filtro: se pueden imprimir hasta ".self::MAXIMO_POR_LOTE.' etiquetas por lote.');
        }

        return $this->responder($query->orderBy('id')->get(['id', 'codigo_barras', 'nombre']), $datos['formato']);
    }

    /**
     * Etiqueta individual desde el detalle del bien.
     */
    public function bien(Bien $biene, string $formato): Response|View
    {
        $this->authorize('view', $biene);
        abort_unless(in_array($formato, ['html', 'pdf', 'png'], true), 404);

        return $this->responder(collect([$biene]), $formato);
    }

    /**
     * Código de barras en SVG para mostrar en pantalla.
     */
    public function codigoBarrasSvg(Bien $biene, GeneradorEtiquetas $generador): Response
    {
        $this->authorize('view', $biene);

        return response($generador->svg($biene->codigoDeBarras()), 200, ['Content-Type' => 'image/svg+xml']);
    }

    /**
     * Página de calibración de la impresora, con la configuración guardada.
     */
    public function prueba(string $formato): Response|View
    {
        abort_unless(in_array($formato, ['html', 'pdf', 'png'], true), 404);
        $generador = GeneradorEtiquetas::conConfiguracionActual();
        $png = $generador->pngDePrueba();

        return match ($formato) {
            'png' => $this->descargaPng($png, 'pagina-de-prueba.png'),
            'pdf' => $this->descargaPdf($generador->pdf([$png]), 'pagina-de-prueba.pdf'),
            'html' => view('etiquetas.imprimir', ['etiquetas' => [['codigo' => 'Página de prueba', 'png' => $png]], 'config' => $generador->configuracion()]),
        };
    }

    /**
     * @param  Collection<int, Bien>  $bienes
     */
    private function responder(Collection $bienes, string $formato): Response|View
    {
        $generador = GeneradorEtiquetas::conConfiguracionActual();
        $etiquetas = $bienes->map(fn (Bien $bien) => [
            'codigo' => $bien->codigo_barras,
            'png' => $generador->png($bien->codigoDeBarras(), $bien->nombre),
        ])->all();

        $this->registrarImpresion($bienes, $formato);
        $sufijo = now()->format('Ymd-His');

        return match ($formato) {
            'html' => view('etiquetas.imprimir', ['etiquetas' => $etiquetas, 'config' => $generador->configuracion()]),
            'pdf' => $this->descargaPdf($generador->pdf(array_column($etiquetas, 'png')), count($etiquetas) === 1 ? "{$etiquetas[0]['codigo']}.pdf" : "etiquetas-{$sufijo}.pdf"),
            'png' => $this->descargaPng($etiquetas[0]['png'], "{$etiquetas[0]['codigo']}.png"),
            'zip' => response()->download(
                $generador->zip(collect($etiquetas)->mapWithKeys(fn (array $e) => ["{$e['codigo']}.png" => $e['png']])->all()),
                "etiquetas-{$sufijo}.zip",
                ['Content-Type' => 'application/zip'],
            )->deleteFileAfterSend(),
            default => abort(404),
        };
    }

    /**
     * Marca la fecha de impresión de cada bien y deja un solo registro de auditoría por lote.
     *
     * @param  Collection<int, Bien>  $bienes
     */
    private function registrarImpresion(Collection $bienes, string $formato): void
    {
        $ids = $bienes->pluck('id')->all();
        Bien::whereIn('id', $ids)->toBase()->update(['etiqueta_impresa_at' => now()]);

        Auditoria::registrar('imprimir_etiquetas', 'bienes', count($ids) === 1 ? $ids[0] : null, null, [
            'formato' => $formato,
            'cantidad' => count($ids),
            'codigos' => $bienes->pluck('codigo_barras')->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasDeFiltro(): array
    {
        return [
            'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'solo_sin_etiqueta' => ['nullable', 'boolean'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Bienes activos (no dados de baja) que cumplen el filtro.
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<Bien>
     */
    private function consultaFiltrada(array $filtros): Builder
    {
        return Bien::query()
            ->where('estado', '!=', 'dado_de_baja')
            ->when($filtros['sede_id'] ?? null, fn (Builder $q, $sede) => $q->where('sede_id', $sede))
            ->when($filtros['area_id'] ?? null, fn (Builder $q, $area) => $q->where('area_id', $area))
            ->when($filtros['categoria_id'] ?? null, fn (Builder $q, $categoria) => $q->where('categoria_id', $categoria))
            ->when($filtros['solo_sin_etiqueta'] ?? false, fn (Builder $q) => $q->whereNull('etiqueta_impresa_at'))
            ->when($filtros['buscar'] ?? null, fn (Builder $q, $buscar) => $q->where(fn (Builder $sub) => $sub
                ->where('nombre', 'like', "%{$buscar}%")
                ->orWhere('codigo_barras', 'like', "%{$buscar}%")
                ->orWhere('codigo_patrimonial', 'like', "%{$buscar}%")));
    }

    private function descargaPdf(string $pdf, string $nombre): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
        ]);
    }

    private function descargaPng(string $png, string $nombre): Response
    {
        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }
}
