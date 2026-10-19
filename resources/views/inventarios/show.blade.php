@extends('layouts.app')

@section('title', $inventario->codigo)
@section('page_header', 'Toma de inventario › '.($inventario->estaAbierto() ? 'Avance' : 'Resultados'))

@php
    $pestanas = [
        'faltante' => ['Faltantes', 'bg-rose-600', 'Bienes esperados que no se escanearon.'],
        'fuera_de_ubicacion' => ['Fuera de ubicación', 'bg-amber-500', 'Encontrados en otra sede o área distinta de la registrada.'],
        'sobrante_no_registrado' => ['Sobrantes no registrados', 'bg-rose-500', 'Códigos leídos que no corresponden a ningún bien.'],
        'dado_de_baja' => ['De baja encontrados', 'bg-purple-600', 'Figuran dados de baja pero siguen físicamente.'],
        'encontrado' => ['Encontrados', 'bg-emerald-600', 'En su sede y área registradas.'],
    ];
    $cerrada = ! $inventario->estaAbierto();
@endphp

@section('content')
<div class="space-y-6">
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-mono font-bold text-slate-400">{{ $inventario->codigo }}</p>
            <h1 class="text-xl font-bold text-slate-800">{{ $inventario->nombre }}</h1>
            <p class="text-sm text-slate-500">{{ $inventario->ambito() }}</p>
            <p class="text-xs text-slate-400 mt-1">
                Del {{ $inventario->fecha_inicio->format('d/m/Y') }}{{ $inventario->fecha_fin ? ' al '.$inventario->fecha_fin->format('d/m/Y') : '' }}
                · Responsables: {{ $inventario->responsables->pluck('nombre_completo')->join(', ') }}
                @if($cerrada)
                    · Cerrada el {{ $inventario->cerrado_at->format('d/m/Y H:i') }} por {{ $inventario->cerradoPor?->nombre_completo }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('escanear', $inventario)
                <a href="{{ route('inventarios.escanear', $inventario) }}" class="px-3.5 py-2 bg-[#0b3b70] text-white rounded-lg text-xs font-bold"><i class="fa-solid fa-barcode"></i> Escanear</a>
            @endcan
            @can('exportar', $inventario)
                <a href="{{ route('inventarios.exportar', [$inventario, 'pdf']) }}" class="px-3.5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                <a href="{{ route('inventarios.exportar', [$inventario, 'xlsx']) }}" class="px-3.5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold"><i class="fa-solid fa-file-excel"></i> Excel</a>
            @endcan
            @can('reabrir', $inventario)
                <form method="POST" action="{{ route('inventarios.reabrir', $inventario) }}" data-confirmar="¿Reabrir la campaña? Se descartarán los faltantes calculados y se podrá seguir escaneando.">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="px-3.5 py-2 bg-amber-500 text-white rounded-lg text-xs font-bold"><i class="fa-solid fa-lock-open"></i> Reabrir</button>
                </form>
            @endcan
        </div>
    </div>

    {{-- Totales --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <p class="text-[11px] uppercase font-semibold text-slate-400">Avance</p>
            <p class="text-2xl font-extrabold text-[#0b3b70]">{{ $resumen['porcentaje'] }} %</p>
            <p class="text-[11px] text-slate-500">{{ $resumen['escaneados'] }} de {{ $resumen['esperados'] }} esperados</p>
        </div>
        @foreach($pestanas as $clave => [$titulo, $color])
            <a href="#{{ $clave }}" class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-slate-300">
                <p class="text-[11px] uppercase font-semibold text-slate-400 flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full {{ $color }}"></span> {{ $titulo }}</p>
                <p class="text-2xl font-extrabold text-slate-800">{{ $resumen[$clave] }}</p>
            </a>
        @endforeach
    </div>

    @if(! $cerrada)
        <div class="p-4 bg-sky-50 border border-sky-200 rounded-2xl text-sky-800 text-sm">
            <i class="fa-solid fa-circle-info"></i> La campaña sigue abierta: los faltantes son provisionales y se registran al cerrarla. Las acciones (movimiento, baja, registrar sobrante) se habilitan al cerrar.
        </div>
    @endif

    @foreach($pestanas as $clave => [$titulo, $color, $descripcion])
        @continue($clave === 'faltante' && ! $cerrada)
        @php($filas = $detalles->get($clave, collect()))
        <section id="{{ $clave }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full {{ $color }}"></span> {{ $titulo }} ({{ $filas->count() }})</h2>
                    <p class="text-[11px] text-slate-400">{{ $descripcion }}</p>
                </div>
            </div>
            @if($filas->isEmpty())
                <p class="px-4 py-4 text-sm text-slate-400 italic">Ninguno.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-400 uppercase text-[11px] font-semibold">
                            <tr>
                                <th class="py-2 px-4">Código</th>
                                <th class="py-2 px-4">Bien</th>
                                <th class="py-2 px-4">Ubicación registrada</th>
                                <th class="py-2 px-4">Contado en</th>
                                <th class="py-2 px-4">Estado físico</th>
                                @if($cerrada)
                                    <th class="py-2 px-4 text-right">Acción</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($filas as $detalle)
                                @php($bien = $detalle->bien)
                                <tr>
                                    <td class="py-2 px-4 font-mono font-bold text-slate-800">{{ $detalle->codigo_leido }}</td>
                                    <td class="py-2 px-4">
                                        @if($bien && $clave !== 'sobrante_no_registrado')
                                            <a href="{{ route('bienes.show', $bien) }}" class="text-indigo-700 hover:underline">{{ $bien->nombre }}</a>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-4 text-xs text-slate-600">
                                        @if($bien && $clave !== 'sobrante_no_registrado')
                                            {{ $bien->sede->nombre ?? '' }} / {{ $bien->area->nombre ?? 'sin área' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-2 px-4 text-xs text-slate-600">
                                        {{ $clave === 'faltante' ? 'No escaneado' : ($detalle->areaEscaneo->nombre ?? 'Sede, sin área') }}
                                        @if($clave !== 'faltante')
                                            <span class="block text-[11px] text-slate-400">{{ $detalle->escaneadoPor?->nombre_completo }} · {{ $detalle->fecha_hora->format('d/m H:i') }}{{ $detalle->registrado_offline ? ' · sin conexión' : '' }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-4 text-xs">{{ \App\Models\InventarioDetalle::ESTADOS_FISICOS[$detalle->estado_fisico] ?? '—' }}</td>
                                    @if($cerrada)
                                        <td class="py-2 px-4 text-right whitespace-nowrap">
                                            @if($clave === 'fuera_de_ubicacion' && $bien)
                                                @if($movimientos->has($bien->id))
                                                    <span class="text-xs text-slate-500">Movimiento {{ $movimientos[$bien->id] }}</span>
                                                @elsecan('solicitar', [$bien, 'movimientos'])
                                                    <a href="{{ route('movimientos.create', ['bien_id' => $bien->id, 'sede_destino_id' => $inventario->sede_id, 'area_destino_id' => $detalle->area_escaneo_id, 'inventario_id' => $inventario->id]) }}" class="px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg text-xs font-bold">Generar movimiento</a>
                                                @endif
                                            @elseif($clave === 'faltante' && $bien)
                                                @if($bajas->has($bien->id))
                                                    <span class="text-xs text-slate-500">Baja {{ $bajas[$bien->id] }}</span>
                                                @elsecan('solicitar', [$bien, 'bajas'])
                                                    <a href="{{ route('bajas.create', ['bien_id' => $bien->id, 'motivo' => 'perdida', 'inventario_id' => $inventario->id]) }}" class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg text-xs font-bold">Iniciar baja</a>
                                                @endif
                                            @elseif($clave === 'sobrante_no_registrado')
                                                @if($bien)
                                                    <span class="text-xs text-emerald-700">Registrado como <a href="{{ route('bienes.show', $bien) }}" class="font-mono font-bold hover:underline">{{ $bien->codigo_barras }}</a></span>
                                                @else
                                                    @can('create', \App\Models\Bien::class)
                                                        <a href="{{ route('bienes.create', array_filter([
                                                            'sede_id' => $inventario->sede_id,
                                                            'area_id' => $detalle->area_escaneo_id,
                                                            'inventario_detalle_id' => $detalle->id,
                                                            'codigo_patrimonial' => str_starts_with($detalle->codigo_leido, 'CAT-') ? null : $detalle->codigo_leido,
                                                        ])) }}" class="px-2.5 py-1 bg-sky-50 text-sky-700 border border-sky-200 rounded-lg text-xs font-bold">Registrar como bien</a>
                                                    @endcan
                                                @endif
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
</div>
@endsection
