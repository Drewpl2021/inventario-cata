<?php

namespace App\Http\Controllers;

use App\Http\Requests\RolRequest;
use App\Models\Modulo;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Roles y su matriz de permisos (módulo × acción).
 * - Los roles del sistema no se eliminan ni se renombran.
 * - Al Administrador no se le pueden quitar permisos ni desactivar.
 */
class RolController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount(['usuarios', 'permisos'])->orderByDesc('es_sistema')->orderBy('nombre')->get();

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('roles.form', ['rol' => new Role(['estado' => 'activo']), 'modulos' => $this->modulos(), 'seleccionados' => []]);
    }

    public function store(RolRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $rol = Role::create([
                'nombre' => $request->validated('nombre'),
                'descripcion' => $request->validated('descripcion'),
                'es_sistema' => false,
                'estado' => 'activo',
            ]);

            $rol->sincronizarPermisos($request->validated('permisos', []));
        });

        return redirect()->route('roles.index')->with('success', 'Rol creado exitosamente.');
    }

    public function edit(Role $rol): View
    {
        return view('roles.form', [
            'rol' => $rol,
            'modulos' => $this->modulos(),
            'seleccionados' => $rol->permisos()->pluck('permisos.id')->all(),
        ]);
    }

    public function update(RolRequest $request, Role $rol): RedirectResponse
    {
        if ($rol->es_sistema && $request->validated('nombre') !== $rol->nombre) {
            return back()->withInput()->withErrors(['nombre' => 'Los roles del sistema no se pueden renombrar.']);
        }

        DB::transaction(function () use ($request, $rol) {
            $rol->update(['nombre' => $request->validated('nombre'), 'descripcion' => $request->validated('descripcion')]);
            $rol->sincronizarPermisos($request->validated('permisos', []));
        });

        return redirect()->route('roles.index')->with('success', "Rol {$rol->nombre} actualizado. Los permisos se aplican de inmediato.");
    }

    public function cambiarEstado(Request $request, Role $rol): RedirectResponse
    {
        $request->validate(['estado' => ['required', 'in:activo,inactivo']]);

        if ($rol->esAdministrador() && $request->estado !== 'activo') {
            return back()->with('error', 'El rol Administrador no se puede desactivar.');
        }

        if ($request->estado !== 'activo' && $this->usuarioActual($request)->rol_id === $rol->id) {
            return back()->with('error', 'No puede desactivar el rol con el que está trabajando.');
        }

        $rol->update(['estado' => $request->estado]);

        return back()->with('success', $request->estado === 'activo'
            ? "Rol {$rol->nombre} activado."
            : "Rol {$rol->nombre} desactivado. Sus usuarios ya no pueden ingresar al sistema.");
    }

    public function destroy(Role $rol): RedirectResponse
    {
        if ($rol->es_sistema) {
            return back()->with('error', 'Los roles del sistema no se pueden eliminar.');
        }

        if (Usuario::withTrashed()->where('rol_id', $rol->id)->exists()) {
            return back()->with('error', 'El rol tiene usuarios asignados. Reasígnelos o desactive el rol.');
        }

        $rol->permisos()->detach();
        $rol->delete();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado exitosamente.');
    }

    /**
     * @return Collection<int, Modulo>
     */
    private function modulos(): Collection
    {
        return Modulo::with('permisos')->orderBy('orden')->get();
    }
}
