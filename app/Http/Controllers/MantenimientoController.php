<?php

namespace App\Http\Controllers;

use App\Models\Bien;
use App\Models\Mantenimiento;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MantenimientoController extends Controller
{
    public function index(Request $request): View
    {
        $mantenimientos = Mantenimiento::with(['bien', 'usuario'])
            ->when($request->filled('buscar'), fn ($q) => $q->where(fn ($s) => $s
                ->where('descripcion', 'like', "%{$request->buscar}%")
                ->orWhere('proveedor', 'like', "%{$request->buscar}%")
                ->orWhereHas('bien', fn ($b) => $b->where('nombre', 'like', "%{$request->buscar}%")->orWhere('codigo_barras', 'like', "%{$request->buscar}%"))))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('mantenimientos.index', compact('mantenimientos'));
    }

    public function create(): View
    {
        $bienes = Bien::where('estado', '!=', 'dado_de_baja')->orderBy('nombre')->get();

        return view('mantenimientos.create', compact('bienes'));
    }

    /**
     * Quien gestiona mantenimientos (permiso "mantenimientos.editar") registra la
     * orden directamente; los demás envían una solicitud pendiente.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bien_id' => ['required', 'exists:bienes,id'],
            'tipo' => ['required', 'in:preventivo,correctivo'],
            'descripcion' => ['required', 'string'],
            'proveedor' => ['nullable', 'string', 'max:150'],
            'fecha_inicio' => ['required', 'date'],
            'costo' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['nullable', 'in:pendiente,en_proceso,finalizado,cancelado'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $gestiona = $this->usuarioActual($request)->can('mantenimientos.editar');
        $data['usuario_id'] = $this->usuarioActual($request)->id;
        $data['costo'] ??= 0;

        if (! $gestiona) {
            $data['estado'] = 'pendiente';
            $data['costo'] = 0;
        }

        DB::transaction(function () use ($data, $gestiona) {
            $mantenimiento = Mantenimiento::create($data);

            if ($gestiona && in_array($mantenimiento->estado, ['pendiente', 'en_proceso'], true)) {
                Bien::findOrFail($mantenimiento->bien_id)->update(['estado' => 'mantenimiento']);
            }
        });

        $msg = $gestiona
            ? 'Mantenimiento registrado exitosamente.'
            : 'Su solicitud de mantenimiento fue enviada al Administrador para su evaluación.';

        return redirect()->route('mantenimientos.index')->with('success', $msg);
    }

    public function update(Request $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', 'in:pendiente,en_proceso,finalizado,cancelado'],
            'fecha_fin' => ['nullable', 'date'],
            'costo' => ['nullable', 'numeric', 'min:0'],
            'proveedor' => ['nullable', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($mantenimiento, $data) {
            $mantenimiento->update($data);
            $bien = Bien::findOrFail($mantenimiento->bien_id);

            if ($bien->estaDadoDeBaja()) {
                return;
            }

            if ($mantenimiento->estado === 'finalizado') {
                $bien->update(['estado' => 'bueno']);
            } elseif ($mantenimiento->estado === 'en_proceso') {
                $bien->update(['estado' => 'mantenimiento']);
            }
        });

        return redirect()->route('mantenimientos.index')->with('success', 'Mantenimiento actualizado exitosamente.');
    }
}
