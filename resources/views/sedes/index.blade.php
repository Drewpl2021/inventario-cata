@extends('layouts.app')

@section('title', 'Sedes')

@section('content')
<div data-catalogo>
    <x-encabezado titulo="Sedes" subtitulo="Sedes institucionales del colegio. El código corto (ej. S01) se usa en el código de barras.">
        <x-slot:acciones>
            @can('sedes.crear')
                <button type="button" class="btn-primario" data-nuevo><i class="fa-solid fa-plus"></i> Nueva sede</button>
            @endcan
        </x-slot:acciones>
    </x-encabezado>

    @canany(['sedes.crear', 'sedes.editar'])
        <x-panel-catalogo :url-crear="route('sedes.store')" titulo-crear="Nueva sede" titulo-editar="Editar sede">
            <x-campo nombre="codigo" etiqueta="Código" requerido>
                <input type="text" id="campo-codigo" name="codigo" value="{{ old('codigo') }}" required maxlength="20" placeholder="S01" class="campo font-mono uppercase">
            </x-campo>
            <x-campo nombre="nombre" etiqueta="Nombre" requerido ancho="lg:col-span-2">
                <input type="text" id="campo-nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="150" placeholder="Sede 01 - Administración" class="campo">
            </x-campo>
            <x-campo nombre="direccion" etiqueta="Dirección" ancho="lg:col-span-2">
                <input type="text" id="campo-direccion" name="direccion" value="{{ old('direccion') }}" maxlength="255" class="campo">
            </x-campo>
            <x-campo nombre="telefono" etiqueta="Teléfono">
                <input type="tel" id="campo-telefono" name="telefono" value="{{ old('telefono') }}" maxlength="30" class="campo">
            </x-campo>
            <x-campo nombre="estado" etiqueta="Estado">
                <select id="campo-estado" name="estado" data-por-defecto="activo" class="campo">
                    <option value="activo" @selected(old('estado', 'activo') === 'activo')>Activa</option>
                    <option value="inactivo" @selected(old('estado') === 'inactivo')>Inactiva</option>
                </select>
            </x-campo>
        </x-panel-catalogo>
    @endcanany

    <section class="tarjeta overflow-hidden">
        <form method="GET" class="p-4 sm:p-5 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-[1fr_200px_auto] gap-3">
            <label for="buscar" class="sr-only">Buscar</label>
            <input type="search" id="buscar" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre o código..." class="campo">
            <label for="filtro-estado" class="sr-only">Estado</label>
            <select id="filtro-estado" name="estado" class="campo">
                <option value="">Todos los estados</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activas</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivas</option>
            </select>
            <button type="submit" class="btn-secundario"><i class="fa-solid fa-filter"></i> Filtrar</button>
        </form>

        @if($sedes->isEmpty())
            <x-estado-vacio icono="fa-building" titulo="No hay sedes" :mensaje="request()->hasAny(['buscar', 'estado']) ? 'Pruebe con otros filtros.' : 'Registre la primera sede con «Nueva sede».'" />
        @else
            <table class="tabla">
                <thead><tr><th>Código</th><th>Nombre</th><th>Áreas</th><th>Bienes</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
                <tbody>
                    @foreach($sedes as $sede)
                        <tr>
                            <td class="font-mono font-semibold text-marino-800">{{ $sede->codigo }}</td>
                            <td>
                                <p class="font-medium text-slate-800">{{ $sede->nombre }}</p>
                                <p class="text-xs text-slate-400">{{ trim($sede->direccion.' '.($sede->telefono ? '· '.$sede->telefono : '')) }}</p>
                            </td>
                            <td>{{ $sede->areas_count }}</td>
                            <td>{{ $sede->bienes_count }}</td>
                            <td><x-badge-estado-registro :estado="$sede->estado" /></td>
                            <td class="text-right whitespace-nowrap" data-label="">
                                @can('sedes.editar')
                                    <button type="button" class="btn-icono" title="Editar" aria-label="Editar {{ $sede->nombre }}"
                                            data-editar="{{ json_encode(['url' => route('sedes.update', $sede), 'valores' => $sede->only(['codigo', 'nombre', 'direccion', 'telefono', 'estado'])]) }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                @endcan
                                @can('sedes.eliminar')
                                    <form method="POST" action="{{ route('sedes.destroy', $sede) }}" class="inline" data-confirmar="Solo se puede eliminar una sede sin áreas, bienes ni responsables. Si los tiene, desactívela." data-titulo="¿Eliminar la sede?" data-aceptar="Eliminar" data-peligro>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icono hover:text-rose-600" title="Eliminar" aria-label="Eliminar {{ $sede->nombre }}"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($sedes->hasPages())<div class="p-4 border-t border-slate-100">{{ $sedes->links() }}</div>@endif
        @endif
    </section>
</div>
@endsection
