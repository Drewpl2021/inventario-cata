@extends('layouts.app')

@section('title', 'Nuevo Bien Patrimonial')
@section('page_header', 'Registrar Nuevo Bien Patrimonial')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-slate-800">Formulario de Registro de Bien</h1>
        <a href="{{ route('bienes.index') }}" class="px-3.5 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg text-sm font-medium transition">
            &larr; Volver al listado
        </a>
    </div>

    <form method="POST" action="{{ route('bienes.store') }}" enctype="multipart/form-data" class="bg-white p-8 rounded-2xl border border-slate-200/80 shadow-sm space-y-6">
        @csrf
        @if(request('inventario_detalle_id') || old('inventario_detalle_id'))
            <input type="hidden" name="inventario_detalle_id" value="{{ old('inventario_detalle_id', request('inventario_detalle_id')) }}">
            <div class="p-3 bg-sky-50 border border-sky-200 rounded-xl text-sky-800 text-xs"><i class="fa-solid fa-barcode"></i> Registro de un <strong>sobrante</strong> encontrado en la toma de inventario. El bien recibirá un código de barras nuevo: imprima y pegue su etiqueta.</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="codigo_patrimonial" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Código Patrimonial (oficial del colegio)</label>
                <input type="text" id="codigo_patrimonial" name="codigo_patrimonial" value="{{ old('codigo_patrimonial', request('codigo_patrimonial')) }}" placeholder="Opcional. EJ: 740805000001" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                @error('codigo_patrimonial') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror

                <div class="mt-3 bg-slate-50 p-3 rounded-xl border border-slate-200 flex items-center gap-3">
                    <i class="fa-solid fa-barcode text-2xl text-slate-500"></i>
                    <p class="text-[11px] text-slate-500 leading-snug">
                        El <strong>código de barras</strong> se genera automáticamente al guardar, con el formato
                        <span class="font-mono font-bold text-slate-700">CAT-S01-000123</span> (sede + correlativo), y no se puede modificar.
                    </p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Código Interno</label>
                <input type="text" id="codigo_interno" name="codigo_interno" value="{{ old('codigo_interno', $nextCodigoInterno ?? '00001') }}" placeholder="EJ: 00001" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold text-indigo-700">
                <p class="text-[11px] text-indigo-600 font-medium mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-wand-magic-sparkles text-indigo-500"></i> Generado automáticamente (Ejemplo: 00001)
                </p>
                @error('codigo_interno') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Nombre / Descripción del Bien *</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}" required placeholder="EJ: Laptop Dell Latitude 5420 i7 16GB" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('nombre') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Marca</label>
                <input type="text" name="marca" value="{{ old('marca') }}" placeholder="EJ: Dell, HP, Lenovo" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Modelo</label>
                <input type="text" name="modelo" value="{{ old('modelo') }}" placeholder="EJ: Latitude 5420" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Número de Serie</label>
                <input type="text" name="numero_serie" value="{{ old('numero_serie') }}" placeholder="EJ: 9X8Y7Z1" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Categoría *</label>
                <select name="categoria_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Seleccionar Categoría --</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ old('categoria_id') == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Sede *</label>
                <select name="sede_id" id="sede_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Seleccionar Sede --</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" {{ old('sede_id', request('sede_id')) == $sede->id ? 'selected' : '' }}>{{ $sede->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Área</label>
                <select name="area_id" data-sede-select="sede_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Sin Asignar --</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}" data-sede="{{ $area->sede_id }}" {{ old('area_id', request('area_id')) == $area->id ? 'selected' : '' }}>{{ $area->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Responsable / Custodio</label>
                <select name="responsable_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Sin Responsable --</option>
                    @foreach($responsables as $resp)
                        <option value="{{ $resp->id }}" {{ old('responsable_id') == $resp->id ? 'selected' : '' }}>{{ $resp->nombres }} {{ $resp->apellidos }}</option>
                    @endforeach
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
                        <p class="text-xs text-slate-500">Seleccione el tipo de ingreso y suba la factura o comprobante correspondiente.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Tipo de Ingreso *</label>
                        <select id="tipo_ingreso" name="tipo_ingreso" required class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-semibold text-slate-700">
                            <option value="con_comprobante" {{ old('tipo_ingreso', 'con_comprobante') == 'con_comprobante' ? 'selected' : '' }}>Con Comprobante / Factura</option>
                            <option value="sin_comprobante" {{ old('tipo_ingreso') == 'sin_comprobante' ? 'selected' : '' }}>Sin Comprobante</option>
                            <option value="donacion" {{ old('tipo_ingreso') == 'donacion' ? 'selected' : '' }}>Donación</option>
                            <option value="transferencia" {{ old('tipo_ingreso') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                            <option value="otro" {{ old('tipo_ingreso') == 'otro' ? 'selected' : '' }}>Otro</option>
                        </select>
                        @error('tipo_ingreso') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label id="lbl_numero_comprobante" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">N° Comprobante / Factura</label>
                        <input type="text" id="numero_comprobante" name="numero_comprobante" value="{{ old('numero_comprobante') }}" placeholder="EJ: F001-00234 o Acta 01" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('numero_comprobante') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Fecha de Ingreso / Compra</label>
                        <input type="date" name="fecha_adquisicion" value="{{ old('fecha_adquisicion', date('Y-m-d')) }}" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="md:col-span-2">
                        <label id="lbl_comprobante_archivo" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Subir Comprobante / Factura (PDF o Imagen)</label>
                        <input type="file" id="comprobante_archivo" name="comprobante_archivo" accept="application/pdf,image/jpeg,image/png,image/webp" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-600">
                        <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
                            <i class="fa-solid fa-paperclip text-slate-400"></i> Formatos: PDF (hasta 10 MB) o imagen JPG, PNG, WEBP (hasta 5 MB)
                        </p>
                        @error('comprobante_archivo') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Valor de Adquisición (S/)</label>
                        <input type="number" step="0.01" name="valor_adquisicion" value="{{ old('valor_adquisicion') }}" placeholder="0.00" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Estado Físico / Operativo *</label>
                <select name="estado" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="nuevo">Nuevo</option>
                    <option value="bueno">Bueno</option>
                    <option value="regular">Regular</option>
                    <option value="malo">Malo</option>
                    <option value="mantenimiento">Mantenimiento</option>
                    <option value="inoperativo">Inoperativo</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Fotografía del Bien (Opcional)</label>
                <input type="file" name="fotografia" accept="image/jpeg,image/png,image/webp" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-600">
                <p class="text-[11px] text-slate-400 mt-1">Formatos permitidos: JPG, PNG, WEBP (Máx. 5MB)</p>
                @error('fotografia') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Observaciones</label>
                <textarea name="observaciones" rows="3" placeholder="Detalles técnicos o notas adicionales..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('observaciones') }}</textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-100 pt-6">
            <a href="{{ route('bienes.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-xl text-sm font-medium transition">Cancelar</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold transition shadow-lg shadow-indigo-500/20">
                Guardar Bien Patrimonial
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
