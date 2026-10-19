@extends('layouts.app')

@section('title', 'Áreas')

@section('content')
<div data-catalogo>
    <x-encabezado titulo="Áreas" subtitulo="Ambientes y oficinas de cada sede.">
        <x-slot:acciones>
            @can('areas.crear')
                <button type="button" class="btn-primario" data-nuevo><i class="fa-solid fa-plus"></i> Nueva área</button>
            @endcan
        </x-slot:acciones>
    </x-encabezado>

    @canany(['areas.crear', 'areas.editar'])
        <x-panel-catalogo :url-crear="route('areas.store')" titulo-crear="Nueva área" titulo-editar="Editar área">
            <x-campo nombre="sede_id" etiqueta="Sede" requerido>
                <select id="campo-sede_id" name="sede_id" required class="campo">
                    <option value="">Seleccione una sede</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" @selected(old('sede_id') == $sede->id)>{{ $sede->nombre }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo nombre="nombre" etiqueta="Nombre" requerido>
                <input type="text" id="campo-nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="150" placeholder="Secretaría Académica" class="campo">
            </x-campo>
            <x-campo nombre="codigo" etiqueta="Código">
                <input type="text" id="campo-codigo" name="codigo" value="{{ old('codigo') }}" maxlength="30" placeholder="SEC" class="campo font-mono uppercase">
            </x-campo>
            <x-campo nombre="tipo" etiqueta="Tipo">
                <input type="text" id="campo-tipo" name="tipo" value="{{ old('tipo') }}" maxlength="80" placeholder="Oficina, aula, laboratorio..." class="campo">
            </x-campo>
            <x-campo nombre="ubicacion" etiqueta="Ubicación física">
                <input type="text" id="campo-ubicacion" name="ubicacion" value="{{ old('ubicacion') }}" maxlength="255" placeholder="Pabellón A, 2.° piso" class="campo">
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
        <form method="GET" class="p-4 sm:p-5 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1fr_220px_180px_auto] gap-3">
            <label for="buscar" class="sr-only">Buscar</label>
            <input type="search" id="buscar" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre, código o ubicación..." class="campo">
            <label for="filtro-sede" class="sr-only">Sede</label>
            <select id="filtro-sede" name="sede_id" class="campo">
                <option value="">Todas las sedes</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede->id }}" @selected(request('sede_id') == $sede->id)>{{ $sede->nombre }}</option>
                @endforeach
            </select>
            <label for="filtro-estado" class="sr-only">Estado</label>
            <select id="filtro-estado" name="estado" class="campo">
                <option value="">Todos los estados</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activas</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivas</option>
            </select>
            <button type="submit" class="btn-secundario"><i class="fa-solid fa-filter"></i> Filtrar</button>
        </form>

        @if($areas->isEmpty())
            <x-estado-vacio icono="fa-sitemap" titulo="No hay áreas" :mensaje="request()->hasAny(['buscar', 'sede_id', 'estado']) ? 'Pruebe con otros filtros.' : 'Registre la primera área con «Nueva área».'" />
        @else
            <table class="tabla">
                <thead><tr><th>Área</th><th>Sede</th><th>Código</th><th>Bienes</th><th>Responsables</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
                <tbody>
                    @foreach($areas as $area)
                        <tr>
                            <td>
                                <p class="font-medium text-slate-800">{{ $area->nombre }}</p>
                                <p class="text-xs text-slate-400">{{ trim(($area->tipo ? $area->tipo.' · ' : '').$area->ubicacion, ' ·') }}</p>
                            </td>
                            <td class="text-slate-600">{{ $area->sede->nombre ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ $area->codigo ?? '—' }}</td>
                            <td>{{ $area->bienes_count }}</td>
                            <td>{{ $area->responsables_count }}</td>
                            <td><x-badge-estado-registro :estado="$area->estado" /></td>
                            <td class="text-right whitespace-nowrap" data-label="">
                                @can('areas.editar')
                                    <button type="button" class="btn-icono" title="Editar" aria-label="Editar {{ $area->nombre }}"
                                            data-editar="{{ json_encode(['url' => route('areas.update', $area), 'valores' => $area->only(['sede_id', 'nombre', 'codigo', 'tipo', 'ubicacion', 'estado'])]) }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                @endcan
                                @can('areas.eliminar')
                                    <form method="POST" action="{{ route('areas.destroy', $area) }}" class="inline" data-confirmar="Solo se puede eliminar un área sin bienes ni responsables. Si los tiene, desactívela." data-titulo="¿Eliminar el área?" data-aceptar="Eliminar" data-peligro>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icono hover:text-rose-600" title="Eliminar" aria-label="Eliminar {{ $area->nombre }}"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($areas->hasPages())<div class="p-4 border-t border-slate-100">{{ $areas->links() }}</div>@endif
        @endif
    </section>
</div>
@endsection
