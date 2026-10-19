<?php

namespace App\Http\Controllers;

use App\Models\Bien;
use App\Models\Documento;
use App\Models\Usuario;
use App\Rules\ArchivoPermitido;
use App\Services\AlmacenArchivos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DocumentoController extends Controller
{
    public function __construct(private readonly AlmacenArchivos $archivos) {}

    public function index(Request $request): View
    {
        $documentos = Documento::with(['bien', 'usuario'])
            ->when($request->filled('buscar'), fn ($q) => $q->where(fn ($s) => $s
                ->where('nombre', 'like', "%{$request->buscar}%")
                ->orWhereHas('bien', fn ($b) => $b->where('nombre', 'like', "%{$request->buscar}%")->orWhere('codigo_barras', 'like', "%{$request->buscar}%"))))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();
        $bienes = Bien::all();
        $usuarios = Usuario::where('estado', 'activo')->get();

        return view('documentos.index', compact('documentos', 'bienes', 'usuarios'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bien_id' => 'required|exists:bienes,id',
            'tipo' => 'required|in:factura,acta,garantia,informe,baja,otro',
            'nombre' => 'required|string|max:255',
            'archivo' => ['required', ArchivoPermitido::pdf()],
        ], [
            'archivo.required' => 'Adjunte el documento en PDF.',
        ]);

        Documento::create([
            ...Arr::except($data, 'archivo'),
            'ruta' => $this->archivos->guardarPdf($request->file('archivo'), AlmacenArchivos::CARPETA_DOCUMENTOS),
            'usuario_id' => $this->usuarioActual($request)->id,
        ]);

        return redirect()->route('documentos.index')->with('success', 'Documento registrado exitosamente.');
    }

    public function destroy(Documento $documento): RedirectResponse
    {
        $documento->delete();

        // El archivo se borra solo si ningún otro registro lo usa (p. ej. el comprobante del bien).
        $enUso = Documento::where('ruta', $documento->ruta)->exists()
            || Bien::withTrashed()->where('comprobante_archivo', $documento->ruta)->exists();
        if (! $enUso) {
            $this->archivos->eliminar($documento->ruta);
        }

        return redirect()->route('documentos.index')->with('success', 'Documento eliminado exitosamente.');
    }
}
