@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
<div class="space-y-6">
    <x-bienvenida :titulo="'¡Hola, '.$usuario->nombre.'!'" con-avatar>
        Aquí puedes ver los bienes que tienes a tu cargo y realizar tus solicitudes.
    </x-bienvenida>

    @unless($tieneResponsable)
        <div class="tarjeta p-5 flex items-start gap-3 text-amber-800 bg-amber-50 border-amber-200">
            <i class="fa-solid fa-circle-info mt-0.5"></i>
            <p class="text-sm">Su cuenta aún no está vinculada a un responsable de bienes. Pida al Administrador que la vincule para ver sus bienes y hacer solicitudes.</p>
        </div>
    @endunless

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <x-cifra titulo="Total de bienes a tu cargo" :valor="$totales['total']" icono="fa-clipboard-list" color="azul" />
        <x-cifra titulo="En buen estado" :valor="$totales['buen_estado']" icono="fa-circle-check" color="verde" />
        <x-cifra titulo="En mantenimiento" :valor="$totales['mantenimiento']" icono="fa-screwdriver-wrench" color="naranja" />
        <x-cifra titulo="Dados de baja" :valor="$totales['dados_de_baja']" icono="fa-ban" color="rojo" />
    </div>

    <section class="tarjeta overflow-hidden">
        <h2 class="px-5 sm:px-6 pt-5 pb-3 text-base font-bold text-slate-800">Mis bienes</h2>
        @if($bienes->isEmpty())
            <x-estado-vacio icono="fa-box-open" titulo="No tiene bienes a su cargo" mensaje="Cuando le asignen bienes, aparecerán aquí." />
        @else
            <table class="tabla tabla-tarjetas">
                <thead>
                    <tr><th>Código</th><th>Nombre</th><th>Categoría</th><th>Estado</th><th>Ubicación</th><th><span class="sr-only">Ver</span></th></tr>
                </thead>
                <tbody>
                    @foreach($bienes as $bien)
                        <tr>
                            <td data-label="Código" class="font-mono text-xs font-semibold text-slate-700">{{ $bien->codigo_barras }}</td>
                            <td data-label="Nombre" class="font-medium text-slate-800">{{ $bien->nombre }}</td>
                            <td data-label="Categoría" class="text-slate-600">{{ $bien->categoria->nombre ?? '—' }}</td>
                            <td data-label="Estado"><x-estado-bien :estado="$bien->estado" /></td>
                            <td data-label="Ubicación" class="text-slate-600">{{ $bien->area->nombre ?? '—' }}</td>
                            <td class="text-right" data-label="">
                                <a href="{{ route('bienes.show', $bien) }}" class="btn-icono" aria-label="Ver {{ $bien->nombre }}"><i class="fa-solid fa-chevron-right"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 sm:px-6 py-3 border-t border-slate-100 text-right">
                <a href="{{ route('mis-bienes.index') }}" class="inline-flex items-center gap-2 min-h-11 text-sm font-semibold text-marino-600 hover:text-marino-800">
                    Ver todos mis bienes <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        @endif
    </section>
</div>
@endsection
