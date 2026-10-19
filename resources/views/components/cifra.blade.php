@props(['titulo', 'valor', 'icono', 'color' => 'azul'])

@php
    $colores = [
        'azul' => 'bg-marino-600',
        'verde' => 'bg-emerald-500',
        'naranja' => 'bg-amber-400',
        'rojo' => 'bg-rose-500',
    ];
@endphp

{{-- Tarjeta con una cifra (mockup: "Total de bienes", "En buen estado", ...). --}}
<div {{ $attributes->class('tarjeta p-4 sm:p-5 flex items-center gap-4') }}>
    <span class="w-12 h-12 shrink-0 rounded-xl {{ $colores[$color] ?? $colores['azul'] }} text-white flex items-center justify-center shadow-sm">
        <i class="fa-solid {{ $icono }} text-lg"></i>
    </span>
    <div class="min-w-0">
        <p class="text-sm text-slate-600 leading-tight">{{ $titulo }}</p>
        <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ $valor }}</p>
    </div>
</div>
