@extends('layouts.app')

@section('title', 'Mantenimientos')

@php
    $gestiona = Auth::user()->can('mantenimientos.editar');
    $estados = ['pendiente' => ['Pendiente', 'badge-ambar'], 'en_proceso' => ['En proceso', 'badge-azul'], 'finalizado' => ['Finalizado', 'badge-verde'], 'cancelado' => ['Cancelado', 'badge-gris']];
@endphp

@section('content')
<x-encabezado titulo="Mantenimientos" :subtitulo="$gestiona ? 'Órdenes de mantenimiento, costos y reactivación de bienes.' : 'Seguimiento de las fallas reportadas.'">
    <x-slot:acciones>
        @can('mantenimientos.crear')
            <a href="{{ route('mantenimientos.create') }}" class="btn-primario"><i class="fa-solid fa-plus"></i> {{ $gestiona ? 'Registrar mantenimiento' : 'Solicitar mantenimiento' }}</a>
        @endcan
    </x-slot:acciones>
</x-encabezado>

<section class="tarjeta overflow-hidden">
    <form method="GET" class="p-4 sm:p-5 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1fr_200px_200px_auto] gap-3">
        <label for="buscar" class="sr-only">Buscar</label>
        <input type="search" id="buscar" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por bien, descripción o proveedor..." class="campo">
        <label for="filtro-estado" class="sr-only">Estado</label>
        <select id="filtro-estado" name="estado" class="campo">
            <option value="">Todos los estados</option>
            @foreach($estados as $valor => [$etiqueta])
                <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select>
        <label for="filtro-tipo" class="sr-only">Tipo</label>
        <select id="filtro-tipo" name="tipo" class="campo">
            <option value="">Todos los tipos</option>
            <option value="preventivo" @selected(request('tipo') === 'preventivo')>Preventivo</option>
            <option value="correctivo" @selected(request('tipo') === 'correctivo')>Correctivo</option>
        </select>
        <button type="submit" class="btn-secundario"><i class="fa-solid fa-filter"></i> Filtrar</button>
    </form>

    @if($mantenimientos->isEmpty())
        <x-estado-vacio icono="fa-screwdriver-wrench" titulo="No hay mantenimientos" :mensaje="request()->hasAny(['buscar', 'estado', 'tipo']) ? 'Pruebe con otros filtros.' : null" />
    @else
        <table class="tabla">
            <thead><tr><th>Bien</th><th>Tipo</th><th>Descripción</th><th>Fechas</th><th>Costo</th><th>Estado</th>@if($gestiona)<th class="text-right">Acciones</th>@endif</tr></thead>
            <tbody>
                @foreach($mantenimientos as $mantenimiento)
                    <tr>
                        <td>
                            <p class="font-medium text-slate-800">{{ $mantenimiento->bien->nombre ?? '—' }}</p>
                            <p class="font-mono text-xs text-slate-500">{{ $mantenimiento->bien->codigo_barras ?? '' }}</p>
                        </td>
                        <td>{{ ucfirst($mantenimiento->tipo) }}</td>
                        <td class="text-slate-600">
                            {{ \Illuminate\Support\Str::limit($mantenimiento->descripcion, 80) }}
                            @if($mantenimiento->proveedor)<span class="block text-xs text-slate-400">{{ $mantenimiento->proveedor }}</span>@endif
                        </td>
                        <td class="text-slate-500 whitespace-nowrap">{{ $mantenimiento->fecha_inicio->format('d/m/Y') }}{{ $mantenimiento->fecha_fin ? ' – '.$mantenimiento->fecha_fin->format('d/m/Y') : '' }}</td>
                        <td class="whitespace-nowrap">S/ {{ number_format($mantenimiento->costo, 2) }}</td>
                        <td><span class="{{ $estados[$mantenimiento->estado][1] ?? 'badge-gris' }}">{{ $estados[$mantenimiento->estado][0] ?? $mantenimiento->estado }}</span></td>
                        @if($gestiona)
                            <td class="text-right whitespace-nowrap" data-label="">
                                @if($mantenimiento->estado === 'pendiente')
                                    <form method="POST" action="{{ route('mantenimientos.update', $mantenimiento) }}" class="inline" data-confirmar="El bien quedará en estado «En mantenimiento»." data-titulo="¿Iniciar el mantenimiento?" data-aceptar="Iniciar">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="estado" value="en_proceso">
                                        <button type="submit" class="btn-secundario">Iniciar</button>
                                    </form>
                                @endif
                                @if(in_array($mantenimiento->estado, ['pendiente', 'en_proceso'], true))
                                    <form method="POST" action="{{ route('mantenimientos.update', $mantenimiento) }}" class="inline" data-confirmar="El bien volverá al estado «Bueno»." data-titulo="¿Finalizar el mantenimiento?" data-aceptar="Finalizar">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="estado" value="finalizado">
                                        <input type="hidden" name="fecha_fin" value="{{ now()->toDateString() }}">
                                        <button type="submit" class="btn-exito">Finalizar</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($mantenimientos->hasPages())<div class="p-4 border-t border-slate-100">{{ $mantenimientos->links() }}</div>@endif
    @endif
</section>
@endsection
