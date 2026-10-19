<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SedeController extends Controller
{
    public function index(Request $request): View
    {
        $sedes = Sede::withCount(['bienes', 'areas'])
            ->when($request->filled('buscar'), fn ($q) => $q->where(fn ($s) => $s
                ->where('nombre', 'like', "%{$request->buscar}%")
                ->orWhere('codigo', 'like', "%{$request->buscar}%")))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderBy('codigo')
            ->paginate(15)
            ->withQueryString();

        return view('sedes.index', compact('sedes'));
    }

    public function store(Request $request): RedirectResponse
    {
        Sede::create($this->validar($request));

        return redirect()->route('sedes.index')->with('success', 'Sede registrada exitosamente.');
    }

    public function update(Request $request, Sede $sede): RedirectResponse
    {
        $sede->update($this->validar($request, $sede));

        return redirect()->route('sedes.index')->with('success', 'Sede actualizada exitosamente.');
    }

    /**
     * Las sedes con áreas, bienes o responsables se desactivan; las vacías se
     * retiran con soft delete.
     */
    public function destroy(Sede $sede): RedirectResponse
    {
        if ($sede->areas()->exists() || $sede->bienes()->exists() || $sede->responsables()->exists()) {
            return back()->with('error', 'La sede tiene áreas, bienes o responsables registrados. Desactívela en lugar de eliminarla.');
        }

        $sede->delete();

        return redirect()->route('sedes.index')->with('success', 'Sede eliminada exitosamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Sede $sede = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('sedes')->ignore($sede)],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'estado' => ['nullable', 'in:activo,inactivo'],
        ]);
    }
}
