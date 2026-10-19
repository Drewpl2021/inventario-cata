@extends('layouts.app')

@section('title', $reporte->titulo())

@php
    $usa = fn (string $filtro) => in_array($filtro, $reporte->filtros(), true);
    $requerido = fn (string $filtro) => in_array($filtro, $reporte->filtrosRequeridos(), true);
    $parametros = array_filter($filtros, fn ($v) => filled($v));
    $columnas = $reporte->columnas();
    $grupos = $pagina ? \App\Reportes\ExportadorReportes::agrupadas($reporte, $pagina->getCollection()) : collect();
@endphp

@section('content')
<x-encabezado :titulo="$reporte->titulo()" :subtitulo="$reporte->descripcion()" :volver="route('reportes.index')" volver-texto="Reportes">
    <x-slot:acciones>
        @if($listo)
            @can('reportes.exportar')
                <a href="{{ route('reportes.exportar', [$reporte->clave(), 'pdf', ...$parametros]) }}" target="_blank" class="btn-peligro"><i class="fa-regular fa-file-pdf"></i> PDF</a>
                <a href="{{ route('reportes.exportar', [$reporte->clave(), 'xlsx', ...$parametros]) }}" class="btn-exito"><i class="fa-regular fa-file-excel"></i> Excel</a>
            @endcan
        @endif
    </x-slot:acciones>
</x-encabezado>

