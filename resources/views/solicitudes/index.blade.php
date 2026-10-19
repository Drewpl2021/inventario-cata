@extends('layouts.app')

@section('title', 'Mis solicitudes')

@section('content')
<x-encabezado titulo="Mis solicitudes" subtitulo="Aquí puedes ver el estado de todas tus solicitudes." :volver="route('dashboard')" volver-texto="Inicio" />

<section class="tarjeta overflow-hidden">
    <nav class="p-3 sm:p-4 border-b border-slate-100 flex gap-2 overflow-x-auto" aria-label="Tipo de solicitud">
        @foreach($pestanas as $clave => $titulo)
            <a href="{{ route('solicitudes.index', $clave === 'todas' ? [] : ['pestana' => $clave]) }}"
               @class(['btn whitespace-nowrap', 'bg-marino-800 text-white hover:bg-marino-900' => $pestana === $clave, 'text-slate-600 hover:bg-slate-100' => $pestana !== $clave])
               @if($pestana === $clave) aria-current="page" @endif>{{ $titulo }}</a>
        @endforeach
    </nav>

    @if($solicitudes->isEmpty())
        <x-estado-vacio icono="fa-inbox" titulo="Aún no tiene solicitudes" mensaje="Use el menú «Solicitudes» para pedir una baja, un movimiento o un requerimiento." />
    @else
        <table class="tabla tabla-tarjetas">
            <thead>
                <tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Estado</th><th><span class="sr-only">Ver</span></th></tr>
            </thead>
            <tbody>
                @foreach($solicitudes as $solicitud)
                    <tr>
                        <td data-label="Fecha" class="text-slate-500 whitespace-nowrap">{{ $solicitud['fecha']?->format('d/m/Y') }}</td>
                        <td data-label="Tipo" class="whitespace-nowrap"><i class="fa-solid {{ $solicitud['icono'] }} text-slate-400 mr-1"></i> {{ $solicitud['tipo'] }}</td>
                        <td data-label="Descripción">
                            <p class="font-medium text-slate-800">{{ $solicitud['descripcion'] }}</p>
                            <p class="text-xs text-slate-500">{{ $solicitud['detalle'] }}</p>
                            @if($solicitud['estado']['clave'] === 'rechazada' && $solicitud['motivo_rechazo'])
                                <p class="text-xs text-rose-700 mt-1"><i class="fa-solid fa-circle-info"></i> Motivo del rechazo: {{ $solicitud['motivo_rechazo'] }}</p>
                            @endif
                        </td>
                        <td data-label="Estado"><span class="{{ $solicitud['estado']['badge'] }}">{{ $solicitud['estado']['etiqueta'] }}</span></td>
                        <td class="text-right" data-label="">
                            @if($solicitud['url'])
                                <a href="{{ $solicitud['url'] }}" class="btn-icono" aria-label="Ver detalle"><i class="fa-solid fa-chevron-right"></i></a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($solicitudes->hasPages())
            <div class="p-4 border-t border-slate-100">{{ $solicitudes->links() }}</div>
        @endif
    @endif
</section>
@endsection
