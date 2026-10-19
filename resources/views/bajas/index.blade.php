@extends('layouts.app')

@section('title', 'Bajas de Bienes')
@section('page_header', 'Gestión de Bajas de Bienes Patrimoniales')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">
                {{ Auth::user()->can('bajas.aprobar') ? 'Registro y Dictamen de Bajas Patrimoniales' : 'Solicitudes de Baja' }}
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                {{ Auth::user()->can('bajas.aprobar') ? 'Bienes desafectados y solicitudes pendientes de dictamen formal.' : 'Historial de bienes reportados para trámite de baja ante la Administración.' }}
            </p>
        </div>
        @can('bajas.crear')
        <a href="{{ route('bajas.create') }}" class="px-4 py-2 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-lg text-sm font-semibold transition shadow-md shadow-blue-900/20 flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>
            <span>{{ Auth::user()->can('bajas.aprobar') ? 'Registrar Baja de Bien' : 'Solicitar Baja de Bien' }}</span>
        </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-800 text-xs font-semibold flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-400 uppercase text-xs font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-4">Bien Patrimonial</th>
                        <th class="py-3 px-4">Foto Evidencia</th>
                        <th class="py-3 px-4">Motivo</th>
                        <th class="py-3 px-4">Fecha</th>
                        <th class="py-3 px-4">Estado</th>
                        <th class="py-3 px-4">Doc. Referencia</th>
                        <th class="py-3 px-4">Solicitante</th>
                        <th class="py-3 px-4">Resuelto por</th>
                        @can('bajas.aprobar')
                            <th class="py-3 px-4 text-right">Acción / Dictamen</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bajas as $baja)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3.5 px-4 font-semibold text-slate-800">
                                {{ $baja->bien->nombre ?? 'N/A' }}
                                <span class="block text-xs font-mono text-rose-600">{{ $baja->bien->codigo_barras ?? '' }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($baja->fotografia)
                                    <a href="{{ $baja->url_fotografia }}" target="_blank">
                                        <img src="{{ $baja->url_fotografia }}" alt="Foto baja" class="w-12 h-12 object-cover rounded-lg border border-slate-200 hover:scale-105 transition shadow-sm">
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400 italic">Sin foto</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 uppercase">
                                    {{ $baja->motivo }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono text-slate-600">{{ $baja->fecha_baja ? $baja->fecha_baja->format('d/m/Y') : '-' }}</td>
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider border {{ $baja->estado_badge['class'] }}">
                                    <i class="fa-solid {{ $baja->estado_badge['icon'] }} text-[10px]"></i>
                                    {{ $baja->estado_badge['label'] }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600">
                                {{ $baja->documento_referencia ?? ($baja->estado === 'solicitada' ? 'En evaluación' : '-') }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-500">
                                {{ $baja->usuario->nombre ?? '' }} {{ $baja->usuario->apellido ?? '' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-500">
                                @if($baja->aprobador)
                                    {{ $baja->aprobador->nombre_completo }}
                                    <span class="block text-[11px] text-slate-400 font-mono">{{ $baja->fecha_resolucion?->format('d/m/Y H:i') }}</span>
                                @else
                                    —
                                @endif
                                @if($baja->estado === 'rechazada' && $baja->motivo_rechazo)
                                    <span class="block text-[11px] text-rose-600 mt-0.5">Motivo: {{ $baja->motivo_rechazo }}</span>
                                @endif
                            </td>
                            @can('bajas.aprobar')
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    @if($baja->estado === 'solicitada')
                                        <div class="flex items-center justify-end gap-1.5">
                                            <form method="POST" action="{{ route('bajas.aprobar', $baja) }}" data-confirmar="¿Aprobar y ejecutar la baja definitiva de este bien?" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                                    <i class="fa-solid fa-check"></i> Aprobar Baja
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('bajas.rechazar', $baja) }}" data-confirmar="Ingrese el motivo de rechazo de la baja:" data-titulo="Rechazar solicitud" data-aceptar="Rechazar" data-peligro data-pedir-motivo class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="motivo_rechazo" value="">
                                                <button type="submit" class="px-2.5 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-lg text-xs font-bold transition border border-rose-200">
                                                    Rechazar
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 font-medium">Procesado</span>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ Auth::user()->can('bajas.aprobar') ? 9 : 8 }}" class="py-8 text-center text-slate-400 italic">No hay registros de bajas o solicitudes pendientes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($bajas->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $bajas->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
