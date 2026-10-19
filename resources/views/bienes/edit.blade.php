@extends('layouts.app')

@section('title', 'Editar Bien Patrimonial')
@section('page_header', 'Editar Bien Patrimonial')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-slate-800">Editar Bien: {{ $bien->codigo_barras }}</h1>
        <a href="{{ route('bienes.index') }}" class="px-3.5 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg text-sm font-medium transition">
            &larr; Volver al listado
        </a>
    </div>

    <form method="POST" action="{{ route('bienes.update', $bien) }}" enctype="multipart/form-data" class="bg-white p-8 rounded-2xl border border-slate-200/80 shadow-sm space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2 p-3 bg-slate-50 rounded-xl border border-slate-200 flex flex-wrap items-center gap-3">
                <i class="fa-solid fa-barcode text-xl text-slate-500"></i>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-600">Código de barras</span>
                <span class="font-mono font-bold text-slate-800">{{ $bien->codigo_barras }}</span>
                <span class="text-[11px] text-slate-400">Asignado por el sistema; no cambia al editar ni al trasladar el bien.</span>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Código Patrimonial (oficial del colegio)</label>
                <input type="text" name="codigo_patrimonial" value="{{ old('codigo_patrimonial', $bien->codigo_patrimonial) }}" placeholder="Opcional" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Código Interno</label>
                <input type="text" name="codigo_interno" value="{{ old('codigo_interno', $bien->codigo_interno) }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Nombre / Descripción del Bien *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $bien->nombre) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Marca</label>
                <input type="text" name="marca" value="{{ old('marca', $bien->marca) }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Modelo</label>
                <input type="text" name="modelo" value="{{ old('modelo', $bien->modelo) }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Número de Serie</label>
                <input type="text" name="numero_serie" value="{{ old('numero_serie', $bien->numero_serie) }}" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Categoría *</label>
                <select name="categoria_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ old('categoria_id', $bien->categoria_id) == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Sede *</label>
                <select name="sede_id" id="sede_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" {{ old('sede_id', $bien->sede_id) == $sede->id ? 'selected' : '' }}>{{ $sede->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Área</label>
                <select name="area_id" data-sede-select="sede_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Sin Asignar --</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}" data-sede="{{ $area->sede_id }}" {{ old('area_id', $bien->area_id) == $area->id ? 'selected' : '' }}>{{ $area->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Responsable / Custodio</label>
                <select name="responsable_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Sin Responsable --</option>
                    @foreach($responsables as $resp)
                        <option value="{{ $resp->id }}" {{ old('responsable_id', $bien->responsable_id) == $resp->id ? 'selected' : '' }}>{{ $resp->nombres }} {{ $resp->apellidos }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Estado Físico / Operativo *</label>
                <select name="estado" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="nuevo" {{ $bien->estado === 'nuevo' ? 'selected' : '' }}>Nuevo</option>
                    <option value="bueno" {{ $bien->estado === 'bueno' ? 'selected' : '' }}>Bueno</option>
                    <option value="regular" {{ $bien->estado === 'regular' ? 'selected' : '' }}>Regular</option>
                    <option value="malo" {{ $bien->estado === 'malo' ? 'selected' : '' }}>Malo</option>
                    <option value="mantenimiento" {{ $bien->estado === 'mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                    <option value="inoperativo" {{ $bien->estado === 'inoperativo' ? 'selected' : '' }}>Inoperativo</option>
                    <option value="dado_de_baja" {{ $bien->estado === 'dado_de_baja' ? 'selected' : '' }}>Dado de Baja</option>
                </select>
            </div>

            <!-- Sección de Tipo de Ingreso y Comprobante / Factura -->
            <div class="md:col-span-2 bg-slate-50/90 p-5 rounded-2xl border border-slate-200 space-y-4">
                <div class="flex items-center gap-2.5 border-b border-slate-200/80 pb-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm shadow-sm">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Modalidad de Ingreso y Comprobante</h3>
                        <p class="text-xs text-slate-500">Gestione el tipo de ingreso y actualice el comprobante o factura del bien.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Tipo de Ingreso *</label>
                        <select id="tipo_ingreso" name="tipo_ingreso" required class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-semibold text-slate-700">
                            <option value="con_comprobante" {{ old('tipo_ingreso', $bien->tipo_ingreso ?? 'con_comprobante') == 'con_comprobante' ? 'selected' : '' }}>Con Comprobante / Factura</option>
                            <option value="sin_comprobante" {{ old('tipo_ingreso', $bien->tipo_ingreso) == 'sin_comprobante' ? 'selected' : '' }}>Sin Comprobante</option>
                            <option value="donacion" {{ old('tipo_ingreso', $bien->tipo_ingreso) == 'donacion' ? 'selected' : '' }}>Donación</option>
                            <option value="transferencia" {{ old('tipo_ingreso', $bien->tipo_ingreso) == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                            <option value="otro" {{ old('tipo_ingreso', $bien->tipo_ingreso) == 'otro' ? 'selected' : '' }}>Otro</option>
                        </select>
                        @error('tipo_ingreso') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label id="lbl_numero_comprobante" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">N° Comprobante / Factura</label>
                        <input type="text" id="numero_comprobante" name="numero_comprobante" value="{{ old('numero_comprobante', $bien->numero_comprobante) }}" placeholder="EJ: F001-00234 o Acta 01" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('numero_comprobante') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Fecha de Ingreso / Compra</label>
                        <input type="date" name="fecha_adquisicion" value="{{ old('fecha_adquisicion', optional($bien->fecha_adquisicion)->format('Y-m-d')) }}" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="md:col-span-2">
                        <label id="lbl_comprobante_archivo" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Comprobante / Factura (PDF o Imagen)</label>
                        @if($bien->comprobante_archivo)
                            <div class="mb-3 flex items-center justify-between bg-indigo-50/70 border border-indigo-200/80 p-3 rounded-xl">
                                <div class="flex items-center gap-2.5 text-xs text-indigo-900 font-medium">
                                    <i class="fa-solid fa-file-invoice text-indigo-600 text-base"></i>
                                    <span>Comprobante registrado: {{ basename($bien->comprobante_archivo) }}</span>
                                </div>
                                <a href="{{ $bien->url_comprobante }}" target="_blank" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg flex items-center gap-1 transition shadow-sm">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver / Descargar
                                </a>
                            </div>
                        @endif
                        <input type="file" id="comprobante_archivo" name="comprobante_archivo" accept="application/pdf,image/jpeg,image/png,image/webp" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-600">
                        <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
                            <i class="fa-solid fa-paperclip text-slate-400"></i> Seleccione un archivo si desea reemplazar el comprobante actual. Formatos: PDF (hasta 10 MB) o imagen JPG, PNG, WEBP (hasta 5 MB)
                        </p>
                        @error('comprobante_archivo') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Valor de Adquisición (S/)</label>
                        <input type="number" step="0.01" name="valor_adquisicion" value="{{ old('valor_adquisicion', $bien->valor_adquisicion) }}" placeholder="0.00" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Fotografía del Bien (Opcional)</label>
                @if($bien->fotografia)
                    <div class="mb-3 flex items-center gap-4 bg-slate-50 p-3 rounded-xl border border-slate-200">
                        <img src="{{ $bien->url_fotografia }}" alt="Foto del bien" class="w-20 h-20 object-cover rounded-lg border border-slate-300 shadow-sm">
                        <span class="text-xs text-slate-500">Fotografía actual registrada. Seleccione un nuevo archivo si desea reemplazarla.</span>
                    </div>
                @endif
                <input type="file" name="fotografia" accept="image/jpeg,image/png,image/webp" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-600">
                <p class="text-[11px] text-slate-400 mt-1">Formatos permitidos: JPG, PNG, WEBP (Máx. 5MB)</p>
                @error('fotografia') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Observaciones</label>
                <textarea name="observaciones" rows="3" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('observaciones', $bien->observaciones) }}</textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-100 pt-6">
            <a href="{{ route('bienes.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-xl text-sm font-medium transition">Cancelar</a>
            <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-semibold transition shadow-lg shadow-amber-500/20">
                Actualizar Bien Patrimonial
            </button>
        </div>
    </form>
</div>

<script nonce="{{ Vite::cspNonce() }}">
    function toggleComprobanteFields() {
        const select = document.getElementById('tipo_ingreso');
        if (!select) return;
        const tipo = select.value;
        const lblNum = document.getElementById('lbl_numero_comprobante');
        const inputNum = document.getElementById('numero_comprobante');
        const lblFile = document.getElementById('lbl_comprobante_archivo');

        if (tipo === 'donacion') {
            if (lblNum) lblNum.innerText = 'N° de Acta / Resolución Donación';
            if (inputNum) inputNum.placeholder = 'EJ: Acta de Donación N° 012-2026';
            if (lblFile) lblFile.innerText = 'Subir Acta de Donación / Resolución (PDF o Imagen)';
        } else if (tipo === 'sin_comprobante') {
            if (lblNum) lblNum.innerText = 'Referencia / Nota Interna (Opcional)';
            if (inputNum) inputNum.placeholder = 'EJ: Saldo Inicial / Inventario Físico';
            if (lblFile) lblFile.innerText = 'Subir Documento o Acta Interna (Opcional)';
        } else if (tipo === 'transferencia') {
            if (lblNum) lblNum.innerText = 'N° de Oficio / Acta Transferencia';
            if (inputNum) inputNum.placeholder = 'EJ: Oficio N° 045-2026-MINEDU';
            if (lblFile) lblFile.innerText = 'Subir Oficio / Acta de Transferencia (PDF o Imagen)';
        } else {
            if (lblNum) lblNum.innerText = 'N° Comprobante / Factura';
            if (inputNum) inputNum.placeholder = 'EJ: F001-00234 o B001-5678';
            if (lblFile) lblFile.innerText = 'Subir Factura / Comprobante (PDF o Imagen)';
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('tipo_ingreso')?.addEventListener('change', toggleComprobanteFields);
        toggleComprobanteFields();
    });
</script>
@include('partials.filtro-areas-por-sede')
@endsection
