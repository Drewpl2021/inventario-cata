@extends('layouts.app')

@php($gestiona = Auth::user()->can('mantenimientos.editar'))

@section('title', $gestiona ? 'Registrar mantenimiento' : 'Solicitar mantenimiento')

@section('content')
<div class="max-w-3xl">
    <x-encabezado :titulo="$gestiona ? 'Registrar mantenimiento' : 'Solicitar mantenimiento'"
                  :subtitulo="$gestiona ? 'Orden de trabajo técnico del bien.' : 'Reporte la falla para que la Administración la evalúe.'"
                  :volver="route('mantenimientos.index')" volver-texto="Mantenimientos" />

    <form method="POST" action="{{ route('mantenimientos.store') }}" class="tarjeta p-5 sm:p-6 space-y-5">
        @csrf
        <x-campo nombre="bien_id" etiqueta="Bien" requerido>
            <select id="campo-bien_id" name="bien_id" required class="campo">
                <option value="">Seleccione un bien</option>
                @foreach($bienes as $bien)
                    <option value="{{ $bien->id }}" @selected(old('bien_id', request('bien_id')) == $bien->id)>{{ $bien->codigo_barras }} · {{ $bien->nombre }}</option>
                @endforeach
            </select>
        </x-campo>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-campo nombre="tipo" etiqueta="Tipo" requerido>
                <select id="campo-tipo" name="tipo" required class="campo">
                    <option value="correctivo" @selected(old('tipo') === 'correctivo')>Correctivo (reparación)</option>
                    <option value="preventivo" @selected(old('tipo') === 'preventivo')>Preventivo</option>
                </select>
            </x-campo>
            <x-campo nombre="fecha_inicio" etiqueta="Fecha de inicio" requerido>
                <input type="date" id="campo-fecha_inicio" name="fecha_inicio" value="{{ old('fecha_inicio', now()->toDateString()) }}" required class="campo">
            </x-campo>
            @if($gestiona)
                <x-campo nombre="proveedor" etiqueta="Proveedor o técnico">
                    <input type="text" id="campo-proveedor" name="proveedor" value="{{ old('proveedor') }}" maxlength="150" class="campo">
                </x-campo>
                <x-campo nombre="costo" etiqueta="Costo (S/)">
                    <input type="number" step="0.01" min="0" id="campo-costo" name="costo" value="{{ old('costo') }}" class="campo">
                </x-campo>
            @endif
        </div>

        <x-campo nombre="descripcion" etiqueta="Descripción de la falla o revisión" requerido>
            <textarea id="campo-descripcion" name="descripcion" rows="4" required class="campo" placeholder="Describa el problema o el trabajo a realizar...">{{ old('descripcion') }}</textarea>
        </x-campo>

        <div class="flex justify-end">
            <button type="submit" class="btn-primario w-full sm:w-auto"><i class="fa-solid fa-paper-plane"></i> {{ $gestiona ? 'Registrar' : 'Enviar solicitud' }}</button>
        </div>
    </form>
</div>
@endsection
