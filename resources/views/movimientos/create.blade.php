@extends('layouts.app')

@php
    $gestiona = Auth::user()->can('movimientos.crear');
    $registraDirecto = Auth::user()->can('movimientos.aprobar') && ! request('inventario_id') && ! old('inventario_id');
@endphp

@section('title', $registraDirecto ? 'Registrar movimiento' : 'Solicitar movimiento')

@section('content')
<div class="max-w-3xl">
    <x-encabezado :titulo="$registraDirecto ? 'Registrar movimiento' : 'Solicitar movimiento'"
                  subtitulo="Solicita el traslado de un bien a otro ambiente o sede."
                  :volver="Auth::user()->can('movimientos.ver') ? route('movimientos.index') : route('solicitudes.index')"
                  :volver-texto="Auth::user()->can('movimientos.ver') ? 'Movimientos' : 'Solicitudes'" />

    <form method="POST" action="{{ route('movimientos.store') }}" class="tarjeta p-5 sm:p-6 space-y-5" id="form-movimiento">
        @csrf
        @if(request('inventario_id') || old('inventario_id'))
            <input type="hidden" name="inventario_id" value="{{ old('inventario_id', request('inventario_id')) }}">
            <div class="p-3 bg-sky-50 border border-sky-200 rounded-xl text-sky-800 text-sm"><i class="fa-solid fa-barcode"></i> Generado desde el resultado de una toma de inventario: se registrará como <strong>solicitud</strong> pendiente de aprobación.</div>
        @endif

        <fieldset class="space-y-3">
            <legend class="etiqueta-campo">Datos del bien</legend>
            <x-selector-bien para="movimientos" :seleccionado="$seleccionado" />
            @error('bien_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
        </fieldset>

        <div>
            <label for="sede_actual" class="etiqueta-campo">Ubicación actual</label>
            <input type="text" id="sede_actual" value="{{ $seleccionado['ubicacion'] ?? '' }}" placeholder="Elija un bien" readonly class="campo bg-slate-50 text-slate-600">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="sede_destino_id" class="etiqueta-campo">Nueva sede <span class="text-rose-500">*</span></label>
                <select id="sede_destino_id" name="sede_destino_id" required class="campo">
                    <option value="">Seleccione una sede</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" @selected(old('sede_destino_id', request('sede_destino_id')) == $sede->id)>{{ $sede->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="area_destino_id" class="etiqueta-campo">Nuevo ambiente / ubicación <span class="text-rose-500">*</span></label>
                <select id="area_destino_id" name="area_destino_id" data-sede-select="sede_destino_id" @required(! $gestiona) class="campo">
                    <option value="">{{ $gestiona ? 'Sin área' : 'Seleccione un ambiente' }}</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}" data-sede="{{ $area->sede_id }}" @selected(old('area_destino_id', request('area_destino_id')) == $area->id)>{{ $area->nombre }}{{ $area->ubicacion ? ' · '.$area->ubicacion : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($gestiona)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="tipo" class="etiqueta-campo">Tipo de movimiento <span class="text-rose-500">*</span></label>
                    <select id="tipo" name="tipo" required class="campo">
                        @foreach(\App\Models\Movimiento::TIPOS as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('tipo', 'traslado') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="responsable_nuevo_id" class="etiqueta-campo">Nuevo responsable</label>
                    <select id="responsable_nuevo_id" name="responsable_nuevo_id" class="campo">
                        <option value="">Sin responsable</option>
                        @foreach($responsables as $responsable)
                            <option value="{{ $responsable->id }}" @selected(old('responsable_nuevo_id') == $responsable->id)>{{ $responsable->nombre_completo }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        <div>
            <label for="motivo" class="etiqueta-campo">Observaciones <span class="font-normal text-slate-400">(opcional)</span></label>
            <textarea id="motivo" name="motivo" rows="4" placeholder="Escribe aquí..." class="campo">{{ old('motivo') }}</textarea>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="btn-primario w-full sm:w-auto"><i class="fa-solid fa-paper-plane"></i> {{ $registraDirecto ? 'Registrar movimiento' : 'Enviar solicitud' }}</button>
        </div>
    </form>
</div>

@include('partials.filtro-areas-por-sede')
@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    document.querySelector('#form-movimiento [data-selector-bien]').addEventListener('bien-seleccionado', (e) => {
        document.getElementById('sede_actual').value = e.detail ? e.detail.ubicacion : '';
    });
</script>
@endpush
@endsection
