<?php

namespace App\Http\Controllers;

use App\Models\Baja;
use App\Models\Bien;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Búsquedas de bienes mientras se escribe: el buscador global del encabezado
 * y el selector de bien de los formularios de solicitud.
 */
class BusquedaController extends Controller
{
    private const LIMITE = 8;

    /**
     * Buscador global: código de barras, código patrimonial, nombre o categoría.
     * Quien solo ve sus bienes, solo los encuentra a ellos.
     */
    public function global(Request $request): JsonResponse
    {
        $texto = trim((string) $request->query('q'));
        $usuario = $this->usuarioActual($request);

        if (mb_strlen($texto) < 2 || ! $this->puedeBuscar($usuario)) {
            return response()->json(['resultados' => []]);
        }

        $bienes = $this->visibles($usuario)
            ->with('categoria:id,nombre')
            ->where(fn (Builder $q) => $q
                ->where('codigo_barras', 'like', "%{$texto}%")
                ->orWhere('codigo_patrimonial', 'like', "%{$texto}%")
                ->orWhere('nombre', 'like', "%{$texto}%")
                ->orWhereHas('categoria', fn (Builder $c) => $c->where('nombre', 'like', "%{$texto}%")))
            ->orderByRaw('codigo_barras = ? desc', [mb_strtoupper($texto)])
            ->orderBy('nombre')
            ->limit(self::LIMITE)
            ->get();

        return response()->json([
            'resultados' => $bienes->map(fn (Bien $b) => [
                'nombre' => $b->nombre,
                'codigo' => $b->codigo_barras,
                'patrimonial' => $b->codigo_patrimonial ? "PAT {$b->codigo_patrimonial}" : null,
                'categoria' => $b->categoria?->nombre,
                'url' => route('bienes.show', $b),
            ])->values(),
        ]);
    }

    /**
     * Selector de bien de las solicitudes: solo bienes sobre los que el usuario
     * puede solicitar una baja o un movimiento.
     */
    public function bienesSolicitables(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'para' => ['required', 'in:bajas,movimientos'],
        ]);
        $usuario = $this->usuarioActual($request);
        $texto = trim($datos['q'] ?? '');

        $query = Bien::query()
            ->with(['categoria:id,nombre', 'sede:id,nombre', 'area:id,nombre'])
            ->where('estado', '!=', 'dado_de_baja')
            ->when(! $usuario->can("{$datos['para']}.crear"), fn (Builder $q) => $q->where('responsable_id', $usuario->responsable->id ?? 0))
            ->when($datos['para'] === 'bajas', fn (Builder $q) => $q->whereDoesntHave('bajas', fn (Builder $b) => $b->whereIn('estado', Baja::ESTADOS_ACTIVOS)))
            ->when($datos['para'] === 'movimientos', fn (Builder $q) => $q->whereDoesntHave('movimientos', fn (Builder $m) => $m->where('estado', 'solicitado')))
            ->when($texto !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s
                ->where('codigo_barras', 'like', "%{$texto}%")
                ->orWhere('codigo_patrimonial', 'like', "%{$texto}%")
                ->orWhere('nombre', 'like', "%{$texto}%")))
            ->orderBy('nombre')
            ->limit(self::LIMITE);

        return response()->json([
            'resultados' => $query->get()->map(fn (Bien $b) => self::bienParaSelector($b))->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function bienParaSelector(Bien $bien): array
    {
        return [
            'id' => $bien->id,
            'codigo' => $bien->codigo_barras,
            'nombre' => $bien->nombre,
            'categoria' => $bien->categoria?->nombre,
            'estado' => Bien::ESTADOS[$bien->estado],
            'foto' => $bien->url_fotografia,
            'sede_id' => $bien->sede_id,
            'area_id' => $bien->area_id,
            'ubicacion' => trim(($bien->sede->nombre ?? '').' / '.($bien->area->nombre ?? 'sin área')),
        ];
    }

    private function puedeBuscar(Usuario $usuario): bool
    {
        return $usuario->can('bienes.ver') || $usuario->can('mis_bienes.ver');
    }

    /**
     * @return Builder<Bien>
     */
    private function visibles(Usuario $usuario): Builder
    {
        return $usuario->can('bienes.ver')
            ? Bien::query()
            : Bien::query()->where('responsable_id', $usuario->responsable->id ?? 0);
    }
}
