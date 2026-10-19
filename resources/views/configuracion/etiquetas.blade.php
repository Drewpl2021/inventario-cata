@extends('layouts.app')

@section('title', 'Configuración de etiquetas')
@section('page_header', 'Configuración › Etiquetas')

@php($valor = fn (string $campo, $porDefecto) => old($campo, $porDefecto))

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    <form method="POST" action="{{ route('configuracion.etiquetas.guardar') }}" id="form-etiqueta" class="xl:col-span-3 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-6">
        @csrf
        @method('PUT')

        <div>
            <h1 class="text-lg font-bold text-slate-800">Formato de la etiqueta</h1>
            <p class="text-xs text-slate-500 mt-0.5">Debe coincidir con el rollo de etiquetas cargado en la impresora térmica.</p>
        </div>

        <fieldset class="space-y-3">
            <legend class="text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Tamaño</legend>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach([...array_keys(\App\Support\ConfiguracionEtiqueta::PRESETS), 'personalizado'] as $preset)
                    <label class="flex items-center gap-2 px-3 py-2.5 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 cursor-pointer has-[:checked]:border-[#0b3b70] has-[:checked]:bg-blue-50">
                        <input type="radio" name="preset" value="{{ $preset }}" @checked($valor('preset', $config->preset) === $preset) class="accent-[#0b3b70]">
                        {{ $preset === 'personalizado' ? 'Personalizado' : str_replace('x', ' × ', $preset).' mm' }}
                    </label>
                @endforeach
            </div>
            <div id="medidas-personalizadas" class="grid grid-cols-2 gap-3">
                <div>
                    <label for="ancho_mm" class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Ancho (mm)</label>
                    <input type="number" step="0.5" min="20" max="110" id="ancho_mm" name="ancho_mm" value="{{ $valor('ancho_mm', $config->anchoMm) }}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
                </div>
                <div>
                    <label for="alto_mm" class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Alto (mm)</label>
                    <input type="number" step="0.5" min="10" max="100" id="alto_mm" name="alto_mm" value="{{ $valor('alto_mm', $config->altoMm) }}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
                </div>
            </div>
        </fieldset>

        <fieldset class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <legend class="text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2 sm:col-span-3">Impresora y código de barras</legend>
            <div>
                <label for="dpi" class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Resolución</label>
                <select id="dpi" name="dpi" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
                    @foreach(\App\Support\ConfiguracionEtiqueta::DPI_PERMITIDOS as $dpi)
                        <option value="{{ $dpi }}" @selected($valor('dpi', $config->dpi) == $dpi)>{{ $dpi }} dpi</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400 mt-1">La mayoría de mini impresoras: 203 dpi.</p>
            </div>
            <div>
                <label for="ancho_modulo" class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Ancho de módulo (puntos)</label>
                <input type="number" min="1" max="6" id="ancho_modulo" name="ancho_modulo" value="{{ $valor('ancho_modulo', $config->anchoModulo) }}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
                <p class="text-[11px] text-slate-400 mt-1">Barra más fina. Se reduce solo si el código no entra.</p>
            </div>
            <div>
                <label for="alto_barras_mm" class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Altura de barras (mm)</label>
                <input type="number" step="0.5" min="3" max="60" id="alto_barras_mm" name="alto_barras_mm" value="{{ $valor('alto_barras_mm', $config->altoBarrasMm) }}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
            </div>
        </fieldset>

        <fieldset class="flex flex-wrap gap-6">
            <legend class="text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2 w-full">Contenido</legend>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="mostrar_nombre" value="0">
                <input type="checkbox" name="mostrar_nombre" value="1" @checked($valor('mostrar_nombre', $config->mostrarNombre)) class="w-4 h-4 accent-[#0b3b70]">
                Mostrar el nombre del bien
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="mostrar_logo" value="0">
                <input type="checkbox" name="mostrar_logo" value="1" @checked($valor('mostrar_logo', $config->mostrarLogo)) class="w-4 h-4 accent-[#0b3b70]">
                Mostrar el logo del colegio
            </label>
            <p class="w-full text-[11px] text-slate-400 -mt-3">El código completo siempre se imprime bajo las barras. El nombre se corta con "…" si no entra.</p>
        </fieldset>

        @can('configuracion.editar')
            <div class="flex justify-end border-t border-slate-100 pt-5">
                <button type="submit" class="px-6 py-2.5 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-xl text-sm font-bold flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Guardar formato
                </button>
            </div>
        @endcan
    </form>

    <div class="xl:col-span-2 space-y-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
            <h2 class="text-sm font-bold text-slate-800">Vista previa</h2>
            <div class="bg-slate-100 rounded-xl p-6 flex items-center justify-center">
                <img id="vista-previa" src="{{ route('configuracion.etiquetas.vista-previa') }}" alt="Vista previa de la etiqueta" class="max-w-full bg-white shadow border border-slate-300" style="image-rendering: pixelated; width: 320px;">
            </div>
            <p class="text-[11px] text-slate-500">
                Guardado: <strong>{{ $config->descripcion() }}</strong> ·
                {{ $config->anchoPx() }} × {{ $config->altoPx() }} puntos ·
                módulo efectivo {{ $generador->anchoModuloEfectivo() }} pt ({{ number_format($config->pxAMm($generador->anchoModuloEfectivo()), 2) }} mm)
            </p>
            @if($generador->anchoModuloEfectivo() < $config->anchoModulo)
                <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2">
                    El módulo se reduce de {{ $config->anchoModulo }} a {{ $generador->anchoModuloEfectivo() }} punto(s) para que el código entre. Compruebe con la página de prueba que su lector lo lee bien.
                </p>
            @endif
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
            <h2 class="text-sm font-bold text-slate-800">Página de prueba</h2>
            <p class="text-xs text-slate-500">Imprime un borde, marcas cada 5 mm y un código de ejemplo para comprobar el tamaño, la alineación y la lectura con el escáner. Usa el formato guardado.</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('etiquetas.prueba', 'html') }}" target="_blank" class="px-3 py-2 bg-[#0b3b70] text-white rounded-lg text-xs font-bold"><i class="fa-solid fa-print"></i> Imprimir</a>
                <a href="{{ route('etiquetas.prueba', 'pdf') }}" target="_blank" class="px-3 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                <a href="{{ route('etiquetas.prueba', 'png') }}" class="px-3 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold"><i class="fa-solid fa-image"></i> PNG</a>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        const form = document.getElementById('form-etiqueta');
        const img = document.getElementById('vista-previa');
        const medidas = document.getElementById('medidas-personalizadas');
        const base = @json(route('configuracion.etiquetas.vista-previa'));
        const presets = @json(\App\Support\ConfiguracionEtiqueta::PRESETS);
        let espera;

        const actualizar = () => {
            const datos = new FormData(form);
            const preset = datos.get('preset');
            medidas.querySelectorAll('input').forEach((i) => (i.readOnly = preset !== 'personalizado'));
            if (presets[preset]) {
                form.ancho_mm.value = presets[preset][0];
                form.alto_mm.value = presets[preset][1];
            }
            datos.delete('_token');
            datos.delete('_method');
            clearTimeout(espera);
            espera = setTimeout(() => {
                img.src = base + '?' + new URLSearchParams(datos).toString();
                img.style.width = Math.round(parseFloat(form.ancho_mm.value || 50) * 6.4) + 'px';
            }, 250);
        };

        form.addEventListener('input', actualizar);
        form.addEventListener('change', actualizar);
        actualizar();
    })();
</script>
@endsection
