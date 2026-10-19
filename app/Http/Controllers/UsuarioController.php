<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\Area;
use App\Models\Responsable;
use App\Models\Role;
use App\Models\Sede;
use App\Models\Usuario;
use App\Services\GestionUsuarios;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function __construct(private readonly GestionUsuarios $gestionUsuarios) {}

    public function index(Request $request): View
    {
        $query = Usuario::with(['rol', 'sede', 'area', 'responsable']);

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$buscar}%")
                ->orWhere('apellido', 'like', "%{$buscar}%")
                ->orWhere('email', 'like', "%{$buscar}%"));
        }

        if ($request->filled('rol_id')) {
            $query->where('rol_id', $request->rol_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $usuarios = $query->orderBy('nombre')->paginate(15)->withQueryString();
        $roles = Role::orderBy('nombre')->get();

        return view('usuarios.index', compact('usuarios', 'roles'));
    }

    public function create(): View
    {
        return view('usuarios.form', [...$this->catalogos(), 'usuario' => new Usuario(['estado' => 'activo'])]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        [$usuario, $passwordTemporal] = $this->gestionUsuarios->crear($request->validated());

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->nombre_completo} creado exitosamente.")
            ->with('password_temporal', ['nombre' => $usuario->nombre_completo, 'email' => $usuario->email, 'password' => $passwordTemporal, 'rol' => $usuario->rol?->nombre]);
    }

    public function edit(Usuario $usuario): View
    {
        return view('usuarios.form', [...$this->catalogos($usuario), 'usuario' => $usuario->load('responsable')]);
    }

    public function update(UsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        $this->gestionUsuarios->actualizar($usuario, $request->validated(), $this->usuarioActual($request));

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado exitosamente.');
    }

    public function cambiarEstado(Request $request, Usuario $usuario): RedirectResponse
    {
        $request->validate(['estado' => ['required', 'in:activo,inactivo']]);

        $this->gestionUsuarios->cambiarEstado($usuario, $request->estado, $this->usuarioActual($request));

        $mensaje = $request->estado === 'activo'
            ? "La cuenta de {$usuario->nombre_completo} fue activada."
            : "La cuenta de {$usuario->nombre_completo} fue desactivada y sus sesiones abiertas se cerraron.";

        return back()->with('success', $mensaje);
    }

    public function restablecerPassword(Request $request, Usuario $usuario): RedirectResponse
    {
        $passwordTemporal = $this->gestionUsuarios->restablecerPassword($usuario, $this->usuarioActual($request));

        return back()
            ->with('success', "Se generó una contraseña temporal para {$usuario->nombre_completo}.")
            ->with('password_temporal', ['nombre' => $usuario->nombre_completo, 'email' => $usuario->email, 'password' => $passwordTemporal, 'rol' => $usuario->rol?->nombre]);
    }

    public function destroy(Request $request, Usuario $usuario): RedirectResponse
    {
        $this->gestionUsuarios->eliminar($usuario, $this->usuarioActual($request));

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado exitosamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(?Usuario $usuario = null): array
    {
        $roles = Role::activos()->orderBy('nombre')->get();

        return [
            'roles' => $roles,
            'rolesConResponsable' => $roles->filter->requiereResponsable()->pluck('id')->values(),
            'sedes' => Sede::where('estado', 'activo')->orderBy('nombre')->get(),
            'areas' => Area::where('estado', 'activo')->orderBy('nombre')->get(),
            // Responsables sin cuenta (o el ya vinculado a este usuario).
            'responsablesLibres' => Responsable::where('estado', 'activo')
                ->where(fn ($q) => $q->whereNull('usuario_id')->when($usuario?->id, fn ($q, int $usuarioId) => $q->orWhere('usuario_id', $usuarioId)))
                ->orderBy('apellidos')
                ->get(),
        ];
    }
}
