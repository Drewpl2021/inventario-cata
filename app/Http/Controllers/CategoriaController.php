<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    public function index(Request $request): View
    {
        $categorias = Categoria::withCount('bienes')
            ->when($request->filled('buscar'), fn ($q) => $q->where('nombre', 'like', "%{$request->buscar}%"))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('categorias.index', compact('categorias'));
    }

    public function store(Request $request): RedirectResponse
    {
        Categoria::create($this->validar($request));

        return redirect()->route('categorias.index')->with('success', 'Categoría registrada exitosamente.');
    }

    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $categoria->update($this->validar($request, $categoria));

        return redirect()->route('categorias.index')->with('success', 'Categoría actualizada exitosamente.');
    }

    /**
     * Las categorías con bienes se desactivan; las vacías se retiran con soft delete.
     */
    public function destroy(Categoria $categoria): RedirectResponse
    {
        if ($categoria->bienes()->exists()) {
            return back()->with('error', 'La categoría tiene bienes registrados. Desactívela en lugar de eliminarla.');
        }

        $categoria->delete();

        return redirect()->route('categorias.index')->with('success', 'Categoría eliminada exitosamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Categoria $categoria = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias')->ignore($categoria)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'in:activo,inactivo'],
        ]);
    }
}
