@props(['titulo', 'conAvatar' => false])

{{-- Tarjeta de bienvenida del Inicio, con el lema institucional. --}}
<section {{ $attributes->class('tarjeta relative overflow-hidden bg-gradient-to-r from-marino-50 via-white to-marino-50 p-6 sm:p-8 flex items-center gap-5') }}>
    @if($conAvatar)
        <span class="hidden sm:flex w-24 h-24 shrink-0 rounded-full bg-marino-100 text-marino-800 items-center justify-center">
            <i class="fa-solid fa-user text-5xl"></i>
        </span>
    @endif
    <div class="flex-1 min-w-0 relative">
        <h1 class="text-2xl sm:text-3xl font-bold text-marino-900">{{ $titulo }}</h1>
        <p class="mt-2 text-slate-600 max-w-2xl">{{ $slot }}</p>
    </div>
    <div class="hidden md:flex flex-col items-center shrink-0 pr-2">
        <x-emblema class="w-16 h-20 text-marino-300" />
        <p class="font-lema text-2xl text-marino-500 -rotate-6 mt-1 leading-tight text-center">Más que educación,<br>una misión</p>
    </div>
</section>
