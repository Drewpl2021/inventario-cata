@extends('layouts.app')

@php($dictamina = Auth::user()->can('bajas.aprobar') && ! request('inventario_id') && ! old('inventario_id'))

@section('title', $dictamina ? 'Registrar baja' : 'Solicitar dar de baja')

@section('content')
<div class="max-w-3xl">
    <x-encabezado :titulo="$dictamina ? 'Registrar baja' : 'Solicitar dar de baja'"
                  :subtitulo="$dictamina ? 'Retiro formal del bien del inventario activo.' : 'Registra la solicitud para dar de baja un bien a tu cargo.'"
                  :volver="Auth::user()->can('bajas.ver') ? route('bajas.index') : route('solicitudes.index')"
                  :volver-texto="Auth::user()->can('bajas.ver') ? 'Bajas' : 'Solicitudes'" />

    <form method="POST" action="{{ route('bajas.store') }}" enctype="multipart/form-data" class="tarjeta p-5 sm:p-6 space-y-5">
        @csrf
        @if(request('inventario_id') || old('inventario_id'))
            <input type="hidden" name="inventario_id" value="{{ old('inventario_id', request('inventario_id')) }}">
            <div class="p-3 bg-sky-50 border border-sky-200 rounded-xl text-sky-800 text-sm"><i class="fa-solid fa-barcode"></i> Generado desde el resultado de una toma de inventario: se registrará como <strong>solicitud</strong> pendiente de aprobación.</div>
        @endif

        <fieldset class="space-y-3">
            <legend class="etiqueta-campo">Datos del bien</legend>
            <x-selector-bien para="bajas" :seleccionado="$seleccionado" />
            @error('bien_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
        </fieldset>

        <div>
            <label for="motivo" class="etiqueta-campo">Motivo de la baja <span class="text-rose-500">*</span></label>
            <select id="motivo" name="motivo" required class="campo">
                <option value="">Selecciona un motivo</option>
                @foreach(['deterioro' => 'Deterioro físico', 'obsolescencia' => 'Obsolescencia técnica', 'sin_reparacion' => 'Sin posibilidad de reparación', 'perdida' => 'Pérdida', 'robo' => 'Robo o hurto', 'otro' => 'Otro'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('motivo', request('motivo')) === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
            @error('motivo') <p class="text-sm text-rose-600 mt-1">{{ $message }}</p> @enderror
        </div>

        @if($dictamina)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="fecha_baja" class="etiqueta-campo">Fecha de baja</label>
                    <input type="date" id="fecha_baja" name="fecha_baja" value="{{ old('fecha_baja', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="campo">
                </div>
                <div>
                    <label for="documento_referencia" class="etiqueta-campo">Documento de referencia</label>
                    <input type="text" id="documento_referencia" name="documento_referencia" value="{{ old('documento_referencia') }}" placeholder="Res. Directoral N.° 045-2026" class="campo">
                </div>
                <div>
                    <label for="valor_residual" class="etiqueta-campo">Valor residual (S/)</label>
                    <input type="number" step="0.01" min="0" id="valor_residual" name="valor_residual" value="{{ old('valor_residual') }}" class="campo">
                </div>
            </div>
        @endif

        <div>
            <label for="descripcion" class="etiqueta-campo">Observaciones <span class="font-normal text-slate-400">(opcional)</span></label>
            <textarea id="descripcion" name="descripcion" rows="4" placeholder="Escribe aquí..." class="campo">{{ old('descripcion') }}</textarea>
        </div>

        <div>
            <label for="fotografia" class="etiqueta-campo">Foto de evidencia <span class="font-normal text-slate-400">(opcional)</span></label>
            <input type="file" id="fotografia" name="fotografia" accept="image/jpeg,image/png,image/webp" class="campo file:mr-3 file:rounded-lg file:border-0 file:bg-marino-50 file:px-3 file:py-1.5 file:text-marino-800 file:font-semibold">
            @error('fotografia') <p class="text-sm text-rose-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="btn-primario w-full sm:w-auto"><i class="fa-solid fa-paper-plane"></i> {{ $dictamina ? 'Registrar baja' : 'Enviar solicitud' }}</button>
        </div>
    </form>
</div>
@endsection
