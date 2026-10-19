<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResponsableRequest;
use App\Models\Area;
use App\Models\Responsable;
use App\Models\Sede;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResponsableController extends Controller
{
    public function index(Request $request): View
    {
        $responsables = Responsable::with(['area', 'sede', 'usuario'])->withCount('bienes')
            ->when($request->filled('buscar'), fn ($q) => $q->where(fn ($s) => $s
                ->where('nombres', 'like', "%{$request->buscar}%")
                ->orWhere('apellidos', 'like', "%{$request->buscar}%")
                ->orWhere('dni', 'like', "%{$request->buscar}%")
                ->orWhere('cargo', 'like', "%{$request->buscar}%")))
            ->when($request->filled('sede_id'), fn ($q) => $q->where('sede_id', $request->sede_id))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderBy('apellidos')
            ->paginate(15)
            ->withQueryString();
        $areas = Area::where('estado', 'activo')->get();
        $sedes = Sede::where('estado', 'activo')->get();

        return view('responsables.index', compact('responsables', 'areas', 'sedes'));
    }

    public function store(ResponsableRequest $request): RedirectResponse
    {
        Responsable::create($request->validated());

        return redirect()->route('responsables.index')->with('success', 'Responsable registrado exitosamente.');
    }

    public function update(ResponsableRequest $request, Responsable $responsable): RedirectResponse
    {
        $responsable->update($request->validated());

        return redirect()->route('responsables.index')->with('success', 'Responsable actualizado exitosamente.');
    }

    /**
     * Los responsables con bienes o con cuenta de usuario se desactivan; los demás
     * se retiran con soft delete.
     */
    public function destroy(Responsable $responsable): RedirectResponse
    {
        if ($responsable->bienes()->exists() || $responsable->usuario_id) {
            return back()->with('error', 'El responsable tiene bienes a su cargo o una cuenta de usuario vinculada. Desactívelo en lugar de eliminarlo.');
        }

        $responsable->delete();

        return redirect()->route('responsables.index')->with('success', 'Responsable eliminado exitosamente.');
    }
}
