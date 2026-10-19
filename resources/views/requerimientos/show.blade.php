@extends('layouts.app')

@section('title', 'Detalle de Requerimiento ' . $requerimiento->codigo)
@section('page_header', 'Detalle de Solicitud de Requerimiento')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-1 rounded-md">
                    {{ $requerimiento->codigo }}
                </span>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider border {{ $requerimiento->estado_badge['class'] }}">
                    <i class="fa-solid {{ $requerimiento->estado_badge['icon'] }} text-[10px]"></i>
                    {{ $requerimiento->estado_badge['label'] }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider {{ $requerimiento->prioridad_badge['class'] }}">
                    Prioridad: {{ $requerimiento->prioridad_badge['label'] }}
                </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-800">
                {{ $requerimiento->tipo_requerimiento }}
            </h1>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('requerimientos.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Volver al listado
            </a>
            <button type="button" data-imprimir class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-print"></i> Imprimir Solicitud
            </button>
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-800 text-xs font-semibold flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Columna Izquierda: Información Detallada y Archivo Adjunto -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Tarjeta Principal del Requerimiento -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/90 shadow-sm space-y-6">
                
                <!-- Descripción -->
                <div class="space-y-1.5">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">
                        Descripción del Bien / Material Solicitado
                    </span>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 text-sm font-medium text-slate-800 leading-relaxed whitespace-pre-line">
                        {{ $requerimiento->descripcion }}
                    </div>
                </div>

                <!-- Justificación -->
                <div class="space-y-1.5">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">
                        Justificación y Necesidad Institucional
                    </span>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 text-sm font-medium text-slate-700 leading-relaxed whitespace-pre-line">
                        {{ $requerimiento->justificacion }}
                    </div>
                </div>

                <!-- Observaciones -->
                @if($requerimiento->observaciones)
                    <div class="space-y-1.5">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">
                            Observaciones Adicionales
                        </span>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 text-xs font-medium text-slate-600 leading-relaxed whitespace-pre-line">
                            {{ $requerimiento->observaciones }}
                        </div>
                    </div>
                @endif

                <!-- Datos de Cantidad, Presupuesto y Destino -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100">
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <span class="text-[11px] font-semibold uppercase text-slate-400 block">Cantidad Solicitada</span>
                        <span class="text-xl font-black text-slate-800">{{ $requerimiento->cantidad }} unidades</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl">
                        <span class="text-[11px] font-semibold uppercase text-slate-400 block">Costo Estimado</span>
                        <span class="text-xl font-black text-emerald-600">S/ {{ number_format($requerimiento->costo_estimado, 2) }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl col-span-2 sm:col-span-1">
                        <span class="text-[11px] font-semibold uppercase text-slate-400 block">Sede y Área Destino</span>
                        <span class="text-sm font-bold text-slate-800 block">{{ $requerimiento->sede->nombre ?? 'Sede General' }}</span>
                        <span class="text-[11px] text-slate-500">{{ $requerimiento->area->nombre ?? 'Área por definir' }}</span>
                    </div>
                </div>

                <!-- Si fue rechazado: Motivo de rechazo -->
                @if($requerimiento->estado === 'rechazado' && $requerimiento->motivo_rechazo)
                    <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl space-y-1">
                        <span class="text-xs font-bold text-rose-800 uppercase flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-xmark text-rose-600"></i> Motivo del Rechazo
                        </span>
                        <p class="text-xs text-rose-700 leading-relaxed font-medium">
                            {{ $requerimiento->motivo_rechazo }}
                        </p>
                    </div>
                @endif
            </div>

            <!-- Tarjeta de Archivo Adjunto: Cotización o Plan de Uso -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/90 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">
                            <i class="fa-solid fa-paperclip"></i>
                        </div>
                        <h2 class="text-base font-bold text-slate-800">
                            Documento Adjunto de Sustento
                        </h2>
                    </div>
                    <span class="text-xs text-slate-400 font-mono">
                        {{ $requerimiento->archivo_tipo ?? 'Cotización / Plan de uso' }}
                    </span>
                </div>

                @if($requerimiento->archivo_adjunto)
                    <div class="p-5 bg-indigo-50/60 border border-indigo-200/80 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xl shrink-0 shadow-md shadow-indigo-600/20">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-sm font-bold text-slate-800 truncate" title="{{ $requerimiento->archivo_nombre_original ?? basename($requerimiento->archivo_adjunto) }}">
                                    {{ $requerimiento->archivo_nombre_original ?? basename($requerimiento->archivo_adjunto) }}
                                </p>
                                <p class="text-xs text-indigo-700 font-medium">
                                    {{ $requerimiento->archivo_tipo ?? 'Cotización o Plan de Uso' }}
                                </p>
                            </div>
                        </div>

                        <a href="{{ $requerimiento->url_adjunto }}" target="_blank" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-md shadow-indigo-600/20 shrink-0">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver / Descargar Archivo
                        </a>
                    </div>
                @else
                    <div class="p-6 text-center text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                        <i class="fa-regular fa-file text-2xl text-slate-300 mb-1.5 block"></i>
                        <p class="text-xs font-medium">No se adjuntó archivo de cotización o plan de uso para esta solicitud.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Columna Derecha: Datos del Solicitante y Panel de Gestión -->
        <div class="space-y-6">

            <!-- Datos del Solicitante -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2 flex items-center gap-1.5">
                    <i class="fa-regular fa-user text-indigo-500"></i> Solicitante
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400 font-medium block">Usuario Solicitante</span>
                        <p class="text-sm font-bold text-slate-800">
                            {{ $requerimiento->usuario ? $requerimiento->usuario->nombre.' '.$requerimiento->usuario->apellido : 'Usuario Sistema' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-slate-400 font-medium block">Correo Electrónico</span>
                        <p class="font-mono text-slate-700 font-semibold">
                            {{ $requerimiento->usuario->email ?? 'No registrado' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-slate-400 font-medium block">Fecha y Hora de Solicitud</span>
                        <p class="font-mono text-slate-700 font-semibold">
                            {{ $requerimiento->fecha_solicitud ? $requerimiento->fecha_solicitud->format('d/m/Y H:i:s') : ($requerimiento->created_at ? $requerimiento->created_at->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s')) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Panel de Gestión de Estado (Solo Administrador) -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2 flex items-center gap-1.5">
                    <i class="fa-solid fa-sliders text-indigo-500"></i> Gestión de Estado
                </h3>

                @can('cambiarEstado', $requerimiento)
                    <form method="POST" action="{{ route('requerimientos.estado', $requerimiento) }}" class="space-y-3.5">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Cambiar Estado</label>
                            <select name="estado" id="estado_select" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">-- Estado actual: {{ \App\Models\Requerimiento::ESTADOS[$requerimiento->estado] }} --</option>
                                @foreach($requerimiento->estadosSiguientes() as $siguiente)
                                    <option value="{{ $siguiente }}" @selected(old('estado') === $siguiente)>{{ \App\Models\Requerimiento::ESTADOS[$siguiente] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Campo de motivo si se rechaza -->
                        <div id="motivo_rechazo_container" class="{{ $requerimiento->estado === 'rechazado' ? '' : 'hidden' }} space-y-1.5">
                            <label class="block text-xs font-bold text-rose-700 uppercase tracking-wider">Motivo del Rechazo</label>
                            <textarea name="motivo_rechazo" rows="3" placeholder="Explique la razón del rechazo..." class="w-full px-3 py-2 bg-rose-50/50 border border-rose-200 rounded-xl text-xs text-rose-900 focus:outline-none focus:ring-2 focus:ring-rose-500">{{ $requerimiento->motivo_rechazo }}</textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-600/20">
                            Actualizar Estado
                        </button>
                    </form>
                @else
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2">
                        @if($requerimiento->aprobador)
                            <p class="text-xs text-slate-500">Resuelto por <strong class="text-slate-700">{{ $requerimiento->aprobador->nombre_completo }}</strong> el {{ $requerimiento->fecha_resolucion?->format('d/m/Y H:i') }}.</p>
                        @endcan
                        <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-clock-rotate-left text-indigo-500"></i> Estado Actual de su Solicitud
                        </span>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Su requerimiento se encuentra en estado <strong class="text-slate-800 uppercase">{{ $requerimiento->estado_badge['label'] }}</strong> y está siendo gestionado por la Administración Patrimonial.
                        </p>
                    </div>
                @endif
            </div>

            <!-- Sello Institucional -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center space-y-1">
                <p class="text-xs font-bold text-slate-700">Colegio Adventista Túpac Amaru</p>
                <p class="text-[11px] text-slate-400">Sistema SIBIP &bull; Control Patrimonial</p>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
    function toggleRechazoField() {
        const select = document.getElementById('estado_select');
        const container = document.getElementById('motivo_rechazo_container');
        if (select.value === 'rechazado') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }

    document.getElementById('estado_select')?.addEventListener('change', toggleRechazoField);
</script>
@endsection
