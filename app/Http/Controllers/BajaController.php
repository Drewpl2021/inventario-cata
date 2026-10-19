<?php

namespace App\Http\Controllers;

use App\Http\Requests\BajaRequest;
use App\Models\Baja;
use App\Models\Bien;
use App\Services\AlmacenArchivos;
use App\Services\GestionBajas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BajaController extends Controller
{
    public function __construct(
        private readonly GestionBajas $gestionBajas,
        private readonly AlmacenArchivos $archivos,
    ) {}

    public function index(): View
    {
        $bajas = Baja::with(['bien', 'usuario', 'aprobador'])->latest('fecha_baja')->latest('id')->paginate(15);

        return view('bajas.index', compact('bajas'));
    }

    public function create(Request $request): View
    {
        // Bien preseleccionado (desde el detalle del bien o tras un error de validación).
        $bienId = old('bien_id', $request->input('bien_id'));
        $bien = $bienId ? $this->bienesSolicitables($request)->with(['categoria', 'area'])->find((int) $bienId) : null;

        return view('bajas.create', [
            'seleccionado' => $bien ? BusquedaController::bienParaSelector($bien) : null,
        ]);
    }

    public function store(BajaRequest $request): RedirectResponse
    {
        $bien = Bien::findOrFail($request->validated('bien_id'));
        $this->authorize('solicitar', [$bien, 'bajas']);

        $usuario = $this->usuarioActual($request);
        $data = $request->safe()->except('fotografia');

        // Solo quien dictamina bajas registra el documento de referencia y el valor residual.
        if (! $usuario->can('bajas.aprobar')) {
            $data['documento_referencia'] = null;
            $data['valor_residual'] = 0;
        }
        $data['valor_residual'] ??= 0;

        if ($request->hasFile('fotografia')) {
            $data['fotografia'] = $this->archivos->guardarSubido($request, 'fotografia', AlmacenArchivos::CARPETA_BAJAS);
        }

        $baja = $this->gestionBajas->registrar($usuario, $data);

        $mensaje = $baja->estado === 'aprobada'
            ? 'Baja del bien patrimonial registrada y procesada exitosamente.'
            : 'Su solicitud de baja fue enviada al Administrador para su dictamen y aprobación formal.';

        return redirect()->route($usuario->can('bajas.ver') ? 'bajas.index' : 'mis-bienes.index')->with('success', $mensaje);
    }

    public function aprobar(Request $request, Baja $baja): RedirectResponse
    {
        $datos = $request->validate([
            'documento_referencia' => ['nullable', 'string', 'max:100'],
            'valor_residual' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->gestionBajas->aprobar($baja, $this->usuarioActual($request), $datos);

        return back()->with('success', 'La solicitud de baja fue aprobada y el bien fue retirado del inventario activo.');
    }

    public function rechazar(Request $request, Baja $baja): RedirectResponse
    {
        $request->validate(['motivo_rechazo' => ['required', 'string', 'max:2000']]);

        $this->gestionBajas->rechazar($baja, $this->usuarioActual($request), $request->motivo_rechazo);

        return back()->with('success', 'La solicitud de baja fue rechazada.');
    }

    /**
     * Bienes sobre los que el usuario puede solicitar una baja: todos los activos
     * si tiene el permiso del módulo, o solo los suyos si es responsable.
     *
     * @return Builder<Bien>
     */
    private function bienesSolicitables(Request $request): Builder
    {
        $query = Bien::with('sede')
            ->where('estado', '!=', 'dado_de_baja')
            ->whereDoesntHave('bajas', fn ($q) => $q->whereIn('estado', Baja::ESTADOS_ACTIVOS));

        if (! $this->usuarioActual($request)->can('bajas.crear')) {
            $query->where('responsable_id', $this->usuarioActual($request)->responsable->id ?? 0);
        }

        return $query->orderBy('nombre');
    }
}
