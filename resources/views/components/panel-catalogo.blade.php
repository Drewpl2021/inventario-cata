@props(['urlCrear', 'tituloCrear', 'tituloEditar', 'enctype' => null])

{{--
    Formulario de alta y edición de un catálogo. Se abre con [data-nuevo] o
    [data-editar] (ver iniciarCatalogos en resources/js/app.js). Si hubo errores
    de validación vuelve abierto y en el mismo modo.
--}}
<section data-panel-formulario data-titulo-crear="{{ $tituloCrear }}" data-titulo-editar="{{ $tituloEditar }}"
         @class(['tarjeta p-5 sm:p-6 mb-6', 'hidden' => ! $errors->any()])>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-bold text-slate-800" data-titulo-formulario>{{ old('_url_edicion') ? $tituloEditar : $tituloCrear }}</h2>
        <button type="button" class="btn-icono" data-cancelar-formulario aria-label="Cerrar formulario"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="{{ old('_url_edicion', $urlCrear) }}" data-url-crear="{{ $urlCrear }}" @if($enctype) enctype="{{ $enctype }}" @endif>
        @csrf
        <input type="hidden" name="_method" value="PUT" @disabled(! old('_url_edicion'))>
        <input type="hidden" name="_url_edicion" value="{{ old('_url_edicion') }}">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {{ $slot }}
        </div>
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 mt-5">
            <button type="button" class="btn-secundario" data-cancelar-formulario>Cancelar</button>
            <button type="submit" class="btn-primario" data-boton-guardar>{{ old('_url_edicion') ? 'Guardar cambios' : 'Guardar' }}</button>
        </div>
    </form>
</section>