{{-- Filtros --}}
<form method="GET" class="tarjeta p-4 sm:p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
    @if($usa('inventario_id'))
        <div class="sm:col-span-2">
            <label for="filtro-inventario" class="etiqueta-campo">Campaña @if($requerido('inventario_id'))<span class="text-rose-500">*</span>@endif</label>
            <select id="filtro-inventario" name="inventario_id" class="campo">
                <option value="">Seleccione una campaña</option>
                @foreach($inventarios as $inventario)
                    <option value="{{ $inventario->id }}" @selected(($filtros['inventario_id'] ?? null) == $inventario->id)>{{ $inventario->codigo }} · {{ $inventario->nombre }} ({{ $inventario->estaAbierto() ? 'abierta' : 'cerrada' }})</option>
                @endforeach
            </select>
        </div>
    @endif
    @if($usa('responsable_id'))
        <div>
            <label for="filtro-responsable" class="etiqueta-campo">Responsable</label>
            <select id="filtro-responsable" name="responsable_id" class="campo">
                <option value="">Todos</option>
                @foreach($responsables as $responsable)
                    <option value="{{ $responsable->id }}" @selected(($filtros['responsable_id'] ?? null) == $responsable->id)>{{ $responsable->apellidos }}, {{ $responsable->nombres }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if($usa('sede_id'))
        <div>
            <label for="filtro-sede" class="etiqueta-campo">Sede</label>
            <select id="filtro-sede" name="sede_id" class="campo">
                <option value="">Todas</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede->id }}" @selected(($filtros['sede_id'] ?? null) == $sede->id)>{{ $sede->nombre }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if($usa('area_id'))
        <div>
            <label for="filtro-area" class="etiqueta-campo">Área</label>
            <select id="filtro-area" name="area_id" data-sede-select="filtro-sede" class="campo">
                <option value="">Todas</option>
                @foreach($areas as $area)
                    <option value="{{ $area->id }}" data-sede="{{ $area->sede_id }}" @selected(($filtros['area_id'] ?? null) == $area->id)>{{ $area->nombre }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if($usa('categoria_id'))
        <div>
            <label for="filtro-categoria" class="etiqueta-campo">Categoría</label>
            <select id="filtro-categoria" name="categoria_id" class="campo">
                <option value="">Todas</option>
                @foreach($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected(($filtros['categoria_id'] ?? null) == $categoria->id)>{{ $categoria->nombre }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if($usa('estado'))
        <div>
            <label for="filtro-estado" class="etiqueta-campo">{{ $reporte instanceof \App\Reportes\DadosDeBaja ? 'Motivo' : 'Estado' }}</label>
            <select id="filtro-estado" name="estado" class="campo">
                <option value="">{{ $reporte instanceof \App\Reportes\ReporteDeBienes ? 'Activos (sin dados de baja)' : 'Todos' }}</option>
                @foreach($reporte->estados() as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(($filtros['estado'] ?? null) === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if($usa('desde'))
        <div>
            <label for="filtro-desde" class="etiqueta-campo">Desde</label>
            <input type="date" id="filtro-desde" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="campo">
        </div>
        <div>
            <label for="filtro-hasta" class="etiqueta-campo">Hasta</label>
            <input type="date" id="filtro-hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="campo">
        </div>
    @endif
    <div class="flex gap-2">
        <button type="submit" class="btn-primario flex-1"><i class="fa-solid fa-eye"></i> Vista previa</button>
        <a href="{{ route('reportes.show', $reporte->clave()) }}" class="btn-secundario" title="Quitar filtros" aria-label="Quitar filtros"><i class="fa-solid fa-eraser"></i></a>
    </div>
    @if($usa('desde') && $reporte instanceof \App\Reportes\ReporteDeBienes)
        <p class="sm:col-span-2 lg:col-span-4 text-xs text-slate-400 -mt-2">Las fechas filtran por fecha de adquisición.</p>
    @endif
</form>

@if(! $listo)
    <div class="tarjeta"><x-estado-vacio icono="fa-filter" titulo="Elija los filtros obligatorios" mensaje="Seleccione la campaña para ver la vista previa." /></div>
@else
    {{-- Totales --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-cifra :titulo="'Total de '.$reporte->unidad()" :valor="number_format($contexto['totales']['cantidad'])" icono="fa-list-ol" />
        @if($contexto['totales']['valor'] !== null)
            <x-cifra titulo="Valor de adquisición" :valor="'S/ '.number_format($contexto['totales']['valor'], 2)" icono="fa-coins" color="verde" />
        @endif
        @if($contexto['totales']['grupos'])
            <x-cifra :titulo="'Grupos ('.\Illuminate\Support\Str::lower($reporte->tituloGrupo()).')'" :valor="count($contexto['totales']['grupos'])" icono="fa-layer-group" color="naranja" />
        @endif
    </div>

    @if($contexto['totales']['cantidad'] > $limiteExcel)
        <div class="tarjeta p-4 mb-6 flex items-start gap-3 bg-sky-50 border-sky-200 text-sky-900 text-sm">
            <i class="fa-solid fa-circle-info mt-0.5"></i>
            Este reporte tiene más de {{ number_format($limiteExcel) }} registros: el Excel se generará en segundo plano y le avisaremos en la campana cuando esté listo.
        </div>
    @endif

    <section class="tarjeta overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2 text-sm">
            <p class="font-semibold text-slate-800">Vista previa</p>
            <p class="text-slate-500">
                @if($pagina->hasPages())
                    Página {{ $pagina->currentPage() }} de {{ $pagina->lastPage() }}. El PDF y el Excel incluyen todo.
                @endif
                Generado el {{ $contexto['generado']->format('d/m/Y H:i') }} · {{ $contexto['generadoPor'] }}
            </p>
        </div>

        @if($contexto['filas']->isEmpty())
            <x-estado-vacio icono="fa-magnifying-glass" titulo="No hay registros con estos filtros" />
        @else
            <div class="overflow-x-auto">
                <table class="tabla tabla-compacta text-xs">
                    <thead>
                        <tr>@foreach($columnas as [$etiqueta, $tipo])<th @class(['text-right' => $tipo === 'moneda'])>{{ $etiqueta }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach($grupos as $grupo => $filas)
                            @if($grupo !== '')
                                <tr class="bg-marino-50/60">
                                    <td colspan="{{ count($columnas) }}" class="font-semibold text-marino-900">
                                        {{ $grupo }}
                                        <span class="font-normal text-slate-500">· {{ $contexto['totales']['grupos'][$grupo]['cantidad'] ?? $filas->count() }} {{ $reporte->unidad() }}@if(($contexto['totales']['grupos'][$grupo]['valor'] ?? null) !== null) · S/ {{ number_format($contexto['totales']['grupos'][$grupo]['valor'], 2) }}@endif</span>
                                    </td>
                                </tr>
                            @endif
                            @foreach($filas as $fila)
                                <tr>
                                    @foreach($columnas as $clave => [$etiqueta, $tipo])
                                        @php $valor = $fila[$clave] ?? null; @endphp
                                        <td data-label="{{ $etiqueta }}" @class(['text-right whitespace-nowrap' => $tipo === 'moneda', 'whitespace-nowrap' => in_array($tipo, ['fecha', 'fechahora'], true), 'font-mono whitespace-nowrap' => $clave === 'codigo'])>
                                            @if($valor === null || $valor === '')
                                                <span class="text-slate-300">—</span>
                                            @elseif($tipo === 'moneda')
                                                S/ {{ number_format($valor, 2) }}
                                            @elseif($tipo === 'fecha')
                                                {{ $valor->format('d/m/Y') }}
                                            @elseif($tipo === 'fechahora')
                                                {{ $valor->format('d/m/Y H:i') }}
                                            @else
                                                {{ $valor }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($pagina->hasPages())
                <div class="p-4 border-t border-slate-100">{{ $pagina->links() }}</div>
            @endif
        @endif
    </section>
@endif

@include('partials.filtro-areas-por-sede')
@endsection
