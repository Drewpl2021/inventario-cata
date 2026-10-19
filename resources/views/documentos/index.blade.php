@extends('layouts.app')

@section('title', 'Documentos')

@php($tipos = ['factura' => 'Factura de compra', 'acta' => 'Acta de entrega o recepción', 'garantia' => 'Certificado de garantía', 'informe' => 'Informe técnico', 'baja' => 'Resolución de baja', 'otro' => 'Otro documento'])

@section('content')
<div data-catalogo>
    <x-encabezado titulo="Documentos" subtitulo="Archivo documentario de los bienes: facturas, actas, garantías e informes.">
        <x-slot:acciones>
            @can('documentos.crear')
                <button type="button" class="btn-primario" data-nuevo><i class="fa-solid fa-plus"></i> Registrar documento</button>
            @endcan
        </x-slot:acciones>
    </x-encabezado>

    @can('documentos.crear')
        <x-panel-catalogo :url-crear="route('documentos.store')" enctype="multipart/form-data" titulo-crear="Registrar documento" titulo-editar="Registrar documento">
            <x-campo nombre="bien_id" etiqueta="Bien" requerido ancho="lg:col-span-2">
                <select id="campo-bien_id" name="bien_id" required class="campo">
                    <option value="">Seleccione un bien</option>
                    @foreach($bienes as $bien)
                        <option value="{{ $bien->id }}" @selected(old('bien_id') == $bien->id)>{{ $bien->codigo_barras }} · {{ $bien->nombre }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo nombre="tipo" etiqueta="Tipo" requerido>
                <select id="campo-tipo" name="tipo" required data-por-defecto="factura" class="campo">
                    @foreach($tipos as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo nombre="nombre" etiqueta="Nombre del documento" requerido ancho="lg:col-span-2">
                <input type="text" id="campo-nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="255" placeholder="Factura electrónica F001-1234" class="campo">
            </x-campo>
            <x-campo nombre="archivo" etiqueta="Archivo PDF (hasta 10 MB)" requerido>
                <input type="file" id="campo-archivo" name="archivo" required accept="application/pdf" class="campo file:mr-3 file:rounded-lg file:border-0 file:bg-marino-50 file:px-3 file:py-1.5 file:text-marino-800 file:font-semibold">
            </x-campo>
        </x-panel-catalogo>
    @endcan

    <section class="tarjeta overflow-hidden">
        <form method="GET" class="p-4 sm:p-5 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-[1fr_240px_auto] gap-3">
            <label for="buscar" class="sr-only">Buscar</label>
            <input type="search" id="buscar" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por documento o bien..." class="campo">
            <label for="filtro-tipo" class="sr-only">Tipo</label>
            <select id="filtro-tipo" name="tipo" class="campo">
                <option value="">Todos los tipos</option>
                @foreach($tipos as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secundario"><i class="fa-solid fa-filter"></i> Filtrar</button>
        </form>

        @if($documentos->isEmpty())
            <x-estado-vacio icono="fa-folder-open" titulo="No hay documentos" :mensaje="request()->hasAny(['buscar', 'tipo']) ? 'Pruebe con otros filtros.' : null" />
        @else
            <table class="tabla">
                <thead><tr><th>Documento</th><th>Tipo</th><th>Bien</th><th>Registrado</th><th class="text-right">Acciones</th></tr></thead>
                <tbody>
                    @foreach($documentos as $documento)
                        <tr>
                            <td class="font-medium text-slate-800">{{ $documento->nombre }}</td>
                            <td class="text-slate-600">{{ $tipos[$documento->tipo] ?? $documento->tipo }}</td>
                            <td>
                                @if($documento->bien)
                                    <a href="{{ route('bienes.show', $documento->bien) }}" class="text-marino-700 hover:underline">{{ $documento->bien->nombre }}</a>
                                    <span class="block font-mono text-xs text-slate-400">{{ $documento->bien->codigo_barras }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-slate-500 whitespace-nowrap">{{ $documento->created_at?->format('d/m/Y') }}<span class="block text-xs">{{ $documento->usuario?->nombre_completo }}</span></td>
                            <td class="text-right whitespace-nowrap" data-label="">
                                <a href="{{ $documento->url }}" target="_blank" class="btn-icono" title="Abrir" aria-label="Abrir {{ $documento->nombre }}"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                                @can('documentos.eliminar')
                                    <form method="POST" action="{{ route('documentos.destroy', $documento) }}" class="inline" data-confirmar="El registro del documento se eliminará." data-titulo="¿Eliminar el documento?" data-aceptar="Eliminar" data-peligro>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icono hover:text-rose-600" title="Eliminar" aria-label="Eliminar {{ $documento->nombre }}"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($documentos->hasPages())<div class="p-4 border-t border-slate-100">{{ $documentos->links() }}</div>@endif
        @endif
    </section>
</div>
@endsection
