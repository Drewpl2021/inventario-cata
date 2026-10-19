@extends('layouts.app')

@section('title', 'Nueva campaña')
@section('page_header', 'Toma de inventario › Nueva campaña')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Nueva campaña de toma de inventario</h1>
            <p class="text-xs text-slate-500 mt-0.5">Solo puede haber una campaña abierta por sede o área a la vez.</p>
        </div>
        <a href="{{ route('inventarios.index') }}" class="px-3.5 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg text-sm font-medium">&larr; Volver</a>
    </div>

    <form method="POST" action="{{ route('inventarios.store') }}" class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200/80 shadow-sm space-y-5">
        @csrf

        <div>
            <label for="nombre" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Nombre *</label>
            <input type="text" id="nombre" name="nombre" value="{{ old('nombre', 'Inventario '.now()->translatedFormat('F Y')) }}" required maxlength="150" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="sede_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Sede *</label>
                <select id="sede_id" name="sede_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                    <option value="">-- Seleccionar --</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" @selected(old('sede_id') == $sede->id)>{{ $sede->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="area_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Área (opcional)</label>
                <select id="area_id" name="area_id" data-sede-select="sede_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                    <option value="">Toda la sede</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}" data-sede="{{ $area->sede_id }}" @selected(old('area_id') == $area->id)>{{ $area->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="fecha_inicio" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Fecha de inicio *</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" value="{{ old('fecha_inicio', now()->toDateString()) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            </div>
            <div>
                <label for="fecha_fin" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Fecha prevista de cierre</label>
                <input type="date" id="fecha_fin" name="fecha_fin" value="{{ old('fecha_fin') }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            </div>
        </div>

        <fieldset>
            <legend class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Responsables del conteo</legend>
            <p class="text-[11px] text-slate-400 mb-2">Usted queda incluido automáticamente. Solo ellos (y quien supervisa con permiso de aprobar) pueden escanear.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($contadores as $contador)
                    <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-xl text-sm text-slate-700 has-[:checked]:bg-blue-50 has-[:checked]:border-[#0b3b70]">
                        <input type="checkbox" name="responsables[]" value="{{ $contador->id }}" @checked(in_array($contador->id, old('responsables', [Auth::id()]))) @disabled($contador->id === Auth::id()) class="accent-[#0b3b70]">
                        {{ $contador->nombre_completo }}
                        <span class="text-[11px] text-slate-400">{{ $contador->rol->nombre ?? '' }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label for="observaciones" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="2" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">{{ old('observaciones') }}</textarea>
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-5">
            <button type="submit" class="px-6 py-2.5 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-xl text-sm font-bold flex items-center gap-2">
                <i class="fa-solid fa-play text-xs"></i> Abrir campaña
            </button>
        </div>
    </form>
</div>

@include('partials.filtro-areas-por-sede')
@endsection
