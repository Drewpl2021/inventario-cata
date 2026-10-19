@extends('layouts.app')

@section('title', 'Responsables')

@section('content')
<div data-catalogo>
    <x-encabezado titulo="Responsables" subtitulo="Personas a cargo de los bienes. Las cuentas de rol «Usuario» se vinculan con su responsable.">
        <x-slot:acciones>
            @can('responsables.crear')
                <button type="button" class="btn-primario" data-nuevo><i class="fa-solid fa-plus"></i> Nuevo responsable</button>
            @endcan
        </x-slot:acciones>
    </x-encabezado>

    @canany(['responsables.crear', 'responsables.editar'])
        <x-panel-catalogo :url-crear="route('responsables.store')" titulo-crear="Nuevo responsable" titulo-editar="Editar responsable">
            <x-campo nombre="nombres" etiqueta="Nombres" requerido>
                <input type="text" id="campo-nombres" name="nombres" value="{{ old('nombres') }}" required maxlength="100" class="campo">
            </x-campo>
            <x-campo nombre="apellidos" etiqueta="Apellidos" requerido>
                <input type="text" id="campo-apellidos" name="apellidos" value="{{ old('apellidos') }}" required maxlength="100" class="campo">
            </x-campo>
            <x-campo nombre="dni" etiqueta="DNI">
                <input type="text" id="campo-dni" name="dni" value="{{ old('dni') }}" inputmode="numeric" maxlength="20" class="campo font-mono">
            </x-campo>
            <x-campo nombre="cargo" etiqueta="Cargo">
                <input type="text" id="campo-cargo" name="cargo" value="{{ old('cargo') }}" maxlength="100" placeholder="Docente, secretaria..." class="campo">
            </x-campo>
            <x-campo nombre="sede_id" etiqueta="Sede">
                <select id="campo-sede_id" name="sede_id" class="campo">
                    <option value="">Sin sede</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" @selected(old('sede_id') == $sede->id)>{{ $sede->nombre }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo nombre="area_id" etiqueta="Área">
                <select id="campo-area_id" name="area_id" data-sede-select="campo-sede_id" class="campo">
                    <option value="">Sin área</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}" data-sede="{{ $area->sede_id }}" @selected(old('area_id') == $area->id)>{{ $area->nombre }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo nombre="email" etiqueta="Correo electrónico">
                <input type="email" id="campo-email" name="email" value="{{ old('email') }}" maxlength="150" class="campo">
            </x-campo>
            <x-campo nombre="telefono" etiqueta="Teléfono">
                <input type="tel" id="campo-telefono" name="telefono" value="{{ old('telefono') }}" maxlength="30" class="campo">
            </x-campo>
            <x-campo nombre="estado" etiqueta="Estado">
                <select id="campo-estado" name="estado" data-por-defecto="activo" class="campo">
                    <option value="activo" @selected(old('estado', 'activo') === 'activo')>Activo</option>
                    <option value="inactivo" @selected(old('estado') === 'inactivo')>Inactivo</option>
                </select>
            </x-campo>
        </x-panel-catalogo>
    @endcanany

    <section class="tarjeta overflow-hidden">
        <form method="GET" class="p-4 sm:p-5 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1fr_220px_180px_auto] gap-3">
            <label for="buscar" class="sr-only">Buscar</label>
            <input type="search" id="buscar" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre, DNI o cargo..." class="campo">
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
                <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
            </select>
            <button type="submit" class="btn-secundario"><i class="fa-solid fa-filter"></i> Filtrar</button>
        </form>

        @if($responsables->isEmpty())
            <x-estado-vacio icono="fa-id-card" titulo="No hay responsables" :mensaje="request()->hasAny(['buscar', 'sede_id', 'estado']) ? 'Pruebe con otros filtros.' : 'Registre el primero con «Nuevo responsable».'" />
        @else
            <table class="tabla">
                <thead><tr><th>Responsable</th><th>Cargo</th><th>Sede / Área</th><th>Contacto</th><th>Bienes</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
                <tbody>
                    @foreach($responsables as $responsable)
                        <tr>
                            <td>
                                <p class="font-medium text-slate-800">{{ $responsable->nombre_completo }}</p>
                                <p class="text-xs text-slate-400">{{ $responsable->dni ? 'DNI '.$responsable->dni : 'Sin DNI' }}
                                    @if($responsable->usuario) · <span class="text-emerald-700"><i class="fa-solid fa-link"></i> Con cuenta</span>@endif
                                </p>
                            </td>
                            <td class="text-slate-600">{{ $responsable->cargo ?? '—' }}</td>
                            <td class="text-slate-600">
                                {{ $responsable->sede->nombre ?? '—' }}
                                <span class="block text-xs text-slate-400">{{ $responsable->area->nombre ?? 'Sin área' }}</span>
                            </td>
                            <td class="text-slate-600 text-xs">{{ $responsable->email ?? '—' }}<span class="block">{{ $responsable->telefono }}</span></td>
                            <td>{{ $responsable->bienes_count }}</td>
                            <td><x-badge-estado-registro :estado="$responsable->estado" /></td>
                            <td class="text-right whitespace-nowrap" data-label="">
                                @can('responsables.editar')
                                    <button type="button" class="btn-icono" title="Editar" aria-label="Editar {{ $responsable->nombre_completo }}"
                                            data-editar="{{ json_encode(['url' => route('responsables.update', $responsable), 'valores' => $responsable->only(['nombres', 'apellidos', 'dni', 'cargo', 'sede_id', 'area_id', 'email', 'telefono', 'estado'])]) }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                @endcan
                                @can('responsables.eliminar')
                                    <form method="POST" action="{{ route('responsables.destroy', $responsable) }}" class="inline" data-confirmar="Solo se puede eliminar un responsable sin bienes ni cuenta vinculada. Si los tiene, desactívelo." data-titulo="¿Eliminar el responsable?" data-aceptar="Eliminar" data-peligro>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icono hover:text-rose-600" title="Eliminar" aria-label="Eliminar {{ $responsable->nombre_completo }}"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($responsables->hasPages())<div class="p-4 border-t border-slate-100">{{ $responsables->links() }}</div>@endif
        @endif
    </section>
</div>

@include('partials.filtro-areas-por-sede')
@endsection
