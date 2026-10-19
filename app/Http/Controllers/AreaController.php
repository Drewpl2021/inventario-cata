<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Sede;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    public function index(Request $request): View
    {
        $areas = Area::with(['sede'])->withCount(['bienes', 'responsables'])
            ->when($request->filled('buscar'), fn ($q) => $q->where(fn ($s) => $s
                ->where('nombre', 'like', "%{$request->buscar}%")
                ->orWhere('codigo', 'like', "%{$request->buscar}%")
                ->orWhere('ubicacion', 'like', "%{$request->buscar}%")))
            ->when($request->filled('sede_id'), fn ($q) => $q->where('sede_id', $request->sede_id))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderBy('sede_id')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();
        $sedes = Sede::where('estado', 'activo')->get();

        return view('areas.index', compact('areas', 'sedes'));
    }

    public function store(Request $request): RedirectResponse
    {
        Area::create($this->validar($request));

        return redirect()->route('areas.index')->with('success', 'Área registrada exitosamente.');
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $datos = $this->validar($request, $area);

        // Cambiar de sede dejaría bienes y responsables con un área de otra sede.
        $cambiaDeSede = (int) $datos['sede_id'] !== (int) $area->sede_id;
        if ($cambiaDeSede && ($area->bienes()->exists() || $area->responsables()->exists())) {
            return back()->with('error', 'No se puede cambiar la sede de un área que tiene bienes o responsables asignados.');
        }

        $area->update($datos);

        return redirect()->route('areas.index')->with('success', 'Área actualizada exitosamente.');
    }

    /**
     * Las áreas con bienes o responsables se desactivan; las vacías se retiran con soft delete.
     */
    public function destroy(Area $area): RedirectResponse
    {
        if ($area->bienes()->exists() || $area->responsables()->exists()) {
            return back()->with('error', 'El área tiene bienes o responsables asignados. Desactívela en lugar de eliminarla.');
        }

        $area->delete();

        return redirect()->route('areas.index')->with('success', 'Área eliminada exitosamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Area $area = null): array
    {
        return $request->validate([
            'sede_id' => ['required', 'exists:sedes,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'codigo' => ['nullable', 'string', 'max:30', Rule::unique('areas')->where('sede_id', $request->input('sede_id'))->ignore($area)],
            'tipo' => ['nullable', 'string', 'max:80'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'in:activo,inactivo'],
        ]);
    }
}
