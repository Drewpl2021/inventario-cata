@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
<div class="space-y-6">
    <x-bienvenida :titulo="'¡Bienvenido, '.$usuario->nombre.'!'">
        Aquí puedes gestionar y controlar el inventario de bienes patrimoniales de las tres sedes del Colegio Adventista Túpac Amaru.
    </x-bienvenida>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <x-cifra titulo="Total de bienes" :valor="$totales['total']" icono="fa-clipboard-list" color="azul" />
        <x-cifra titulo="Bienes en buen estado" :valor="$totales['buen_estado']" icono="fa-circle-check" color="verde" />
        <x-cifra titulo="En mantenimiento" :valor="$totales['mantenimiento']" icono="fa-screwdriver-wrench" color="naranja" />
        <x-cifra titulo="Dados de baja" :valor="$totales['dados_de_baja']" icono="fa-ban" color="rojo" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        {{-- Distribución por sede --}}
        <section class="tarjeta p-5 sm:p-6">
            <h2 class="text-base font-bold text-slate-800">Distribución por sede</h2>
            @if($distribucion->sum('total') > 0)
                <div class="mt-4 flex flex-col sm:flex-row items-center gap-6">
                    <div class="relative w-44 h-44 shrink-0">
                        <canvas data-grafico-dona='@json(['etiquetas' => $distribucion->pluck('nombre'), 'valores' => $distribucion->pluck('total'), 'colores' => $distribucion->pluck('color')])' aria-label="Gráfico de bienes por sede" role="img"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-3xl font-bold text-slate-900">{{ $distribucion->sum('total') }}</span>
                            <span class="text-xs text-slate-500">Total de bienes</span>
                        </div>
                    </div>
                    <ul class="w-full space-y-3 text-sm">
                        @foreach($distribucion as $sede)
                            <li class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $sede['color'] }}"></span>
                                <span class="flex-1 text-slate-600 whitespace-nowrap">{{ $sede['nombre'] }}</span>
                                <span class="font-semibold text-slate-800 whitespace-nowrap">{{ $sede['total'] }} ({{ $sede['porcentaje'] }}%)</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <x-estado-vacio icono="fa-chart-pie" titulo="Aún no hay bienes registrados" />
            @endif
        </section>

        {{-- Últimos movimientos --}}
        <section class="tarjeta overflow-hidden">
            <div class="px-5 sm:px-6 pt-5 pb-3 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-800">Últimos movimientos</h2>
                @can('movimientos.ver')
                    <a href="{{ route('movimientos.index') }}" class="text-sm font-semibold text-marino-600 hover:text-marino-800 min-h-11 inline-flex items-center">Ver todos</a>
                @endcan
            </div>
            @if($ultimosMovimientos->isEmpty())
                <x-estado-vacio icono="fa-right-left" titulo="Sin movimientos registrados" />
            @else
                <table class="tabla tabla-tarjetas">
                    <thead>
                        <tr><th>Fecha</th><th>Bien</th><th>Sede</th><th>Movimiento</th></tr>
                    </thead>
                    <tbody>
                        @foreach($ultimosMovimientos as $movimiento)
                            <tr>
                                <td data-label="Fecha" class="text-slate-500">{{ $movimiento->fecha_movimiento->format('d/m/Y') }}</td>
                                <td data-label="Bien" class="font-medium text-slate-800">{{ $movimiento->bien->nombre ?? '—' }}</td>
                                <td data-label="Sede" class="text-slate-600">{{ $movimiento->sedeDestino?->codigo ? 'Sede '.substr($movimiento->sedeDestino->codigo, 1) : ($movimiento->sedeDestino->nombre ?? '—') }}</td>
                                <td data-label="Movimiento">
                                    {{ \App\Models\Movimiento::TIPOS[$movimiento->tipo] ?? $movimiento->tipo }}
                                    @if($movimiento->estado === 'solicitado')
                                        <span class="badge-ambar ml-1">Pendiente</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="tarjeta p-5 sm:p-6">
            <h2 class="text-base font-bold text-slate-800">Por aprobar</h2>
            <p class="text-4xl font-bold text-marino-800 mt-2">{{ $porAprobar }}</p>
            <p class="text-sm text-slate-500">solicitudes esperan su revisión</p>
            @can('aprobaciones.ver')
                <a href="{{ route('aprobaciones.index') }}" class="btn-secundario mt-4 w-full">Abrir bandeja de aprobación</a>
            @endcan
            <p class="text-xs text-slate-400 mt-4">Valor patrimonial activo: <strong class="text-slate-600">S/ {{ number_format($valorTotal, 2) }}</strong></p>
        </section>

        <section class="tarjeta p-5 sm:p-6">
            <h2 class="text-base font-bold text-slate-800">Bienes por categoría</h2>
            @php($maximo = max(1, $porCategoria->max('bienes_count')))
            <ul class="mt-4 space-y-3">
                @forelse($porCategoria as $categoria)
                    <li>
                        <div class="flex justify-between text-sm"><span class="text-slate-600">{{ $categoria->nombre }}</span><span class="font-semibold">{{ $categoria->bienes_count }}</span></div>
                        <div class="h-2 bg-slate-100 rounded-full mt-1"><div class="h-2 bg-marino-500 rounded-full" style="width: {{ $categoria->bienes_count / $maximo * 100 }}%"></div></div>
                    </li>
                @empty
                    <li><x-estado-vacio icono="fa-layer-group" titulo="Sin categorías" /></li>
                @endforelse
            </ul>
        </section>

        <section class="tarjeta p-5 sm:p-6">
            <h2 class="text-base font-bold text-slate-800">Mantenimientos en curso</h2>
            <ul class="mt-3 divide-y divide-slate-100">
                @forelse($mantenimientosEnCurso as $mantenimiento)
                    <li class="py-2.5 flex items-center justify-between gap-2 text-sm">
                        <span class="truncate text-slate-700">{{ $mantenimiento->bien->nombre ?? '—' }}</span>
                        <span class="{{ $mantenimiento->estado === 'en_proceso' ? 'badge-azul' : 'badge-ambar' }}">{{ $mantenimiento->estado === 'en_proceso' ? 'En proceso' : 'Pendiente' }}</span>
                    </li>
                @empty
                    <li><x-estado-vacio icono="fa-screwdriver-wrench" titulo="Ningún mantenimiento en curso" /></li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
