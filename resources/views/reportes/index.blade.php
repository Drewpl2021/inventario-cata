@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
<x-encabezado titulo="Reportes" subtitulo="Genera informes del inventario según tus necesidades." />

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-6">
    @foreach($reportes as $reporte)
        <article class="tarjeta p-6 flex flex-col items-center text-center gap-3">
            <span class="w-14 h-14 rounded-2xl bg-marino-50 text-marino-700 flex items-center justify-center">
                <i class="fa-solid {{ $reporte->icono() }} text-2xl" aria-hidden="true"></i>
            </span>
            <h2 class="font-bold text-slate-800">{{ $reporte->titulo() }}</h2>
            <p class="text-sm text-slate-500 flex-1">{{ $reporte->descripcion() }}</p>
            <a href="{{ route('reportes.show', $reporte->clave()) }}" class="btn-primario w-full sm:w-auto sm:px-10">Generar</a>
        </article>
    @endforeach
</div>
@endsection
