@extends('layouts.app')

@section('title', 'Etiquetas')
@section('page_header', 'Etiquetas con código de barras')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Impresión de etiquetas</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Formato actual: <strong>{{ $generador->configuracion()->descripcion() }}</strong>
                · módulo {{ $generador->anchoModuloEfectivo() }} pt
                @can('configuracion.ver')
                    · <a href="{{ route('configuracion.etiquetas') }}" class="text-indigo-600 hover:underline">cambiar formato</a>
                @endcan
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('etiquetas.prueba', 'html') }}" target="_blank" class="px-3.5 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-ruler-combined"></i> Página de prueba
            </a>
        </div>
    </div>

    <!-- Filtros del lote -->
    <form method="GET" action="{{ route('etiquetas.index') }}" class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
        <div class="lg:col-span-2">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Buscar</label>
            <input type="text" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Código o nombre..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Sede</label>
            <select name="sede_id" id="filtro_sede" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
                <option value="">Todas</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede->id }}" @selected(($filtros['sede_id'] ?? null) == $sede->id)>{{ $sede->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Área</label>
            <select name="area_id" data-sede-select="filtro_sede" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
                <option value="">Todas</option>
                @foreach($areas as $area)
                    <option value="{{ $area->id }}" data-sede="{{ $area->sede_id }}" @selected(($filtros['area_id'] ?? null) == $area->id)>{{ $area->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Categoría</label>
            <select name="categoria_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
                <option value="">Todas</option>
                @foreach($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected(($filtros['categoria_id'] ?? null) == $categoria->id)>{{ $categoria->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-2">
            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                <input type="checkbox" name="solo_sin_etiqueta" value="1" @checked($filtros['solo_sin_etiqueta'] ?? false) class="w-4 h-4 accent-[#0b3b70]">
                Solo sin etiqueta
            </label>
            <button type="submit" class="px-3 py-2 bg-slate-800 text-white rounded-lg text-sm font-medium"><i class="fa-solid fa-filter mr-1"></i> Filtrar</button>
        </div>
    </form>

    <!-- Lote: selección o todo lo filtrado -->
    <form method="POST" action="{{ route('etiquetas.generar') }}" target="_blank" id="form-lote" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        @csrf
        @foreach(['buscar', 'sede_id', 'area_id', 'categoria_id', 'solo_sin_etiqueta'] as $campo)
            @if(filled($filtros[$campo] ?? null))
                <input type="hidden" name="{{ $campo }}" value="{{ $filtros[$campo] }}">
            @endif
        @endforeach
        <input type="hidden" name="alcance" id="alcance" value="seleccion">

        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                <strong class="text-slate-800">{{ $total }}</strong> bienes coinciden ·
                <span id="contador-seleccion" class="font-semibold text-indigo-700">0 seleccionados</span>
                @if($total > $bienes->count())
                    <span class="block text-[11px] text-amber-600">Se muestran los primeros {{ $bienes->count() }}. Afine el filtro para imprimir el resto.</span>
                @endif
            </p>
            @can('etiquetas.imprimir')
                <div class="flex flex-wrap items-center gap-2">
                    <select id="alcance-select" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold">
                        <option value="seleccion">Imprimir los seleccionados</option>
                        <option value="filtro">Imprimir todos los filtrados ({{ $total }})</option>
                    </select>
                    <button type="submit" name="formato" value="html" class="px-3.5 py-2 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-lg text-xs font-bold flex items-center gap-1.5"><i class="fa-solid fa-print"></i> Imprimir</button>
                    <button type="submit" name="formato" value="pdf" class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold flex items-center gap-1.5"><i class="fa-solid fa-file-pdf"></i> PDF</button>
                    <button type="submit" name="formato" value="zip" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold flex items-center gap-1.5" title="Un PNG por etiqueta, para apps de impresoras Bluetooth"><i class="fa-solid fa-file-zipper"></i> PNG (ZIP)</button>
                </div>
            @endcan
        </div>

        <div class="overflow-x-auto max-h-[60vh] overflow-y-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-400 uppercase text-xs font-semibold border-b border-slate-100 sticky top-0">
                    <tr>
                        <th class="py-3 px-4 w-10"><input type="checkbox" id="marcar-todos" class="w-4 h-4 accent-[#0b3b70]" title="Seleccionar todos"></th>
                        <th class="py-3 px-4">Código de barras</th>
                        <th class="py-3 px-4">Bien</th>
                        <th class="py-3 px-4">Sede / Área</th>
                        <th class="py-3 px-4">Última impresión</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bienes as $bien)
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-2.5 px-4"><input type="checkbox" name="bien_ids[]" value="{{ $bien->id }}" class="casilla-bien w-4 h-4 accent-[#0b3b70]"></td>
                            <td class="py-2.5 px-4 font-mono font-bold text-slate-800">{{ $bien->codigo_barras }}</td>
                            <td class="py-2.5 px-4">
                                {{ $bien->nombre }}
                                <span class="block text-[11px] text-slate-400">{{ $bien->categoria->nombre ?? '' }}</span>
                            </td>
                            <td class="py-2.5 px-4 text-xs text-slate-600">
                                {{ $bien->sede->nombre ?? '' }}
                                <span class="block text-[11px] text-slate-400">{{ $bien->area->nombre ?? 'Sin área' }}</span>
                            </td>
                            <td class="py-2.5 px-4 text-xs">
                                @if($bien->etiqueta_impresa_at)
                                    <span class="font-mono text-slate-500">{{ $bien->etiqueta_impresa_at->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 font-semibold text-[11px]">Sin etiqueta</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400 italic">No hay bienes con esos criterios.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
</div>

@include('partials.filtro-areas-por-sede')

<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        const casillas = document.querySelectorAll('.casilla-bien');
        const todos = document.getElementById('marcar-todos');
        const contador = document.getElementById('contador-seleccion');
        const alcance = document.getElementById('alcance');
        const alcanceSelect = document.getElementById('alcance-select');

        const actualizar = () => {
            const n = [...casillas].filter((c) => c.checked).length;
            contador.textContent = n + (n === 1 ? ' seleccionado' : ' seleccionados');
            todos.checked = n > 0 && n === casillas.length;
        };

        todos.addEventListener('change', () => { casillas.forEach((c) => (c.checked = todos.checked)); actualizar(); });
        casillas.forEach((c) => c.addEventListener('change', actualizar));
        if (alcanceSelect) {
            alcanceSelect.addEventListener('change', () => (alcance.value = alcanceSelect.value));
        }

        // Tras imprimir, recarga la lista para reflejar la nueva fecha de impresión.
        document.getElementById('form-lote').addEventListener('submit', (evento) => {
            if (alcance.value === 'seleccion' && ![...casillas].some((c) => c.checked)) {
                evento.preventDefault();
                contador.textContent = 'Seleccione al menos un bien o elija "todos los filtrados".';
                contador.classList.add('text-rose-600');
                return;
            }
            setTimeout(() => window.location.reload(), 1500);
        });
        actualizar();
    })();
</script>
@endsection
