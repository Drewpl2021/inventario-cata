@extends('layouts.app')

@section('title', 'Bandeja de aprobación')

@section('content')
<x-encabezado titulo="Bandeja de aprobación" subtitulo="Solicitudes pendientes de bajas, movimientos y requerimientos." />

@if($pestanas->isEmpty())
    <div class="tarjeta"><x-estado-vacio icono="fa-inbox" titulo="No tiene solicitudes que aprobar" mensaje="Su rol no aprueba bajas, movimientos ni requerimientos." /></div>
@else
<section class="tarjeta overflow-hidden" data-pestanas data-activa="{{ $activa }}">
    <div class="flex overflow-x-auto border-b border-slate-200 px-2" role="tablist">
        @foreach($pestanas as $clave => [$titulo, $icono])
            <button type="button" role="tab" data-pestana="{{ $clave }}" class="min-h-12 px-4 text-sm font-semibold whitespace-nowrap border-b-2 border-transparent text-slate-500 hover:text-marino-800 inline-flex items-center gap-2">
                <i class="fa-solid {{ $icono }}"></i> {{ $titulo }}
                <span class="{{ $pendientes[$clave]->isEmpty() ? 'badge-gris' : 'badge-ambar' }}">{{ $pendientes[$clave]->count() }}</span>
            </button>
        @endforeach
    </div>

    {{-- Bajas --}}
    @if($pestanas->has('bajas'))
        <div data-panel="bajas" role="tabpanel">
            @forelse($pendientes['bajas'] as $baja)
                <article class="p-4 sm:p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center gap-4">
                    <div class="flex-1 min-w-0 text-sm">
                        <p class="font-semibold text-slate-800">{{ $baja->bien->nombre ?? '—' }} <span class="font-mono text-xs text-slate-500">{{ $baja->bien->codigo_barras ?? '' }}</span></p>
                        <p class="text-slate-500">Motivo: <strong class="text-slate-700">{{ str_replace('_', ' ', $baja->motivo) }}</strong> · {{ $baja->bien?->sede?->nombre }} / {{ $baja->bien?->area?->nombre ?? 'sin área' }}</p>
                        @if($baja->descripcion)<p class="text-slate-500 mt-1">{{ $baja->descripcion }}</p>@endif
                        <p class="text-xs text-slate-400 mt-1">Solicitado por {{ $baja->usuario?->nombre_completo }} · {{ $baja->created_at?->diffForHumans() }}{{ $baja->inventario_id ? ' · desde toma de inventario' : '' }}</p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <form method="POST" action="{{ route('bajas.aprobar', $baja) }}" data-confirmar="El bien quedará dado de baja y saldrá del inventario activo." data-titulo="¿Aprobar la baja?" data-aceptar="Aprobar baja">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-exito"><i class="fa-solid fa-check"></i> Aprobar</button>
                        </form>
                        <form method="POST" action="{{ route('bajas.rechazar', $baja) }}" data-confirmar="Indique por qué se rechaza: el solicitante verá el motivo." data-titulo="Rechazar la baja" data-aceptar="Rechazar" data-peligro data-pedir-motivo>
                            @csrf @method('PATCH')
                            <input type="hidden" name="motivo_rechazo">
                            <button type="submit" class="btn-secundario text-rose-700">Rechazar</button>
                        </form>
                    </div>
                </article>
            @empty
                <x-estado-vacio icono="fa-circle-check" titulo="No hay bajas pendientes" />
            @endforelse
        </div>
    @endif

    {{-- Movimientos --}}
    @if($pestanas->has('movimientos'))
        <div data-panel="movimientos" role="tabpanel" class="hidden">
            @forelse($pendientes['movimientos'] as $movimiento)
                <article class="p-4 sm:p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center gap-4">
                    <div class="flex-1 min-w-0 text-sm">
                        <p class="font-semibold text-slate-800">{{ $movimiento->bien->nombre ?? '—' }} <span class="font-mono text-xs text-slate-500">{{ $movimiento->bien->codigo_barras ?? '' }}</span></p>
                        <p class="text-slate-500">
                            {{ \App\Models\Movimiento::TIPOS[$movimiento->tipo] ?? $movimiento->tipo }}:
                            {{ $movimiento->sedeOrigen->nombre ?? '—' }} / {{ $movimiento->areaOrigen->nombre ?? 'sin área' }}
                            <i class="fa-solid fa-arrow-right mx-1 text-xs"></i>
                            <strong class="text-slate-700">{{ $movimiento->sedeDestino->nombre ?? '—' }} / {{ $movimiento->areaDestino->nombre ?? 'sin área' }}</strong>
                        </p>
                        @if($movimiento->motivo)<p class="text-slate-500 mt-1">{{ $movimiento->motivo }}</p>@endif
                        <p class="text-xs text-slate-400 mt-1">Solicitado por {{ $movimiento->usuario?->nombre_completo }} · {{ $movimiento->fecha_movimiento->diffForHumans() }}{{ $movimiento->inventario_id ? ' · desde toma de inventario' : '' }}</p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <form method="POST" action="{{ route('movimientos.aprobar', $movimiento) }}" data-confirmar="Se actualizarán la sede, el área y el responsable del bien." data-titulo="¿Aprobar el movimiento?" data-aceptar="Aprobar">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-exito"><i class="fa-solid fa-check"></i> Aprobar</button>
                        </form>
                        <form method="POST" action="{{ route('movimientos.rechazar', $movimiento) }}" data-confirmar="Indique por qué se rechaza: el solicitante verá el motivo." data-titulo="Rechazar el movimiento" data-aceptar="Rechazar" data-peligro data-pedir-motivo>
                            @csrf @method('PATCH')
                            <input type="hidden" name="motivo_rechazo">
                            <button type="submit" class="btn-secundario text-rose-700">Rechazar</button>
                        </form>
                    </div>
                </article>
            @empty
                <x-estado-vacio icono="fa-circle-check" titulo="No hay movimientos pendientes" />
            @endforelse
        </div>
    @endif

    {{-- Requerimientos --}}
    @if($pestanas->has('requerimientos'))
        <div data-panel="requerimientos" role="tabpanel" class="hidden">
            @forelse($pendientes['requerimientos'] as $requerimiento)
                <article class="p-4 sm:p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center gap-4">
                    <div class="flex-1 min-w-0 text-sm">
                        <p class="font-semibold text-slate-800">
                            <a href="{{ route('requerimientos.show', $requerimiento) }}" class="hover:underline">{{ $requerimiento->codigo }} · {{ $requerimiento->tipo_requerimiento }}</a>
                            <span class="{{ $requerimiento->estado === 'en_cotizacion' ? 'badge-azul' : 'badge-ambar' }} ml-1">{{ \App\Models\Requerimiento::ESTADOS[$requerimiento->estado] }}</span>
                        </p>
                        <p class="text-slate-500">{{ \Illuminate\Support\Str::limit($requerimiento->descripcion, 120) }}</p>
                        <p class="text-xs text-slate-400 mt-1">Solicitado por {{ $requerimiento->usuario?->nombre_completo }} · {{ $requerimiento->fecha_solicitud->diffForHumans() }} · Prioridad {{ $requerimiento->prioridad }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2 shrink-0">
                        @foreach($requerimiento->estadosSiguientes() as $siguiente)
                            @continue($siguiente === 'rechazado')
                            <form method="POST" action="{{ route('requerimientos.estado', $requerimiento) }}" data-confirmar="El requerimiento pasará a «{{ \App\Models\Requerimiento::ESTADOS[$siguiente] }}»." data-aceptar="Confirmar">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="{{ $siguiente }}">
                                <button type="submit" class="{{ $siguiente === 'aprobado' ? 'btn-exito' : 'btn-secundario' }}">{{ $siguiente === 'en_cotizacion' ? 'Pasar a cotización' : 'Aprobar' }}</button>
                            </form>
                        @endforeach
                        <form method="POST" action="{{ route('requerimientos.estado', $requerimiento) }}" data-confirmar="Indique por qué se rechaza: el solicitante verá el motivo." data-titulo="Rechazar el requerimiento" data-aceptar="Rechazar" data-peligro data-pedir-motivo>
                            @csrf @method('PATCH')
                            <input type="hidden" name="estado" value="rechazado">
                            <input type="hidden" name="motivo_rechazo">
                            <button type="submit" class="btn-secundario text-rose-700">Rechazar</button>
                        </form>
                    </div>
                </article>
            @empty
                <x-estado-vacio icono="fa-circle-check" titulo="No hay requerimientos pendientes" />
            @endforelse
        </div>
    @endif
</section>
@endif
@endsection
