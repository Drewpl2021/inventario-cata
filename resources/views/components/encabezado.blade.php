@props(['titulo', 'subtitulo' => null, 'volver' => null, 'volverTexto' => 'Volver'])

{{-- Encabezado de página: "← Volver", título, subtítulo y acciones a la derecha. --}}
<div {{ $attributes->class('mb-6') }}>
    @if($volver)
        <a href="{{ $volver }}" class="inline-flex items-center gap-1.5 min-h-11 text-sm font-semibold text-marino-700 hover:text-marino-900">
            <i class="fa-solid fa-arrow-left text-xs"></i> {{ $volverTexto }}
        </a>
    @endif
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">{{ $titulo }}</h1>
            @if($subtitulo)
                <p class="text-sm text-slate-500 mt-0.5">{{ $subtitulo }}</p>
            @endif
        </div>
        @isset($acciones)
            <div class="flex flex-wrap gap-2">{{ $acciones }}</div>
        @endisset
    </div>
</div>
