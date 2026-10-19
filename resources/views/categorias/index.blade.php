@extends('layouts.app')

@section('title', 'Categorías')

@section('content')
<div data-catalogo>
    <x-encabezado titulo="Categorías" subtitulo="Clasificación de los bienes por tipo.">
        <x-slot:acciones>
            @can('categorias.crear')
                <button type="button" class="btn-primario" data-nuevo><i class="fa-solid fa-plus"></i> Nueva categoría</button>
            @endcan
        </x-slot:acciones>
    </x-encabezado>

    @canany(['categorias.crear', 'categorias.editar'])
        <x-panel-catalogo :url-crear="route('categorias.store')" titulo-crear="Nueva categoría" titulo-editar="Editar categoría">
            <x-campo nombre="nombre" etiqueta="Nombre" requerido>
                <input type="text" id="campo-nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="100" placeholder="Equipos informáticos" class="campo">
            </x-campo>
            <x-campo nombre="descripcion" etiqueta="Descripción">
                <input type="text" id="campo-descripcion" name="descripcion" value="{{ old('descripcion') }}" maxlength="255" class="campo">
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
            <input type="search" id="buscar" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre..." class="campo">
            <label for="filtro-estado" class="sr-only">Estado</label>
            <select id="filtro-estado" name="estado" class="campo">
                <option value="">Todos los estados</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activas</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivas</option>
            </select>
            <button type="submit" class="btn-secundario"><i class="fa-solid fa-filter"></i> Filtrar</button>
        </form>

        @if($categorias->isEmpty())
            <x-estado-vacio icono="fa-layer-group" titulo="No hay categorías" :mensaje="request()->hasAny(['buscar', 'estado']) ? 'Pruebe con otros filtros.' : 'Registre la primera categoría con «Nueva categoría».'" />
        @else
            <table class="tabla">
                <thead><tr><th>Categoría</th><th>Descripción</th><th>Bienes</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
                <tbody>
                    @foreach($categorias as $categoria)
                        <tr>
                            <td class="font-medium text-slate-800">{{ $categoria->nombre }}</td>
                            <td class="text-slate-500">{{ $categoria->descripcion ?: '—' }}</td>
                            <td>{{ $categoria->bienes_count }}</td>
                            <td><x-badge-estado-registro :estado="$categoria->estado" /></td>
                            <td class="text-right whitespace-nowrap" data-label="">
                                @can('categorias.editar')
                                    <button type="button" class="btn-icono" title="Editar" aria-label="Editar {{ $categoria->nombre }}"
                                            data-editar="{{ json_encode(['url' => route('categorias.update', $categoria), 'valores' => $categoria->only(['nombre', 'descripcion', 'estado'])]) }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                @endcan
                                @can('categorias.eliminar')
                                    <form method="POST" action="{{ route('categorias.destroy', $categoria) }}" class="inline" data-confirmar="Solo se puede eliminar una categoría sin bienes. Si los tiene, desactívela." data-titulo="¿Eliminar la categoría?" data-aceptar="Eliminar" data-peligro>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icono hover:text-rose-600" title="Eliminar" aria-label="Eliminar {{ $categoria->nombre }}"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($categorias->hasPages())<div class="p-4 border-t border-slate-100">{{ $categorias->links() }}</div>@endif
        @endif
    </section>
</div>
@endsection
