<?php

namespace App\Http\Controllers;

use App\Models\Baja;
use App\Models\Movimiento;
use App\Models\Requerimiento;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Bandeja de aprobación: solicitudes pendientes de bajas, movimientos y
 * requerimientos que el usuario puede resolver. Las acciones usan las rutas
 * de cada módulo (aprobar, rechazar, cambiar estado).
 */
class AprobacionController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $this->usuarioActual($request);

        $pestanas = collect([
            'bajas' => ['Bajas', 'fa-ban', $usuario->can('bajas.aprobar')],
            'movimientos' => ['Movimientos', 'fa-right-left', $usuario->can('movimientos.aprobar')],
            'requerimientos' => ['Requerimientos', 'fa-clipboard-list', $usuario->can('requerimientos.aprobar')],
        ])->filter(fn (array $pestana) => $pestana[2]);

        $pendientes = [
            'bajas' => $pestanas->has('bajas')
                ? Baja::with(['bien.categoria', 'bien.sede', 'bien.area', 'usuario'])->where('estado', 'solicitada')->oldest('id')->get()
                : collect(),
            'movimientos' => $pestanas->has('movimientos')
                ? Movimiento::with(['bien', 'sedeOrigen', 'areaOrigen', 'sedeDestino', 'areaDestino', 'responsableNuevo', 'usuario'])->where('estado', 'solicitado')->oldest('id')->get()
                : collect(),
            'requerimientos' => $pestanas->has('requerimientos')
                ? Requerimiento::with(['usuario', 'sede', 'area'])->whereIn('estado', ['pendiente', 'en_cotizacion'])->oldest('fecha_solicitud')->get()
                : collect(),
        ];

        $activa = $pestanas->has($request->query('pestana')) ? $request->query('pestana') : $pestanas->keys()->first();

        return view('aprobaciones.index', compact('pestanas', 'pendientes', 'activa'));
    }
}
