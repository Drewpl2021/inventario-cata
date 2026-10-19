@props(['icono' => 'fa-box-open', 'titulo' => 'No hay registros', 'mensaje' => null])

<div {{ $attributes->class('py-12 px-6 text-center') }}>
    <span class="mx-auto mb-3 w-14 h-14 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center">
        <i class="fa-solid {{ $icono }} text-2xl"></i>
    </span>
    <p class="font-semibold text-slate-700">{{ $titulo }}</p>
    @if($mensaje)
        <p class="text-sm text-slate-500 mt-1">{{ $mensaje }}</p>
    @endif
    {{ $slot }}
</div>
