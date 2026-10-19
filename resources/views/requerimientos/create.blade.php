@extends('layouts.app')

@section('title', 'Solicitar requerimiento')

@php
    $tipos = [
        'Mobiliario y Enseres (Mesa, Silla, Escritorio)' => 'Mobiliario y enseres (mesa, silla, escritorio, estante)',
        'Equipos de Cómputo y Tecnología' => 'Equipos de cómputo y tecnología (laptop, PC, proyector)',
        'Equipamiento de Laboratorio' => 'Equipamiento de laboratorio o taller',
        'Material Educativo y Didáctico' => 'Material educativo y didáctico',
        'Herramientas y Mantenimiento' => 'Herramientas y mantenimiento institucional',
        'Suministros y Útiles de Oficina' => 'Suministros y útiles de oficina',
        'Equipos de Audio y Video' => 'Equipos de audio, video y eventos',
        'Otro Bien / Material' => 'Otro bien o material',
    ];
    $completo = Auth::user()->can('requerimientos.crear');
@endphp

@section('content')
<div class="max-w-3xl">
    <x-encabezado titulo="Solicitar requerimiento" subtitulo="Solicita un nuevo bien o material."
                  :volver="Auth::user()->can('requerimientos.ver') ? route('requerimientos.index') : route('solicitudes.index')"
                  :volver-texto="Auth::user()->can('requerimientos.ver') ? 'Requerimientos' : 'Solicitudes'" />

    <form method="POST" action="{{ route('requerimientos.store') }}" enctype="multipart/form-data" class="tarjeta p-5 sm:p-6 space-y-5">
        @csrf

        <div>
            <label for="tipo_requerimiento" class="etiqueta-campo">Tipo de requerimiento <span class="text-rose-500">*</span></label>
            <select id="tipo_requerimiento" name="tipo_requerimiento" required class="campo">
                <option value="">Selecciona tipo</option>
                @foreach($tipos as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('tipo_requerimiento') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="descripcion" class="etiqueta-campo">Descripción <span class="text-rose-500">*</span></label>
            <textarea id="descripcion" name="descripcion" rows="3" required placeholder="Describe el bien o material que necesitas..." class="campo">{{ old('descripcion') }}</textarea>
        </div>

        <div>
            <label for="justificacion" class="etiqueta-campo">Justificación <span class="text-rose-500">*</span></label>
            <textarea id="justificacion" name="justificacion" rows="3" required placeholder="¿Para qué área o actividad se requiere?" class="campo">{{ old('justificacion') }}</textarea>
        </div>

        <div>
            <label for="observaciones" class="etiqueta-campo">Observaciones <span class="font-normal text-slate-400">(opcional)</span></label>
            <textarea id="observaciones" name="observaciones" rows="2" placeholder="Escribe aquí..." class="campo">{{ old('observaciones') }}</textarea>
        </div>

        <div>
            <label for="archivo_adjunto" class="etiqueta-campo">Adjunto <span class="font-normal text-slate-400">(opcional: PDF hasta 10 MB o imagen JPG, PNG o WEBP hasta 5 MB)</span></label>
            <input type="file" id="archivo_adjunto" name="archivo_adjunto" accept="application/pdf,image/jpeg,image/png,image/webp" class="campo file:mr-3 file:rounded-lg file:border-0 file:bg-marino-50 file:px-3 file:py-1.5 file:text-marino-800 file:font-semibold">
            <input type="hidden" name="archivo_tipo" value="{{ old('archivo_tipo', 'Documento de soporte') }}">
            @error('archivo_adjunto') <p class="text-sm text-rose-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <details @if($completo || $errors->hasAny(['cantidad', 'prioridad', 'costo_estimado', 'sede_id', 'area_id'])) open @endif class="rounded-xl border border-slate-200 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-slate-700 min-h-11 flex items-center">Más detalles (cantidad, prioridad, costo, sede)</summary>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                <div>
                    <label for="cantidad" class="etiqueta-campo">Cantidad</label>
                    <input type="number" id="cantidad" name="cantidad" min="1" value="{{ old('cantidad', 1) }}" class="campo">
                </div>
                <div>
                    <label for="prioridad" class="etiqueta-campo">Prioridad</label>
                    <select id="prioridad" name="prioridad" class="campo">
                        @foreach(['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'urgente' => 'Urgente'] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('prioridad', 'media') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="costo_estimado" class="etiqueta-campo">Costo estimado (S/)</label>
                    <input type="number" step="0.01" min="0" id="costo_estimado" name="costo_estimado" value="{{ old('costo_estimado') }}" class="campo">
                </div>
                <div>
                    <label for="sede_id" class="etiqueta-campo">Sede</label>
                    <select id="sede_id" name="sede_id" class="campo">
                        <option value="">Sin sede</option>
                        @foreach($sedes as $sede)
                            <option value="{{ $sede->id }}" @selected(old('sede_id', Auth::user()->sede_id) == $sede->id)>{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="area_id" class="etiqueta-campo">Área</label>
                    <select id="area_id" name="area_id" data-sede-select="sede_id" class="campo">
                        <option value="">Sin área</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}" data-sede="{{ $area->sede_id }}" @selected(old('area_id', Auth::user()->area_id) == $area->id)>{{ $area->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </details>

        <div class="flex justify-end pt-2">
            <button type="submit" class="btn-primario w-full sm:w-auto"><i class="fa-solid fa-paper-plane"></i> Enviar solicitud</button>
        </div>
    </form>
</div>

@include('partials.filtro-areas-por-sede')
@endsection
