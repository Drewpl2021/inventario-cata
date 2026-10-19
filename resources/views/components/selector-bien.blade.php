@props(['para', 'seleccionado' => null, 'name' => 'bien_id'])

{{--
    Buscador de bien por código o nombre, con la tarjeta (foto y datos) del bien elegido.
    Solo ofrece bienes sobre los que el usuario puede solicitar ($para: bajas | movimientos).
    Al elegir un bien emite el evento "bien-seleccionado" con sus datos.
--}}
<div data-selector-bien data-url="{{ route('buscar.bienes-solicitables', ['para' => $para]) }}" {{ $attributes->class('space-y-3') }}>
    <input type="hidden" name="{{ $name }}" value="{{ $seleccionado['id'] ?? '' }}" data-valor>

    <div class="relative" data-buscador>
        <label for="selector-bien-{{ $para }}" class="sr-only">Buscar bien por código o nombre</label>
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
        <input type="search" id="selector-bien-{{ $para }}" autocomplete="off" placeholder="Buscar bien por código o nombre..." class="campo pl-10" data-texto>
        <div class="hidden absolute z-10 left-0 right-0 mt-2 tarjeta py-1 shadow-lg max-h-72 overflow-y-auto" data-resultados></div>
    </div>

    <div @class(['tarjeta p-4 flex gap-4 items-center', 'hidden' => ! $seleccionado]) data-tarjeta>
        <div class="w-24 h-20 shrink-0 rounded-xl bg-slate-100 overflow-hidden flex items-center justify-center" data-foto>
            @if($seleccionado['foto'] ?? null)
                <img src="{{ $seleccionado['foto'] }}" alt="" class="w-full h-full object-cover">
            @else
                <i class="fa-solid fa-box-archive text-2xl text-slate-300"></i>
            @endif
        </div>
        <div class="min-w-0 flex-1 text-sm space-y-0.5">
            <p class="font-bold text-slate-800" data-campo="nombre">{{ $seleccionado['nombre'] ?? '' }}</p>
            <p class="text-slate-500">Código: <span class="font-mono font-semibold text-slate-700" data-campo="codigo">{{ $seleccionado['codigo'] ?? '' }}</span></p>
            <p class="text-slate-500">Categoría: <span class="text-slate-700" data-campo="categoria">{{ $seleccionado['categoria'] ?? '' }}</span></p>
            <p class="text-slate-500">Estado: <span class="text-slate-700" data-campo="estado">{{ $seleccionado['estado'] ?? '' }}</span></p>
        </div>
        <button type="button" class="btn-icono" data-quitar aria-label="Elegir otro bien"><i class="fa-solid fa-xmark"></i></button>
    </div>
</div>
