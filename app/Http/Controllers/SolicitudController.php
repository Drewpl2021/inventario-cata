<?php

namespace App\Http\Controllers;

use App\Models\Baja;
use App\Models\Movimiento;
use App\Models\Requerimiento;
use App\Support\EstadoSolicitud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * "Mis solicitudes": bajas, movimientos y requerimientos registrados por el
 * usuario, con su estado (Pendiente, En proceso, Aprobada, Rechazada).
 */
class SolicitudController extends Controller
{
    public const PESTANAS = [
        'todas' => 'Todas',
        'bajas' => 'Dar de baja',
        'movimientos' => 'Movimiento',
        'requerimientos' => 'Requerimiento',
    ];

    public function index(Request $request): View
    {
        $pestana = array_key_exists($request->query('pestana'), self::PESTANAS) ? $request->query('pestana') : 'todas';
        $usuarioId = $this->usuarioActual($request)->id;

        /** @var Collection<int, array<string, mixed>> $solicitudes */
        $solicitudes = collect();

        if (in_array($pestana, ['todas', 'bajas'], true)) {
            $solicitudes = $solicitudes->concat(Baja::with('bien')->where('usuario_id', $usuarioId)->get()->map(fn (Baja $b) => [
                'fecha' => $b->created_at ?? $b->fecha_baja,
                'tipo' => 'Dar de baja',
                'icono' => 'fa-ban',
                'descripcion' => ($b->bien->nombre ?? 'Bien').' · '.($b->bien->codigo_barras ?? ''),
                'detalle' => 'Motivo: '.str_replace('_', ' ', $b->motivo),
                'estado' => EstadoSolicitud::de($b),
                'motivo_rechazo' => $b->motivo_rechazo,
                'url' => $b->bien ? route('bienes.show', $b->bien) : null,
            ]));
        }

        if (in_array($pestana, ['todas', 'movimientos'], true)) {
            $solicitudes = $solicitudes->concat(Movimiento::with(['bien', 'sedeDestino', 'areaDestino'])->where('usuario_id', $usuarioId)->get()->map(fn (Movimiento $m) => [
                'fecha' => $m->fecha_movimiento,
                'tipo' => 'Movimiento',
                'icono' => 'fa-right-left',
                'descripcion' => ($m->bien->nombre ?? 'Bien').' · '.($m->bien->codigo_barras ?? ''),
                'detalle' => 'Hacia: '.($m->sedeDestino->nombre ?? '').($m->areaDestino ? ' / '.$m->areaDestino->nombre : ''),
                'estado' => EstadoSolicitud::de($m),
                'motivo_rechazo' => $m->motivo_rechazo,
                'url' => $m->bien ? route('bienes.show', $m->bien) : null,
            ]));
        }

        if (in_array($pestana, ['todas', 'requerimientos'], true)) {
            $solicitudes = $solicitudes->concat(Requerimiento::where('usuario_id', $usuarioId)->get()->map(fn (Requerimiento $r) => [
                'fecha' => $r->fecha_solicitud,
                'tipo' => 'Requerimiento',
                'icono' => 'fa-clipboard-list',
                'descripcion' => "{$r->codigo} · {$r->tipo_requerimiento}",
                'detalle' => str($r->descripcion)->limit(80)->toString(),
                'estado' => EstadoSolicitud::de($r),
                'motivo_rechazo' => $r->motivo_rechazo,
                'url' => route('requerimientos.show', $r),
            ]));
        }

        $ordenadas = $solicitudes->sortByDesc('fecha')->values();
        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $paginadas = new LengthAwarePaginator($ordenadas->forPage($pagina, 15)->values(), $ordenadas->count(), 15, $pagina, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('solicitudes.index', [
            'solicitudes' => $paginadas,
            'pestana' => $pestana,
            'pestanas' => self::PESTANAS,
        ]);
    }
}
