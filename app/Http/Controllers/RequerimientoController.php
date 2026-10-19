<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequerimientoRequest;
use App\Models\Area;
use App\Models\Requerimiento;
use App\Models\Sede;
use App\Services\AlmacenArchivos;
use App\Services\Notificador;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequerimientoController extends Controller
{
    public function __construct(private readonly AlmacenArchivos $archivos) {}

    public function index(Request $request): View
    {
        $query = Requerimiento::with(['usuario', 'sede', 'area']);

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('codigo', 'like', "%{$buscar}%")
                    ->orWhere('tipo_requerimiento', 'like', "%{$buscar}%")
                    ->orWhere('descripcion', 'like', "%{$buscar}%")
                    ->orWhere('justificacion', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('prioridad')) {
            $query->where('prioridad', $request->prioridad);
        }

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        $requerimientos = $query->latest('fecha_solicitud')->paginate(15);

        // Contadores para métricas
        $total = Requerimiento::count();
        $pendientes = Requerimiento::where('estado', 'pendiente')->count();
        $enCotizacion = Requerimiento::where('estado', 'en_cotizacion')->count();
        $aprobados = Requerimiento::where('estado', 'aprobado')->count();
        $atendidos = Requerimiento::where('estado', 'atendido')->count();

        $sedes = Sede::where('estado', 'activo')->get();

        return view('requerimientos.index', compact(
            'requerimientos',
            'sedes',
            'total',
            'pendientes',
            'enCotizacion',
            'aprobados',
            'atendidos'
        ));
    }

    public static function generateNextCodigo(): string
    {
        $year = date('Y');
        $maxId = Requerimiento::max('id') ?? 0;
        $nextNum = $maxId + 1;
        $code = "REQ-{$year}-".sprintf('%04d', $nextNum);

        while (Requerimiento::where('codigo', $code)->exists()) {
            $nextNum++;
            $code = "REQ-{$year}-".sprintf('%04d', $nextNum);
        }

        return $code;
    }

    public function create(): View
    {
        $sedes = Sede::where('estado', 'activo')->get();
        $areas = Area::where('estado', 'activo')->get();
        $nextCodigo = self::generateNextCodigo();

        return view('requerimientos.create', compact('sedes', 'areas', 'nextCodigo'));
    }

    public function store(RequerimientoRequest $request): RedirectResponse
    {
        $this->authorize('create', Requerimiento::class);

        $data = $request->safe()->except('archivo_adjunto');
        $data['codigo'] = self::generateNextCodigo();
        $data['usuario_id'] = $this->usuarioActual($request)->id;
        $data['fecha_solicitud'] = now();
        $data['estado'] = 'pendiente';

        if ($request->hasFile('archivo_adjunto')) {
            $data['archivo_nombre_original'] = mb_substr($request->file('archivo_adjunto')->getClientOriginalName(), 0, 255);
            $data['archivo_adjunto'] = $this->archivos->guardarSubido($request, 'archivo_adjunto', AlmacenArchivos::CARPETA_REQUERIMIENTOS);
        }

        $requerimiento = Requerimiento::create($data);
        app(Notificador::class)->solicitudRegistrada($requerimiento);

        $destino = $this->usuarioActual($request)->can('requerimientos.ver')
            ? redirect()->route('requerimientos.index')
            : redirect()->route('requerimientos.show', $requerimiento);

        return $destino->with('success', "Requerimiento {$requerimiento->codigo} registrado exitosamente.");
    }

    public function show(Requerimiento $requerimiento): View
    {
        $this->authorize('view', $requerimiento);

        $requerimiento->load(['usuario', 'sede', 'area', 'aprobador']);

        return view('requerimientos.show', compact('requerimiento'));
    }

    /**
     * Avanza el requerimiento según el flujo:
     * pendiente → en_cotizacion → aprobado → atendido, o pendiente/en_cotizacion → rechazado.
     */
    public function updateEstado(Request $request, Requerimiento $requerimiento): RedirectResponse
    {
        $this->authorize('cambiarEstado', $requerimiento);

        $request->validate([
            'estado' => ['required', Rule::in($requerimiento->estadosSiguientes())],
            'motivo_rechazo' => ['required_if:estado,rechazado', 'nullable', 'string', 'max:2000'],
        ], [
            'estado.in' => 'El requerimiento no puede pasar de "'.Requerimiento::ESTADOS[$requerimiento->estado].'" al estado elegido.',
            'motivo_rechazo.required_if' => 'Indique el motivo del rechazo.',
        ]);

        $updateData = ['estado' => $request->estado];

        if (in_array($request->estado, ['aprobado', 'rechazado'], true)) {
            $updateData['aprobado_por'] = $this->usuarioActual($request)->id;
            $updateData['fecha_resolucion'] = now();
        }

        if ($request->estado === 'rechazado') {
            $updateData['motivo_rechazo'] = $request->motivo_rechazo;
        }

        $requerimiento->update($updateData);
        app(Notificador::class)->solicitudResuelta($requerimiento, $this->usuarioActual($request));

        return back()->with('success', "Estado del requerimiento {$requerimiento->codigo} actualizado a ".Requerimiento::ESTADOS[$request->estado].'.');
    }

    public function destroy(Requerimiento $requerimiento): RedirectResponse
    {
        $this->authorize('delete', $requerimiento);

        $requerimiento->delete();
        $this->archivos->eliminar($requerimiento->archivo_adjunto);

        return redirect()->route($this->usuarioActual()->can('requerimientos.ver') ? 'requerimientos.index' : 'dashboard')
            ->with('success', 'Requerimiento eliminado exitosamente.');
    }
}
