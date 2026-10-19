@extends('layouts.app')

@section('title', 'Toma de inventario')
@section('page_header', 'Toma de inventario')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Campañas de toma de inventario</h1>
            <p class="text-xs text-slate-500 mt-0.5">Escanee las etiquetas para saber qué bienes están, cuáles faltan y cuáles están fuera de lugar.</p>
        </div>
        @can('create', \App\Models\Inventario::class)
            <a href="{{ route('inventarios.create') }}" class="px-4 py-2 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-lg text-sm font-semibold flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Nueva campaña
            </a>
        @endcan
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach(['' => 'Todas', 'abierto' => 'Abiertas', 'cerrado' => 'Cerradas'] as $valor => $etiqueta)
            <a href="{{ route('inventarios.index', array_filter(['estado' => $valor])) }}"
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold {{ request('estado', '') === $valor ? 'bg-[#0b3b70] text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">{{ $etiqueta }}</a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($inventarios as $inventario)
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col gap-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-mono font-bold text-slate-400">{{ $inventario->codigo }}</p>
                        <h2 class="font-bold text-slate-800">{{ $inventario->nombre }}</h2>
                        <p class="text-xs text-slate-500">{{ $inventario->ambito() }}</p>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border {{ $inventario->estaAbierto() ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' }}">
                        {{ $inventario->estaAbierto() ? 'Abierta' : 'Cerrada' }}
                    </span>
                </div>
                <div class="text-xs text-slate-500 space-y-0.5">
                    <p><i class="fa-regular fa-calendar w-4"></i> {{ $inventario->fecha_inicio->format('d/m/Y') }}{{ $inventario->fecha_fin ? ' – '.$inventario->fecha_fin->format('d/m/Y') : '' }}</p>
                    <p><i class="fa-solid fa-barcode w-4"></i> {{ $inventario->lecturas_count }} lecturas</p>
                    @if($inventario->cerrado_at)
                        <p><i class="fa-solid fa-lock w-4"></i> Cerrada el {{ $inventario->cerrado_at->format('d/m/Y H:i') }}</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-100 mt-auto">
                    @can('escanear', $inventario)
                        <a href="{{ route('inventarios.escanear', $inventario) }}" class="px-3 py-1.5 bg-[#0b3b70] text-white rounded-lg text-xs font-bold"><i class="fa-solid fa-barcode"></i> Escanear</a>
                    @endcan
                    <a href="{{ route('inventarios.show', $inventario) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold">{{ $inventario->estaAbierto() ? 'Avance' : 'Resultados' }}</a>
                </div>
            </div>
        @empty
            <div class="md:col-span-2 xl:col-span-3 p-10 bg-white rounded-2xl border border-dashed border-slate-300 text-center text-slate-400">
                <i class="fa-solid fa-barcode text-4xl mb-2"></i>
                <p>Aún no hay campañas de toma de inventario.</p>
            </div>
        @endforelse
    </div>

    @if($inventarios->hasPages())
        <div>{{ $inventarios->links() }}</div>
    @endif
</div>
@endsection
