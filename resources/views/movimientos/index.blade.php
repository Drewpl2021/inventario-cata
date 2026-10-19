@extends('layouts.app')

@section('title', 'Movimientos')
@section('page_header', 'Histórico de Movimientos de Bienes')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Registro de Movimientos y Traslados</h1>
            <p class="text-xs text-slate-500 mt-0.5">La sede, el área y el responsable del bien cambian solo cuando el movimiento se aprueba.</p>
        </div>
        @can('movimientos.crear')
            <a href="{{ route('movimientos.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold transition shadow-md shadow-indigo-500/20 flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> {{ Auth::user()->can('movimientos.aprobar') ? 'Registrar Movimiento' : 'Solicitar Movimiento' }}
            </a>
        @endcan
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @foreach(['' => 'Todos', 'solicitado' => 'Pendientes', 'aprobado' => 'Aprobados', 'ejecutado' => 'Ejecutados', 'rechazado' => 'Rechazados'] as $valor => $etiqueta)
            <a href="{{ route('movimientos.index', array_filter(['estado' => $valor])) }}"
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition {{ request('estado', '') === $valor ? 'bg-[#0b3b70] text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                {{ $etiqueta }}
                @if($valor === 'solicitado' && $pendientes > 0)
                    <span class="ml-1 px-1.5 py-0.5 rounded-full bg-amber-400 text-amber-950 text-[10px]">{{ $pendientes }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-400 uppercase text-xs font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-4">Bien Patrimonial</th>
                        <th class="py-3 px-4">Tipo</th>
                        <th class="py-3 px-4">Origen</th>
                        <th class="py-3 px-4">Destino</th>
                        <th class="py-3 px-4">Nuevo Responsable</th>
                        <th class="py-3 px-4">Estado</th>
                        <th class="py-3 px-4">Fecha</th>
                        @can('movimientos.aprobar')
                            <th class="py-3 px-4 text-right">Acción</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movimientos as $mov)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3.5 px-4 font-semibold text-slate-800">
                                {{ $mov->bien->nombre ?? 'Bien N/A' }}
                                <span class="block text-xs font-mono text-indigo-600">{{ $mov->bien->codigo_barras ?? '' }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 uppercase">
                                    {{ $mov->tipo }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-500">
                                {{ $mov->sedeOrigen->nombre ?? 'N/A' }}
                                <span class="block text-[11px] text-slate-400">{{ $mov->areaOrigen->nombre ?? '' }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-semibold text-slate-800">
                                {{ $mov->sedeDestino->nombre ?? 'N/A' }}
                                <span class="block text-[11px] font-normal text-slate-400">{{ $mov->areaDestino->nombre ?? '' }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-700">
                                {{ $mov->responsableNuevo ? $mov->responsableNuevo->nombres.' '.$mov->responsableNuevo->apellidos : 'Sin Asignar' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-bold uppercase border {{ $mov->estado_badge['class'] }}">
                                    <i class="fa-solid {{ $mov->estado_badge['icon'] }} text-[10px]"></i> {{ $mov->estado_badge['label'] }}
                                </span>
                                <span class="block text-[11px] text-slate-400 mt-1">Por: {{ $mov->usuario->nombre_completo ?? '—' }}</span>
                                @if($mov->aprobador)
                                    <span class="block text-[11px] text-slate-400">Resolvió: {{ $mov->aprobador->nombre_completo }}</span>
                                @endif
                                @if($mov->estado === 'rechazado' && $mov->motivo_rechazo)
                                    <span class="block text-[11px] text-rose-600">Motivo: {{ $mov->motivo_rechazo }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-400 font-mono">
                                {{ $mov->fecha_movimiento->format('d/m/Y H:i') }}
                            </td>
                            @can('movimientos.aprobar')
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    @if($mov->estado === 'solicitado')
                                        <div class="flex items-center justify-end gap-1.5">
                                            <form method="POST" action="{{ route('movimientos.aprobar', $mov) }}" data-confirmar="¿Aprobar el movimiento? Se actualizará la ubicación y el responsable del bien.">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold">
                                                    <i class="fa-solid fa-check"></i> Aprobar
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('movimientos.rechazar', $mov) }}" data-confirmar="Motivo del rechazo:" data-titulo="Rechazar solicitud" data-aceptar="Rechazar" data-peligro data-pedir-motivo>
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="motivo_rechazo" value="">
                                                <button type="submit" class="px-2.5 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-lg text-xs font-bold border border-rose-200">Rechazar</button>
                                            </form>
                                        </div>
                                    @elseif($mov->estado === 'aprobado')
                                        <form method="POST" action="{{ route('movimientos.ejecutar', $mov) }}" data-confirmar="¿Confirmar que el traslado físico ya se realizó?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="px-2.5 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-xs font-bold border border-blue-200">Marcar ejecutado</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-slate-400 italic">No hay movimientos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($movimientos->hasPages())
            <div class="p-4 border-t border-slate-100">{{ $movimientos->links() }}</div>
        @endif
    </div>
</div>
@endsection
