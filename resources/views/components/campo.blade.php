@props(['nombre', 'etiqueta', 'requerido' => false, 'ancho' => null])

{{-- Campo de formulario con etiqueta y mensaje de error. El control va en el slot. --}}
<div @class([$ancho])>
    <label for="campo-{{ $nombre }}" class="etiqueta-campo">{{ $etiqueta }} @if($requerido)<span class="text-rose-500">*</span>@endif</label>
    {{ $slot }}
    @error($nombre) <p class="text-sm text-rose-600 mt-1">{{ $message }}</p> @enderror
</div>
