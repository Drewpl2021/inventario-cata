@extends('layouts.app')

@section('title', 'Notificaciones')

@section('content')
<x-encabezado titulo="Notificaciones" />

<section class="tarjeta overflow-hidden divide-y divide-slate-100">
    @forelse($notificaciones as $notificacion)
        <a href="{{ $notificacion->data['url'] ?? '#' }}" class="flex gap-3 px-5 py-4 hover:bg-slate-50 {{ $notificacion->read_at ? '' : 'bg-marino-50/60' }}">
            <span class="w-10 h-10 shrink-0 rounded-full bg-marino-100 text-marino-700 flex items-center justify-center"><i class="fa-solid {{ $notificacion->data['icono'] ?? 'fa-bell' }}"></i></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-slate-800">{{ $notificacion->data['titulo'] ?? 'Notificación' }}</span>
                <span class="block text-sm text-slate-500">{{ $notificacion->data['mensaje'] ?? '' }}</span>
                <span class="block text-xs text-slate-400 mt-0.5">{{ $notificacion->created_at->translatedFormat('j \d\e F, H:i') }}</span>
            </span>
        </a>
    @empty
        <x-estado-vacio icono="fa-bell-slash" titulo="No tiene notificaciones" />
    @endforelse
</section>

@if($notificaciones->hasPages())
    <div class="mt-4">{{ $notificaciones->links() }}</div>
@endif
@endsection
