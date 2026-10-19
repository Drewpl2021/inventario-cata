@extends('layouts.app')

@section('title', 'Requerimientos de Bienes')
@section('page_header', 'Gestión de Requerimientos y Solicitudes')

@section('content')
<div class="space-y-6">

    <!-- Tarjetas de Métricas Ejecutivas -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Total Solicitudes</span>
                <span class="text-2xl font-black text-slate-800">{{ $total }}</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-amber-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-amber-700 uppercase tracking-wider block">Pendientes</span>
                <span class="text-2xl font-black text-amber-600">{{ $pendientes }}</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-clock"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-blue-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-blue-700 uppercase tracking-wider block">En Cotización</span>
                <span class="text-2xl font-black text-blue-600">{{ $enCotizacion }}</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-emerald-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-emerald-700 uppercase tracking-wider block">Aprobados</span>
                <span class="text-2xl font-black text-emerald-600">{{ $aprobados }}</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-purple-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-purple-700 uppercase tracking-wider block">Atendidos</span>
                <span class="text-2xl font-black text-purple-600">{{ $atendidos }}</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-box-open"></i>
            </div>
        </div>
    </div>

    <!-- Barra de Búsqueda, Filtros y Botón de Nueva Solicitud -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row gap-4 items-center justify-between">
        <form method="GET" action="{{ route('requerimientos.index') }}" class="flex flex-wrap gap-3 items-center w-full lg:w-auto">
            <div class="relative w-full sm:w-64">
                <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar código, bien, justificación..." class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3.5 text-slate-400 text-xs"></i>
            </div>

            <select name="estado" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium">
                <option value="">Todos los Estados</option>
                <option value="pendiente" {{ request('estado') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                <option value="en_cotizacion" {{ request('estado') === 'en_cotizacion' ? 'selected' : '' }}>En Cotización</option>
                <option value="aprobado" {{ request('estado') === 'aprobado' ? 'selected' : '' }}>Aprobado</option>
                <option value="atendido" {{ request('estado') === 'atendido' ? 'selected' : '' }}>Atendido</option>
                <option value="rechazado" {{ request('estado') === 'rechazado' ? 'selected' : '' }}>Rechazado</option>
            </select>

            <select name="prioridad" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium">
                <option value="">Cualquier Prioridad</option>
                <option value="urgente" {{ request('prioridad') === 'urgente' ? 'selected' : '' }}>Urgente</option>
                <option value="alta" {{ request('prioridad') === 'alta' ? 'selected' : '' }}>Alta</option>
                <option value="media" {{ request('prioridad') === 'media' ? 'selected' : '' }}>Media</option>
                <option value="baja" {{ request('prioridad') === 'baja' ? 'selected' : '' }}>Baja</option>
            </select>

            <button type="submit" class="px-4 py-2.5 bg-slate-800 text-white rounded-xl text-xs sm:text-sm font-semibold hover:bg-slate-900 transition flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-filter text-xs"></i> Filtrar
            </button>
            @if(request()->hasAny(['buscar', 'estado', 'prioridad']))
                <a href="{{ route('requerimientos.index') }}" class="px-3 py-2.5 text-slate-500 hover:text-slate-800 text-xs font-semibold transition">Limpiar</a>
            @endif
        </form>

        <a href="{{ route('requerimientos.create') }}" class="w-full sm:w-auto px-5 py-2.5 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-xl text-sm font-bold transition shadow-md shadow-blue-900/20 flex items-center justify-center gap-2">
            <i class="fa-solid fa-plus text-xs"></i> Solicitar Requerimiento
        </a>
    </div>

    <!-- Tabla de Requerimientos -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-400 uppercase text-xs font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-4">Código</th>
                        <th class="py-3 px-4">Bien Solicitado / Descripción</th>
                        <th class="py-3 px-4">Solicitante</th>
                        <th class="py-3 px-4 text-center">Cant.</th>
                        <th class="py-3 px-4">Costo Est.</th>
                        <th class="py-3 px-4">Cotización / Plan</th>
                        <th class="py-3 px-4">Prioridad</th>
                        <th class="py-3 px-4">Estado</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($requerimientos as $req)
                        <tr class="hover:bg-slate-50/60 transition">
                            <!-- Código -->
                            <td class="py-3.5 px-4 font-mono font-bold text-xs text-indigo-700 whitespace-nowrap">
                                <a href="{{ route('requerimientos.show', $req) }}" class="bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-md border border-indigo-200 transition">
                                    {{ $req->codigo }}
                                </a>
                            </td>

                            <!-- Bien Solicitado & Justificación -->
                            <td class="py-3.5 px-4 max-w-xs">
                                <p class="font-bold text-slate-800 line-clamp-1" title="{{ $req->descripcion }}">
                                    {{ $req->descripcion }}
                                </p>
                                <span class="text-[11px] font-semibold text-slate-500 block truncate">
                                    <i class="fa-solid fa-tag text-[10px] text-slate-400 mr-1"></i>{{ $req->tipo_requerimiento }}
                                </span>
                                <p class="text-[11px] text-slate-400 italic line-clamp-1 mt-0.5" title="{{ $req->justificacion }}">
                                    {{ $req->justificacion }}
                                </p>
                            </td>

                            <!-- Solicitante & Sede -->
                            <td class="py-3.5 px-4 text-xs">
                                <p class="font-semibold text-slate-800">
                                    {{ $req->usuario ? $req->usuario->nombre.' '.$req->usuario->apellido : 'Usuario Sistema' }}
                                </p>
                                <p class="text-[11px] text-slate-400">
                                    {{ $req->sede->nombre ?? 'Sede General' }}
                                </p>
                            </td>

                            <!-- Cantidad -->
                            <td class="py-3.5 px-4 text-center font-bold text-slate-700 text-xs">
                                <span class="bg-slate-100 px-2 py-0.5 rounded-md">
                                    {{ $req->cantidad }}
                                </span>
                            </td>

                            <!-- Costo Estimado -->
                            <td class="py-3.5 px-4 text-xs font-bold text-emerald-700 whitespace-nowrap">
                                S/ {{ number_format($req->costo_estimado, 2) }}
                            </td>

                            <!-- Cotización / Plan de uso Adjunto -->
                            <td class="py-3.5 px-4 text-xs whitespace-nowrap">
                                @if($req->archivo_adjunto)
                                    <a href="{{ $req->url_adjunto }}" target="_blank" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg font-semibold text-[11px] transition shadow-sm" title="{{ $req->archivo_nombre_original }}">
                                        <i class="fa-solid fa-paperclip text-indigo-600"></i>
                                        <span>{{ $req->archivo_tipo ?? 'Ver Archivo' }}</span>
                                    </a>
                                @else
                                    <span class="text-[11px] text-slate-400 italic">Sin archivo</span>
                                @endif
                            </td>

                            <!-- Prioridad -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider {{ $req->prioridad_badge['class'] }}">
                                    {{ $req->prioridad_badge['label'] }}
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider border {{ $req->estado_badge['class'] }}">
                                    <i class="fa-solid {{ $req->estado_badge['icon'] }} text-[10px]"></i>
                                    {{ $req->estado_badge['label'] }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('requerimientos.show', $req) }}" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-600 flex items-center justify-center transition" title="Ver Detalle y Gestionar">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </a>
                                    <form method="POST" action="{{ route('requerimientos.destroy', $req) }}" data-confirmar="¿Está seguro de eliminar este requerimiento?" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-600 flex items-center justify-center transition" title="Eliminar Requerimiento">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-2xl">
                                    <i class="fa-solid fa-clipboard-question"></i>
                                </div>
                                <p class="font-bold text-slate-700 text-base">No hay requerimientos registrados</p>
                                <p class="text-xs text-slate-400 mt-1">Cuando los usuarios soliciten bienes o materiales aparecerán listados aquí.</p>
                                <div class="pt-4">
                                    <a href="{{ route('requerimientos.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-xl text-xs font-bold transition shadow-md">
                                        <i class="fa-solid fa-plus"></i> Crear Primer Requerimiento
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requerimientos->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $requerimientos->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
