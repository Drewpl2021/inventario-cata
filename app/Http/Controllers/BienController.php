<?php

namespace App\Http\Controllers;

use App\Http\Requests\BienRequest;
use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Documento;
use App\Models\InventarioDetalle;
use App\Models\Movimiento;
use App\Models\Responsable;
use App\Models\Sede;
use App\Services\AlmacenArchivos;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BienController extends Controller
{
    public function __construct(private readonly AlmacenArchivos $archivos) {}

    public function index(Request $request): View
    {
        return $this->listado($request, Bien::query());
    }

    /**
     * Bienes de los que el usuario autenticado es responsable.
     */
    public function misBienes(Request $request): View
    {
        $responsableId = $this->usuarioActual($request)->responsable?->id;

        $query = Bien::query()->where('responsable_id', $responsableId ?? 0);

        return $this->listado($request, $query, misBienes: true);
    }

    /**
     * @param  Builder<Bien>  $query
     */
    private function listado(Request $request, Builder $query, bool $misBienes = false): View
    {
        $query->with(['categoria', 'sede', 'area', 'responsable']);

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('codigo_barras', 'like', "%{$buscar}%")
                    ->orWhere('codigo_patrimonial', 'like', "%{$buscar}%")
                    ->orWhere('codigo_interno', 'like', "%{$buscar}%")
                    ->orWhere('numero_serie', 'like', "%{$buscar}%")
                    ->orWhere('marca', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->input('etiqueta') === 'sin') {
            $query->whereNull('etiqueta_impresa_at');
        } elseif ($request->input('etiqueta') === 'con') {
            $query->whereNotNull('etiqueta_impresa_at');
        }

        $bienes = $query->latest()->paginate(15)->withQueryString();
        $categorias = Categoria::all();
        $sedes = Sede::all();

        return view('bienes.index', compact('bienes', 'categorias', 'sedes', 'misBienes'));
    }

    public static function generateNextCodigoInterno(): string
    {
        return Bien::siguienteCodigoInterno();
    }

    public static function generateNextCodigoPatrimonial(): string
    {
        return Bien::siguienteCodigoPatrimonial();
    }

    public function create(): View
    {
        $this->authorize('create', Bien::class);

        return view('bienes.create', [
            ...$this->catalogosFormulario(),
            'nextCodigoInterno' => Bien::siguienteCodigoInterno(),
        ]);
    }

    public function store(BienRequest $request): RedirectResponse
    {
        $this->authorize('create', Bien::class);

        $data = $request->safe()->except(['fotografia', 'comprobante_archivo', 'inventario_detalle_id']);
        $data['tipo_ingreso'] ??= 'con_comprobante';

        if ($request->hasFile('fotografia')) {
            $data['fotografia'] = $this->archivos->guardarSubido($request, 'fotografia', AlmacenArchivos::CARPETA_FOTOS_BIENES);
        }

        if ($request->hasFile('comprobante_archivo')) {
            $data['comprobante_archivo'] = $this->archivos->guardarSubido($request, 'comprobante_archivo', AlmacenArchivos::CARPETA_COMPROBANTES);
        }

        $bien = DB::transaction(function () use ($data, $request) {
            $bien = Bien::create($data);

            // Si es un sobrante de una toma de inventario, la lectura queda vinculada al bien nuevo.
            if ($request->filled('inventario_detalle_id')) {
                InventarioDetalle::whereKey($request->validated('inventario_detalle_id'))
                    ->whereNull('bien_id')
                    ->update(['bien_id' => $bien->id]);
            }

            // Si se subió un comprobante, registrarlo también en la tabla de documentos
            if (! empty($data['comprobante_archivo'])) {
                Documento::create([
                    'bien_id' => $bien->id,
                    'tipo' => 'factura',
                    'nombre' => 'Comprobante de Ingreso '.($request->filled('numero_comprobante') ? '('.$request->numero_comprobante.')' : '').' - '.$bien->nombre,
                    'ruta' => $data['comprobante_archivo'],
                    'usuario_id' => $this->usuarioActual($request)->id,
                ]);
            }

            return $bien;
        });

        return redirect()->route('bienes.show', $bien)->with('success', "Bien patrimonial {$bien->codigo_barras} creado exitosamente. Ya puede imprimir su etiqueta.");
    }

    public function show(Bien $biene): View
    {
        $this->authorize('view', $biene);

        $biene->load([
            'categoria',
            'sede',
            'area',
            'responsable',
            'movimientos' => function ($q) {
                $q->with(['sedeOrigen', 'areaOrigen', 'sedeDestino', 'areaDestino', 'responsableAnterior', 'responsableNuevo', 'usuario'])
                    ->orderBy('fecha_movimiento', 'desc');
            },
            'mantenimientos' => function ($q) {
                $q->with('usuario')->orderBy('fecha_inicio', 'desc');
            },
            'documentos' => function ($q) {
                $q->with('usuario')->latest();
            },
            'baja.usuario',
            'baja.aprobador',
        ]);

        return view('bienes.show', [
            'bien' => $biene,
            'historial' => $this->historialDeEventos($biene),
            'historialEstados' => $this->historialDeEstados($biene),
        ]);
    }

    public function edit(Bien $biene): View
    {
        $this->authorize('update', $biene);

        return view('bienes.edit', ['bien' => $biene, ...$this->catalogosFormulario()]);
    }

    public function update(BienRequest $request, Bien $biene): RedirectResponse
    {
        $this->authorize('update', $biene);

        $data = $request->safe()->except(['fotografia', 'comprobante_archivo']);
        $fotoAnterior = $biene->fotografia;

        if ($request->hasFile('fotografia')) {
            $data['fotografia'] = $this->archivos->guardarSubido($request, 'fotografia', AlmacenArchivos::CARPETA_FOTOS_BIENES);
        }

        DB::transaction(function () use ($request, $biene, &$data) {
            if ($request->hasFile('comprobante_archivo')) {
                // El comprobante anterior se conserva: sigue registrado en el archivo documentario.
                $data['comprobante_archivo'] = $this->archivos->guardarSubido($request, 'comprobante_archivo', AlmacenArchivos::CARPETA_COMPROBANTES);

                // Registrar nuevo comprobante en documentos
                Documento::create([
                    'bien_id' => $biene->id,
                    'tipo' => 'factura',
                    'nombre' => 'Comprobante de Ingreso Actualizado '.($request->filled('numero_comprobante') ? '('.$request->numero_comprobante.')' : '').' - '.$biene->nombre,
                    'ruta' => $data['comprobante_archivo'],
                    'usuario_id' => $this->usuarioActual($request)->id,
                ]);
            }

            $biene->update($data);
        });

        if (isset($data['fotografia']) && $fotoAnterior !== $data['fotografia']) {
            $this->archivos->eliminar($fotoAnterior);
        }

        return redirect()->route('bienes.index')->with('success', 'Bien patrimonial actualizado exitosamente.');
    }

    /**
     * Los bienes nunca se borran físicamente. Solo se puede retirar (soft delete)
     * un registro hecho por error y sin historial; los demás se dan de baja.
     */
    public function destroy(Bien $biene): RedirectResponse
    {
        $this->authorize('delete', $biene);

        if ($biene->tieneHistorial()) {
            return back()->with('error', 'El bien tiene movimientos, bajas o mantenimientos registrados. Para retirarlo del inventario, solicite su baja.');
        }

        $biene->delete();

        return redirect()->route('bienes.index')->with('success', 'Registro del bien retirado del inventario.');
    }

    /**
     * Línea de tiempo del bien: alta, movimientos, mantenimientos y baja.
     *
     * @return Collection<int, array{fecha: ?Carbon, evento: string, detalle: string, usuario: ?string, icono: string}>
     */
    private function historialDeEventos(Bien $bien): Collection
    {
        $alta = Auditoria::with('usuario')->where('tabla', 'bienes')->where('registro_id', $bien->id)->where('accion', 'crear')->first();

        $eventos = collect([[
            'fecha' => $bien->created_at,
            'evento' => 'Registro',
            'detalle' => 'Ingreso al inventario · '.$bien->tipo_ingreso_texto,
            'usuario' => $alta?->usuario?->nombre_completo,
            'icono' => 'fa-circle-plus',
        ]]);

        foreach ($bien->movimientos as $movimiento) {
            $destino = $movimiento->areaDestino->nombre ?? $movimiento->sedeDestino->nombre ?? '';
            $eventos->push([
                'fecha' => $movimiento->fecha_movimiento,
                'evento' => Movimiento::TIPOS[$movimiento->tipo],
                'detalle' => "A {$destino}".($movimiento->estado === 'aprobado' || $movimiento->estado === 'ejecutado' ? '' : ' · '.$movimiento->estado_badge['label']),
                'usuario' => $movimiento->usuario?->nombre_completo,
                'icono' => 'fa-right-left',
            ]);
        }

        foreach ($bien->mantenimientos as $mantenimiento) {
            $eventos->push([
                'fecha' => $mantenimiento->fecha_inicio,
                'evento' => 'Mantenimiento',
                'detalle' => ucfirst($mantenimiento->tipo).' · '.Str::limit($mantenimiento->descripcion, 60),
                'usuario' => $mantenimiento->usuario?->nombre_completo,
                'icono' => 'fa-screwdriver-wrench',
            ]);
        }

        if ($bien->baja) {
            $eventos->push([
                'fecha' => $bien->baja->fecha_baja,
                'evento' => 'Baja',
                'detalle' => 'Motivo: '.str_replace('_', ' ', $bien->baja->motivo).' · '.$bien->baja->estado_badge['label'],
                'usuario' => $bien->baja->usuario?->nombre_completo,
                'icono' => 'fa-ban',
            ]);
        }

        return $eventos->sortByDesc(fn (array $e) => $e['fecha']->timestamp ?? 0)->values();
    }

    /**
     * Cambios de estado del bien, tomados de la auditoría.
     *
     * @return Collection<int, array{fecha: Carbon, anterior: mixed, nuevo: mixed, usuario: ?string}>
     */
    private function historialDeEstados(Bien $bien): Collection
    {
        return Auditoria::with('usuario')
            ->where('tabla', 'bienes')
            ->where('registro_id', $bien->id)
            ->whereIn('accion', ['crear', 'actualizar'])
            ->whereNotNull('datos_nuevos->estado')
            ->latest('fecha')
            ->get()
            ->map(fn (Auditoria $registro) => [
                'fecha' => $registro->fecha,
                'anterior' => $registro->datos_anteriores['estado'] ?? null,
                'nuevo' => $registro->datos_nuevos['estado'] ?? null,
                'usuario' => $registro->usuario?->nombre_completo,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogosFormulario(): array
    {
        return [
            'categorias' => Categoria::where('estado', 'activo')->get(),
            'sedes' => Sede::where('estado', 'activo')->get(),
            'areas' => Area::where('estado', 'activo')->get(),
            'responsables' => Responsable::where('estado', 'activo')->get(),
        ];
    }
}
