<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $auditorias = Auditoria::with('usuario')
            ->when($request->filled('tabla'), fn ($q) => $q->where('tabla', $request->tabla))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->accion))
            ->when($request->filled('usuario_id'), fn ($q) => $q->where('usuario_id', $request->usuario_id))
            ->when($request->filled('registro_id'), fn ($q) => $q->where('registro_id', $request->registro_id))
            ->when($request->filled('desde'), fn ($q) => $q->where('fecha', '>=', $request->date('desde')?->startOfDay()))
            ->when($request->filled('hasta'), fn ($q) => $q->where('fecha', '<=', $request->date('hasta')?->endOfDay()))
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('auditoria.index', [
            'auditorias' => $auditorias,
            'tablas' => Auditoria::query()->distinct()->orderBy('tabla')->pluck('tabla'),
            'acciones' => Auditoria::query()->distinct()->orderBy('accion')->pluck('accion'),
            'usuarios' => Usuario::withTrashed()->orderBy('nombre')->get(['id', 'nombre', 'apellido']),
        ]);
    }
}
