<?php

namespace App\Http\Controllers;

use App\Exports\ResultadoInventarioExport;
use App\Models\Area;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Inventario;
use App\Models\InventarioDetalle;
use App\Models\Movimiento;
use App\Models\Sede;
use App\Models\Usuario;
use App\Rules\AreaPerteneceASede;
use App\Services\TomaDeInventario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventarioController extends Controller
{
    /** Máximo de lecturas por envío de sincronización. */
    public const LOTE_SINCRONIZACION = 100;

    public function __construct(private readonly TomaDeInventario $toma) {}

    public function index(Request $request): View
    {
        $inventarios = Inventario::with(['sede', 'area', 'creador'])
            ->withCount(['lecturas'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('inventarios.index', compact('inventarios'));
    }

    public function create(): View
    {
        $this->authorize('create', Inventario::class);

        return view('inventarios.create', [
            'sedes' => Sede::where('estado', 'activo')->orderBy('nombre')->get(),
            'areas' => Area::where('estado', 'activo')->orderBy('nombre')->get(),
            'contadores' => $this->posiblesResponsables(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Inventario::class);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'sede_id' => ['required', 'exists:sedes,id'],
            'area_id' => ['nullable', 'exists:areas,id', new AreaPerteneceASede($request->input('sede_id'))],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'responsables' => ['nullable', 'array'],
            'responsables.*' => ['integer', Rule::in($this->posiblesResponsables()->pluck('id'))],
        ], [
            'responsables.*.in' => 'Uno de los responsables elegidos no puede realizar conteos.',
        ]);

        $inventario = $this->toma->crear(
            $this->usuarioActual($request),
            Arr::except($datos, 'responsables'),
            array_values(array_map('intval', $datos['responsables'] ?? [])),
        );

        return redirect()->route('inventarios.escanear', $inventario)
            ->with('success', "Campaña {$inventario->codigo} abierta. Ya puede empezar a escanear.");
    }

    public function show(Inventario $inventario): View
    {
        $this->authorize('view', $inventario);

        $inventario->load(['sede', 'area', 'creador', 'cerradoPor', 'responsables']);
        $detalles = $inventario->lecturas()
            ->with(['bien.sede', 'bien.area', 'areaEscaneo', 'escaneadoPor'])
            ->orderBy('resultado')
            ->orderBy('codigo_leido')
            ->get()
            ->groupBy('resultado');

        return view('inventarios.show', [
            'inventario' => $inventario,
            'resumen' => $this->toma->resumen($inventario),
            'detalles' => $detalles,
            // Solicitudes ya generadas desde esta campaña, por bien.
            'movimientos' => Movimiento::where('inventario_id', $inventario->id)->pluck('estado', 'bien_id'),
            'bajas' => Baja::where('inventario_id', $inventario->id)->pluck('estado', 'bien_id'),
        ]);
    }

    public function escanear(Inventario $inventario): View
    {
        $this->authorize('escanear', $inventario);

        $inventario->load(['sede', 'area']);

        return view('inventarios.escanear', [
            'inventario' => $inventario,
            'areas' => $inventario->area_id ? collect() : Area::where('sede_id', $inventario->sede_id)->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Registra una lectura (lectora USB/Bluetooth o cámara).
     */
    public function lectura(Request $request, Inventario $inventario): JsonResponse
    {
        $this->authorize('escanear', $inventario);
        $datos = $request->validate($this->reglasDeLectura());

        $resultado = $this->toma->registrarLectura(
            $inventario,
            $this->usuarioActual($request),
            $datos['codigo'],
            isset($datos['area_escaneo_id']) ? (int) $datos['area_escaneo_id'] : null,
            $datos['estado_fisico'] ?? null,
            $datos['uuid'] ?? null,
        );

        return response()->json([
            'lectura' => $this->lecturaJson($resultado['detalle'], $resultado['resultado']),
            'resumen' => $this->toma->resumen($inventario),
        ]);
    }

    /**
     * Sincroniza lecturas guardadas sin conexión. Es idempotente por uuid:
     * reenviar el mismo lote no duplica lecturas.
     */
    public function sincronizar(Request $request, Inventario $inventario): JsonResponse
    {
        $this->authorize('escanear', $inventario);

        $request->validate([
            'lecturas' => ['required', 'array', 'max:'.self::LOTE_SINCRONIZACION],
            ...collect($this->reglasDeLectura())->mapWithKeys(fn ($reglas, $campo) => ["lecturas.*.{$campo}" => $reglas])->all(),
            'lecturas.*.uuid' => ['required', 'uuid'],
            'lecturas.*.fecha_hora' => ['nullable', 'date'],
        ]);

        $respuestas = [];
        foreach ($request->input('lecturas') as $lectura) {
            try {
                $resultado = $this->toma->registrarLectura(
                    $inventario,
                    $this->usuarioActual($request),
                    $lectura['codigo'],
                    isset($lectura['area_escaneo_id']) ? (int) $lectura['area_escaneo_id'] : null,
                    $lectura['estado_fisico'] ?? null,
                    $lectura['uuid'],
                    $this->fechaDeLectura($lectura['fecha_hora'] ?? null, $inventario),
                    offline: true,
                );
                $respuestas[] = ['uuid' => $lectura['uuid'], 'ok' => true, 'lectura' => $this->lecturaJson($resultado['detalle'], $resultado['resultado'])];
            } catch (ValidationException $e) {
                // La lectura no se puede registrar (p. ej. campaña cerrada): el cliente la descarta y avisa.
                $respuestas[] = ['uuid' => $lectura['uuid'], 'ok' => false, 'error' => collect($e->errors())->flatten()->first()];
            }
        }

        return response()->json(['respuestas' => $respuestas, 'resumen' => $this->toma->resumen($inventario)]);
    }

    /**
     * Avance y últimas 20 lecturas (la pantalla lo consulta periódicamente para
     * ver también lo que escanean los demás usuarios).
     */
    public function estado(Inventario $inventario): JsonResponse
    {
        $this->authorize('view', $inventario);

        $ultimas = $inventario->lecturas()
            ->with(['bien.sede', 'bien.area', 'escaneadoPor', 'areaEscaneo'])
            ->where('resultado', '!=', 'faltante')
            ->latest('id')
            ->limit(20)
            ->get();

        return response()->json([
            'abierto' => $inventario->estaAbierto(),
            'resumen' => $this->toma->resumen($inventario),
            'ultimas' => $ultimas->map(fn (InventarioDetalle $d) => $this->lecturaJson($d, $d->resultado))->values(),
        ]);
    }

    /**
     * Catálogo mínimo para clasificar lecturas en el navegador si se pierde la conexión.
     */
    public function catalogo(Inventario $inventario): JsonResponse
    {
        $this->authorize('escanear', $inventario);

        // Solo los bienes del ámbito de la campaña (incluidos los dados de baja, para
        // reconocerlos) y solo los campos necesarios: nada de responsables ni valores.
        $bienes = Bien::query()
            ->where('sede_id', $inventario->sede_id)
            ->when($inventario->area_id, fn ($q) => $q->where('area_id', $inventario->area_id))
            ->get(['id', 'codigo_barras', 'nombre', 'area_id', 'estado'])
            ->mapWithKeys(fn (Bien $b) => [$b->codigo_barras => [
                'i' => $b->id,
                'n' => Str::limit($b->nombre, 40),
                'a' => $b->area_id,
                'e' => $b->estado,
            ]]);

        return response()->json([
            'bienes' => $bienes,
            'areas' => Area::where('sede_id', $inventario->sede_id)->pluck('nombre', 'id'),
            'leidos' => $inventario->lecturas()->pluck('codigo_leido'),
        ]);
    }

    public function deshacer(Request $request, Inventario $inventario, InventarioDetalle $detalle): JsonResponse
    {
        $this->authorize('escanear', $inventario);
        $this->toma->deshacer($detalle, $this->usuarioActual($request));

        return response()->json(['ok' => true, 'resumen' => $this->toma->resumen($inventario)]);
    }

    public function estadoFisico(Request $request, Inventario $inventario, InventarioDetalle $detalle): JsonResponse
    {
        $this->authorize('escanear', $inventario);
        $datos = $request->validate(['estado_fisico' => ['nullable', Rule::in(array_keys(InventarioDetalle::ESTADOS_FISICOS))]]);

        $this->toma->marcarEstadoFisico($detalle, $datos['estado_fisico'] ?? null);

        return response()->json(['ok' => true, 'lectura' => $this->lecturaJson($detalle->refresh(), $detalle->resultado)]);
    }

    public function cerrar(Request $request, Inventario $inventario): RedirectResponse
    {
        $this->authorize('cerrar', $inventario);
        $this->toma->cerrar($inventario, $this->usuarioActual($request));

        return redirect()->route('inventarios.show', $inventario)->with('success', "Campaña {$inventario->codigo} cerrada. Estos son los resultados.");
    }

    public function reabrir(Inventario $inventario): RedirectResponse
    {
        $this->authorize('reabrir', $inventario);
        $this->toma->reabrir($inventario);

        return redirect()->route('inventarios.show', $inventario)->with('success', "Campaña {$inventario->codigo} reabierta.");
    }

    public function exportar(Inventario $inventario, string $formato): BinaryFileResponse|Response
    {
        $this->authorize('exportar', $inventario);
        abort_unless(in_array($formato, ['pdf', 'xlsx'], true), 404);

        $export = new ResultadoInventarioExport($inventario);
        $nombre = "resultado-{$inventario->codigo}";

        if ($formato === 'xlsx') {
            return Excel::download($export, "{$nombre}.xlsx");
        }

        return Pdf::loadView('inventarios.pdf', [
            'inventario' => $inventario->load(['sede', 'area', 'creador', 'cerradoPor']),
            'resumen' => $this->toma->resumen($inventario),
            'filas' => $export->filas(),
            'generado' => now(),
        ])->setPaper('a4')->download("{$nombre}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasDeLectura(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:80'],
            'area_escaneo_id' => ['nullable', 'integer', 'exists:areas,id'],
            'estado_fisico' => ['nullable', Rule::in(array_keys(InventarioDetalle::ESTADOS_FISICOS))],
            'uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * Hora real de la lectura offline, acotada entre el inicio de la campaña y ahora.
     */
    private function fechaDeLectura(?string $fecha, Inventario $inventario): Carbon
    {
        if (! $fecha) {
            return now();
        }

        $fechaHora = Carbon::parse($fecha)->setTimezone(config('app.timezone'));

        return $fechaHora->isFuture() || $fechaHora->lt($inventario->fecha_inicio) ? now() : $fechaHora;
    }

    /**
     * @return array<string, mixed>
     */
    private function lecturaJson(InventarioDetalle $detalle, string $resultado): array
    {
        $detalle->loadMissing(['bien.sede', 'bien.area', 'escaneadoPor', 'areaEscaneo']);
        $bien = $detalle->bien;
        $info = InventarioDetalle::RESULTADOS[$resultado];

        return [
            'id' => $detalle->id,
            'uuid' => $detalle->uuid_cliente,
            'codigo' => $detalle->codigo_leido,
            'resultado' => $resultado,
            'etiqueta' => $info['etiqueta'],
            'color' => $info['color'],
            'bien' => $bien ? ['id' => $bien->id, 'nombre' => $bien->nombre, 'ubicacion' => $this->ubicacion($bien)] : null,
            'mensaje' => match ($resultado) {
                'fuera_de_ubicacion' => 'Debería estar en: '.$this->ubicacion($bien),
                'dado_de_baja' => 'El bien figura dado de baja, pero está físicamente aquí.',
                'sobrante_no_registrado' => 'Este código no corresponde a ningún bien registrado.',
                'ya_escaneado' => 'Ya se leyó en esta campaña'.($detalle->escaneadoPor ? ' por '.$detalle->escaneadoPor->nombre_completo : '').'.',
                default => $bien ? $this->ubicacion($bien) : '',
            },
            'estado_fisico' => $detalle->estado_fisico,
            'area_escaneo' => $detalle->areaEscaneo?->nombre,
            'por' => $detalle->escaneadoPor?->nombre_completo,
            'hora' => $detalle->fecha_hora->format('H:i:s'),
            'offline' => $detalle->registrado_offline,
        ];
    }

    private function ubicacion(?Bien $bien): string
    {
        if (! $bien) {
            return '';
        }

        return trim(($bien->sede->nombre ?? 'Sin sede').' / '.($bien->area->nombre ?? 'sin área'));
    }

    /**
     * @return Collection<int, Usuario>
     */
    private function posiblesResponsables(): Collection
    {
        return Usuario::with('rol')
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Usuario $u) => $u->can('toma_inventario.editar'))
            ->values();
    }
}
