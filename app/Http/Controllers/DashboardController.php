<?php

namespace App\Http\Controllers;

use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Mantenimiento;
use App\Models\Movimiento;
use App\Models\Sede;
use App\Models\Usuario;
use App\Support\MenuLateral;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Colores de la dona "Distribución por sede" (gama institucional). */
    private const COLORES_SEDES = ['#1d4ed8', '#0b3b70', '#38bdf8', '#5a8ccc', '#93c5fd', '#134682'];

    public function index(Request $request): View
    {
        /** @var Usuario $usuario */
        $usuario = $this->usuarioActual($request);

        // Quien solo ve sus propios bienes tiene su propio inicio.
        if (! $usuario->can('bienes.ver')) {
            return $this->inicioDelResponsable($usuario);
        }

        $totales = $this->totales(Bien::query());

        $sedes = Sede::withCount(['bienes' => fn (Builder $q) => $q->where('estado', '!=', 'dado_de_baja')])->orderBy('codigo')->get();
        $totalActivos = max(1, $sedes->sum('bienes_count'));
        $distribucion = $sedes->values()->map(fn (Sede $sede, int $i) => [
            'nombre' => $sede->nombre,
            'total' => $sede->bienes_count,
            'porcentaje' => round($sede->bienes_count / $totalActivos * 100, 1),
            'color' => self::COLORES_SEDES[$i % count(self::COLORES_SEDES)],
        ]);

        return view('dashboard', [
            'usuario' => $usuario,
            'totales' => $totales,
            'distribucion' => $distribucion,
            'ultimosMovimientos' => Movimiento::with(['bien', 'sedeDestino'])->latest('fecha_movimiento')->latest('id')->take(5)->get(),
            'porAprobar' => MenuLateral::pendientesDeAprobar($usuario),
            'porCategoria' => Categoria::withCount(['bienes' => fn (Builder $q) => $q->where('estado', '!=', 'dado_de_baja')])->orderByDesc('bienes_count')->take(5)->get(),
            'mantenimientosEnCurso' => Mantenimiento::with('bien')->whereIn('estado', ['pendiente', 'en_proceso'])->latest()->take(5)->get(),
            'valorTotal' => Bien::where('estado', '!=', 'dado_de_baja')->sum('valor_adquisicion'),
        ]);
    }

    private function inicioDelResponsable(Usuario $usuario): View
    {
        $responsableId = $usuario->responsable?->id;
        $misBienes = Bien::where('responsable_id', $responsableId ?? 0);

        return view('dashboard-usuario', [
            'usuario' => $usuario,
            'tieneResponsable' => $responsableId !== null,
            'totales' => $this->totales(clone $misBienes),
            'bienes' => (clone $misBienes)->with(['categoria', 'area'])->where('estado', '!=', 'dado_de_baja')->orderBy('nombre')->take(5)->get(),
        ]);
    }

    /**
     * @param  Builder<Bien>  $bienes
     * @return array{total: int, buen_estado: int, mantenimiento: int, dados_de_baja: int}
     */
    private function totales(Builder $bienes): array
    {
        $porEstado = $bienes->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return [
            'total' => (int) $porEstado->sum(),
            'buen_estado' => (int) ($porEstado['nuevo'] ?? 0) + (int) ($porEstado['bueno'] ?? 0),
            'mantenimiento' => (int) ($porEstado['mantenimiento'] ?? 0),
            'dados_de_baja' => (int) ($porEstado['dado_de_baja'] ?? 0),
        ];
    }
}
