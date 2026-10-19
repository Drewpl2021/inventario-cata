@extends('layouts.app')

@section('title', $misBienes ? 'Mis bienes' : 'Bienes patrimoniales')

@section('content')
<x-encabezado :titulo="$misBienes ? 'Mis bienes' : 'Bienes patrimoniales'"
              :subtitulo="$misBienes ? 'Bienes que tienes a tu cargo.' : 'Listado de todos los bienes registrados en el sistema.'">
    <x-slot:acciones>
        @if($misBienes && Auth::user()->responsable)
            <a href="{{ route('mis-bienes.acta') }}" target="_blank" class="btn-secundario"><i class="fa-regular fa-file-pdf"></i> Acta de asignación</a>
        @endif
        @can('etiquetas.ver')
            <a href="{{ route('etiquetas.index', array_filter(['sede_id' => request('sede_id'), 'categoria_id' => request('categoria_id'), 'buscar' => request('buscar'), 'solo_sin_etiqueta' => request('etiqueta') === 'sin' ? 1 : null])) }}" class="btn-secundario">
                <i class="fa-solid fa-tags"></i> Imprimir etiquetas
            </a>
        @endcan
        @can('create', \App\Models\Bien::class)
            <a href="{{ route('bienes.create') }}" class="btn-primario"><i class="fa-solid fa-plus"></i> Nuevo bien</a>
        @endcan
    </x-slot:acciones>
</x-encabezado>

@if(Auth::user()->can('bienes.ver') && Auth::user()->can('mis_bienes.ver') && Auth::user()->responsable)
    <div class="flex gap-2 mb-4">
        <a href="{{ route('bienes.index') }}" @class(['btn', 'bg-marino-800 text-white' => ! $misBienes, 'btn-secundario' => $misBienes])>Todos los bienes</a>
        <a href="{{ route('mis-bienes.index') }}" @class(['btn', 'bg-marino-800 text-white' => $misBienes, 'btn-secundario' => ! $misBienes])>Mis bienes</a>
    </div>
@endif

<section class="tarjeta overflow-hidden">
    <form method="GET" action="{{ $misBienes ? route('mis-bienes.index') : route('bienes.index') }}" class="p-4 sm:p-5 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.3fr)_repeat(3,minmax(0,1fr))_minmax(0,1.1fr)] gap-3">
        <div class="relative sm:col-span-2 xl:col-span-1">
            <label for="buscar" class="sr-only">Buscar</label>
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="search" id="buscar" name="buscar" value="{{ request('buscar') }}" placeholder="Código, nombre, serie..." class="campo pl-10">
        </div>
        @unless($misBienes)
            <label class="sr-only" for="filtro-sede">Sede</label>
            <select id="filtro-sede" name="sede_id" class="campo">
                <option value="">Todas las sedes</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede->id }}" @selected(request('sede_id') == $sede->id)>{{ $sede->nombre }}</option>
                @endforeach
            </select>
        @endunless
        <label class="sr-only" for="filtro-categoria">Categoría</label>
        <select id="filtro-categoria" name="categoria_id" class="campo">
            <option value="">Todas las categorías</option>
            @foreach($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected(request('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="filtro-estado">Estado</label>
        <select id="filtro-estado" name="estado" class="campo">
            <option value="">Todos los estados</option>
            @foreach(\App\Models\Bien::ESTADOS as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <label class="sr-only" for="filtro-etiqueta">Etiqueta</label>
            <select id="filtro-etiqueta" name="etiqueta" class="campo">
                <option value="">Etiqueta: todas</option>
                <option value="sin" @selected(request('etiqueta') === 'sin')>Sin etiqueta impresa</option>
                <option value="con" @selected(request('etiqueta') === 'con')>Con etiqueta impresa</option>
            </select>
            <button type="submit" class="btn-icono border border-slate-200 shrink-0" aria-label="Aplicar filtros"><i class="fa-solid fa-sliders"></i></button>
        </div>
    </form>

    @if($bienes->isEmpty())
        <x-estado-vacio icono="fa-box-open" titulo="No se encontraron bienes" :mensaje="request()->hasAny(['buscar', 'sede_id', 'categoria_id', 'estado', 'etiqueta']) ? 'Pruebe con otros filtros.' : null" />
    @else
        <div class="overflow-x-auto">
            <table class="tabla tabla-tarjetas">
                <thead>
                    <tr>
                        <th>Código</th><th>Nombre</th><th>Categoría</th><th>Sede</th><th>Ubicación</th><th>Estado</th><th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bienes as $bien)
                        <tr>
                            <td data-label="Código">
                                <span class="font-mono text-xs font-semibold text-slate-800">{{ $bien->codigo_barras }}</span>
                                @unless($bien->etiqueta_impresa_at)
                                    <span class="badge-ambar ml-1 text-[10px]! py-0.5!" title="Etiqueta aún no impresa">Sin etiqueta</span>
                                @endunless
                            </td>
                            <td data-label="Nombre">
                                <p class="font-medium text-slate-800">{{ $bien->nombre }}</p>
                                @if($bien->marca || $bien->modelo)
                                    <p class="text-xs text-slate-400">{{ trim($bien->marca.' '.$bien->modelo) }}</p>
                                @endif
                            </td>
                            <td data-label="Categoría" class="text-slate-600">{{ $bien->categoria->nombre ?? '—' }}</td>
                            <td data-label="Sede" class="text-slate-600 whitespace-nowrap">{{ $bien->sede?->codigo ? 'Sede '.substr($bien->sede->codigo, 1) : ($bien->sede->nombre ?? '—') }}</td>
                            <td data-label="Ubicación" class="text-slate-600">{{ $bien->area->nombre ?? 'Sin área' }}</td>
                            <td data-label="Estado"><x-estado-bien :estado="$bien->estado" /></td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('bienes.show', $bien) }}" class="btn-icono" title="Ver detalle" aria-label="Ver {{ $bien->nombre }}"><i class="fa-regular fa-eye"></i></a>
                                @can('update', $bien)
                                    <a href="{{ route('bienes.edit', $bien) }}" class="btn-icono" title="Editar" aria-label="Editar {{ $bien->nombre }}"><i class="fa-solid fa-pen"></i></a>
                                @endcan
                                @can('etiquetas.imprimir')
                                    <a href="{{ route('bienes.etiqueta', [$bien, 'html']) }}" target="_blank" class="btn-icono" title="Imprimir etiqueta" aria-label="Imprimir etiqueta de {{ $bien->nombre }}"><i class="fa-solid fa-tag"></i></a>
                                @endcan
                                @can('delete', $bien)
                                    <form method="POST" action="{{ route('bienes.destroy', $bien) }}" class="inline"
                                          data-confirmar="Solo se puede retirar un registro sin historial. Si el bien existe físicamente, solicite su baja." data-titulo="¿Retirar este registro?" data-aceptar="Retirar" data-peligro>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icono hover:text-rose-600" title="Retirar registro" aria-label="Retirar {{ $bien->nombre }}"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($bienes->hasPages())
            <div class="p-4 border-t border-slate-100">{{ $bienes->links() }}</div>
        @endif
    @endif
</section>
@endsection
