@extends('layouts.app')

@section('title', 'Escanear · '.$inventario->codigo)
@section('page_header', 'Toma de inventario › Escanear')

@section('content')
<div class="max-w-3xl mx-auto space-y-4" id="escaner">
    {{-- Encabezado y avance --}}
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-[11px] font-mono font-bold text-slate-400">{{ $inventario->codigo }}</p>
                <h1 class="text-lg font-bold text-slate-800 leading-tight">{{ $inventario->nombre }}</h1>
                <p class="text-xs text-slate-500">{{ $inventario->ambito() }}</p>
            </div>
            <div class="flex flex-col items-end gap-1">
                <span id="indicador-conexion" class="badge-verde">
                    <i class="fa-solid fa-wifi"></i> En línea
                </span>
                <span id="indicador-pendientes" class="hidden badge-azul">
                    <i class="fa-solid fa-cloud-arrow-up"></i> <span id="numero-pendientes">0</span> pendientes de sincronizar
                </span>
            </div>
        </div>

        <div>
            <div class="flex items-baseline justify-between text-sm">
                <span class="font-bold text-slate-800"><span id="escaneados">0</span> / <span id="esperados">0</span> esperados</span>
                <span id="porcentaje" class="font-mono font-bold text-[#0b3b70]">0 %</span>
            </div>
            <div class="h-3 bg-slate-100 rounded-full overflow-hidden mt-1">
                <div id="barra-avance" class="h-full bg-emerald-500 transition-all duration-500" style="width: 0%"></div>
            </div>
            <div class="flex flex-wrap gap-1.5 mt-2 text-[11px] font-semibold">
                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Encontrados <span data-total="encontrado">0</span></span>
                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Fuera de ubicación <span data-total="fuera_de_ubicacion">0</span></span>
                <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-800">De baja <span data-total="dado_de_baja">0</span></span>
                <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800">No registrados <span data-total="sobrante_no_registrado">0</span></span>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">Por encontrar <span data-total="faltante">0</span></span>
            </div>
        </div>
    </div>

    {{-- Controles --}}
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label for="area-escaneo" class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Área donde está contando</label>
            @if($inventario->area_id)
                <div class="px-3 py-2 bg-slate-100 border border-slate-200 rounded-lg text-sm text-slate-700">{{ $inventario->area->nombre }}</div>
            @else
                <select id="area-escaneo" class="campo">
                    <option value="">Toda la sede (sin verificar el área)</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}">{{ $area->nombre }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        <div>
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Estado físico por defecto</span>
            <div class="grid grid-cols-4 gap-1" id="estado-por-defecto">
                @foreach(['' => 'Sin marcar', 'bueno' => 'Bueno', 'regular' => 'Regular', 'malo' => 'Malo'] as $valor => $etiqueta)
                    <button type="button" data-estado="{{ $valor }}" class="px-2 py-2 rounded-lg text-xs font-bold border border-slate-200 bg-white text-slate-600">{{ $etiqueta }}</button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Entrada: lectora tipo teclado o cámara --}}
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
        <div class="flex gap-2">
            <button type="button" id="modo-lectora" class="btn-primario flex-1"><i class="fa-solid fa-barcode"></i> Lectora</button>
            <button type="button" id="modo-camara" class="btn-secundario flex-1"><i class="fa-solid fa-camera"></i> Cámara</button>
        </div>

        <form id="form-lectura" autocomplete="off">
            <label for="codigo" class="sr-only">Código</label>
            <input type="text" id="codigo" name="codigo" autofocus autocomplete="off" autocapitalize="characters" spellcheck="false"
                   placeholder="Escanee o escriba el código"
                   class="w-full px-4 py-4 text-xl font-mono font-bold text-center bg-slate-50 border-2 border-[#0b3b70]/30 focus:border-[#0b3b70] focus:outline-none rounded-xl">
        </form>
        <label class="inline-flex items-center gap-2 text-sm text-slate-600 min-h-11">
            <input type="checkbox" id="ocultar-teclado" class="w-5 h-5 accent-marino-800">
            Ocultar el teclado del celular (cuando usa una lectora Bluetooth)
        </label>

        <div id="panel-camara" class="hidden space-y-2">
            <div id="aviso-https" class="hidden p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                <p class="font-bold"><i class="fa-solid fa-lock-open"></i> La cámara necesita una conexión segura (HTTPS)</p>
                <p class="text-xs mt-1">El navegador solo permite usar la cámara en sitios <strong>https://</strong> o en <strong>localhost</strong>. Abra el sistema con su dirección HTTPS, o use una lectora de códigos.</p>
            </div>
            <div id="lector-camara" class="w-full max-w-md mx-auto rounded-xl overflow-hidden bg-black"></div>
            <p id="estado-camara" class="text-center text-xs text-slate-500"></p>
        </div>
    </div>

    {{-- Resultado de la última lectura --}}
    <div id="tarjeta-resultado" class="hidden rounded-2xl p-5 text-white shadow-lg transition-colors" aria-live="assertive">
        <p id="resultado-titulo" class="text-2xl sm:text-3xl font-extrabold"></p>
        <p id="resultado-codigo" class="font-mono text-lg font-bold opacity-90"></p>
        <p id="resultado-bien" class="text-base font-semibold mt-1"></p>
        <p id="resultado-mensaje" class="text-sm mt-1 opacity-95"></p>
        <div id="resultado-acciones" class="flex flex-wrap items-center gap-2 mt-3">
            <span class="text-xs font-bold opacity-90">Estado físico:</span>
            @foreach(\App\Models\InventarioDetalle::ESTADOS_FISICOS as $valor => $etiqueta)
                <button type="button" data-estado-lectura="{{ $valor }}" class="min-h-11 px-4 rounded-xl text-sm font-bold bg-white/20 hover:bg-white/35 border border-white/40">{{ $etiqueta }}</button>
            @endforeach
            <button type="button" id="boton-deshacer" class="ml-auto min-h-11 px-4 rounded-xl text-sm font-bold bg-black/25 hover:bg-black/40"><i class="fa-solid fa-rotate-left"></i> Deshacer</button>
        </div>
    </div>

    {{-- Últimas lecturas --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-800">Últimas 20 lecturas</h2>
            <span class="text-[11px] text-slate-400">Incluye las de otros usuarios</span>
        </div>
        <ul id="lista-lecturas" class="divide-y divide-slate-100 text-sm">
            <li class="px-4 py-6 text-center text-slate-400 italic">Aún no hay lecturas.</li>
        </ul>
    </div>

    <div class="flex flex-wrap justify-between gap-2">
        <a href="{{ route('inventarios.show', $inventario) }}" class="btn-secundario">Ver avance detallado</a>
        @can('cerrar', $inventario)
            <form method="POST" action="{{ route('inventarios.cerrar', $inventario) }}" id="form-cerrar">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn-peligro"><i class="fa-solid fa-flag-checkered"></i> Cerrar campaña</button>
            </form>
        @endcan
    </div>
</div>

@php($configEscaneo = [
    'inventarioId' => $inventario->id,
    'areaFija' => $inventario->area_id,
    'sedeId' => $inventario->sede_id,
    'csrf' => csrf_token(),
    'urls' => [
        'lectura' => route('inventarios.lecturas', $inventario),
        'sincronizar' => route('inventarios.sincronizar', $inventario),
        'estado' => route('inventarios.estado', $inventario),
        'catalogo' => route('inventarios.catalogo', $inventario),
        'lecturas' => url('inventarios/'.$inventario->id.'/lecturas'),
    ],
])
<script type="application/json" id="config-escaneo">@json($configEscaneo)</script>
@push('scripts')
    @vite('resources/js/escaneo.js')
@endpush
@endsection
