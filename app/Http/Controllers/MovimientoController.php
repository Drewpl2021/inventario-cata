<?php

namespace App\Http\Controllers;

use App\Http\Requests\MovimientoRequest;
use App\Models\Area;
use App\Models\Bien;
use App\Models\Movimiento;
use App\Models\Responsable;
use App\Models\Sede;
use App\Services\GestionMovimientos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    public function __construct(private readonly GestionMovimientos $gestionMovimientos) {}

    public function index(Request $request): View
    {
        $query = Movimiento::with(['bien', 'sedeOrigen', 'areaOrigen', 'sedeDestino', 'areaDestino', 'responsableAnterior', 'responsableNuevo', 'usuario', 'aprobador']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $movimientos = $query->latest('fecha_movimiento')->latest('id')->paginate(15)->withQueryString();
        $pendientes = Movimiento::where('estado', 'solicitado')->count();

        return view('movimientos.index', compact('movimientos', 'pendientes'));
    }

    public function create(Request $request): View
    {
        // Bien preseleccionado (desde el detalle del bien o tras un error de validación).
        $bien = null;
        if ($bienId = old('bien_id', $request->input('bien_id'))) {
            $bien = Bien::with(['sede', 'area', 'categoria'])->where('estado', '!=', 'dado_de_baja')->find((int) $bienId);
            $bien = $bien && $this->usuarioActual($request)->can('solicitar', [$bien, 'movimientos']) ? $bien : null;
        }

        return view('movimientos.create', [
            'seleccionado' => $bien ? BusquedaController::bienParaSelector($bien) : null,
            'sedes' => Sede::where('estado', 'activo')->orderBy('nombre')->get(),
            'areas' => Area::where('estado', 'activo')->get(),
            'responsables' => Responsable::where('estado', 'activo')->get(),
        ]);
    }

    public function store(MovimientoRequest $request): RedirectResponse
    {
        $bien = Bien::findOrFail($request->validated('bien_id'));
        $this->authorize('solicitar', [$bien, 'movimientos']);

        $usuario = $this->usuarioActual($request);
        $movimiento = $this->gestionMovimientos->solicitar($usuario, $request->validated());

        $mensaje = $movimiento->estado === 'aprobado'
            ? 'Movimiento registrado y aplicado al bien.'
            : 'Solicitud de movimiento registrada. El bien se actualizará cuando sea aprobada.';

        return redirect()->route($usuario->can('movimientos.ver') ? 'movimientos.index' : 'mis-bienes.index')->with('success', $mensaje);
    }

    public function aprobar(Request $request, Movimiento $movimiento): RedirectResponse
    {
        $this->gestionMovimientos->aprobar($movimiento, $this->usuarioActual($request));

        return back()->with('success', 'Movimiento aprobado. Se actualizaron la sede, el área y el responsable del bien.');
    }

    public function rechazar(Request $request, Movimiento $movimiento): RedirectResponse
    {
        $request->validate(['motivo_rechazo' => ['required', 'string', 'max:2000']]);

        $this->gestionMovimientos->rechazar($movimiento, $this->usuarioActual($request), $request->motivo_rechazo);

        return back()->with('success', 'La solicitud de movimiento fue rechazada.');
    }

    public function ejecutar(Movimiento $movimiento): RedirectResponse
    {
        $this->gestionMovimientos->marcarEjecutado($movimiento);

        return back()->with('success', 'Movimiento marcado como ejecutado.');
    }
}
