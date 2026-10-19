@extends('layouts.app')

@section('title', $bien->nombre)

@section('content')
<x-encabezado titulo="Detalle de bien" :volver="Auth::user()->can('bienes.ver') ? route('bienes.index') : route('mis-bienes.index')">
    <x-slot:acciones>
        @can('update', $bien)
            <a href="{{ route('bienes.edit', $bien) }}" class="btn-secundario"><i class="fa-solid fa-pen"></i> Editar</a>
        @endcan
        @can('solicitar', [$bien, 'movimientos'])
            <a href="{{ route('movimientos.create', ['bien_id' => $bien->id]) }}" class="btn-secundario"><i class="fa-solid fa-right-left"></i> Movimiento</a>
        @endcan
        @can('solicitar', [$bien, 'bajas'])
            <a href="{{ route('bajas.create', ['bien_id' => $bien->id]) }}" class="btn-secundario text-rose-700"><i class="fa-solid fa-ban"></i> Dar de baja</a>
        @endcan
        @can('create', \App\Models\Bien::class)
            <a href="{{ route('bienes.create') }}" class="btn-primario"><i class="fa-solid fa-plus"></i> Nuevo bien</a>
        @endcan
    </x-slot:acciones>
</x-encabezado>

<div class="space-y-6">
    {{-- Datos del bien --}}
    <section class="tarjeta p-5 sm:p-6 grid grid-cols-1 lg:grid-cols-[220px_1fr_240px] gap-6 items-start">
        <div class="aspect-[4/3] lg:aspect-square rounded-2xl bg-slate-100 overflow-hidden flex items-center justify-center">
            @if($bien->fotografia)
                <img src="{{ $bien->url_fotografia }}" alt="Fotografía de {{ $bien->nombre }}" class="w-full h-full object-cover">
            @else
                <i class="fa-solid fa-box-archive text-5xl text-slate-300" aria-hidden="true"></i>
            @endif
        </div>

        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2.5 text-sm">
            <dt class="text-slate-500">Código:</dt><dd class="font-mono font-semibold text-slate-800">{{ $bien->codigo_barras }}</dd>
            <dt class="text-slate-500">Nombre:</dt><dd class="font-semibold text-slate-800">{{ $bien->nombre }}</dd>
            <dt class="text-slate-500">Categoría:</dt><dd>{{ $bien->categoria->nombre ?? '—' }}</dd>
            <dt class="text-slate-500">Sede:</dt><dd>{{ $bien->sede->nombre ?? '—' }}</dd>
            <dt class="text-slate-500">Ubicación:</dt><dd>{{ $bien->area->nombre ?? 'Sin área' }}{{ $bien->area?->ubicacion ? ' · '.$bien->area->ubicacion : '' }}</dd>
            <dt class="text-slate-500">Responsable:</dt><dd>{{ $bien->responsable?->nombre_completo ?? 'Sin asignar' }}</dd>
            <dt class="text-slate-500">Estado:</dt><dd><x-estado-bien :estado="$bien->estado" /></dd>
            <dt class="text-slate-500">Cód. patrimonial:</dt><dd class="font-mono">{{ $bien->codigo_patrimonial ?? 'Sin código oficial' }}</dd>
            @if($bien->marca || $bien->modelo || $bien->numero_serie)
                <dt class="text-slate-500">Marca / modelo:</dt><dd>{{ trim($bien->marca.' '.$bien->modelo) ?: '—' }}{{ $bien->numero_serie ? ' · S/N '.$bien->numero_serie : '' }}</dd>
            @endif
            <dt class="text-slate-500">Adquisición:</dt>
            <dd>
                {{ $bien->fecha_adquisicion?->format('d/m/Y') ?? 'Sin fecha' }}
                @if($bien->valor_adquisicion) · S/ {{ number_format($bien->valor_adquisicion, 2) }} @endif
                · {{ $bien->tipo_ingreso_texto }}{{ $bien->numero_comprobante ? ' ('.$bien->numero_comprobante.')' : '' }}
            </dd>
        </dl>

        <div class="flex flex-col items-center text-center gap-3 lg:border-l lg:border-slate-100 lg:pl-6">
            <img src="{{ route('bienes.codigo-barras', $bien) }}" alt="Código de barras {{ $bien->codigo_barras }}" class="w-full max-w-[220px] h-16">
            <span class="font-mono text-sm font-bold tracking-wider text-slate-800">{{ $bien->codigo_barras }}</span>
            <p class="text-xs text-slate-500">
                @if($bien->etiqueta_impresa_at)
                    <i class="fa-solid fa-tag text-emerald-600"></i> Etiqueta impresa el {{ $bien->etiqueta_impresa_at->format('d/m/Y H:i') }}
                @else
                    <span class="badge-ambar"><i class="fa-solid fa-tag"></i> Etiqueta aún no impresa</span>
                @endif
            </p>
            @can('etiquetas.imprimir')
                <div class="flex flex-wrap justify-center gap-2">
                    <a href="{{ route('bienes.etiqueta', [$bien, 'html']) }}" target="_blank" class="btn-primario px-3"><i class="fa-solid fa-print"></i> Etiqueta</a>
                    <a href="{{ route('bienes.etiqueta', [$bien, 'pdf']) }}" target="_blank" class="btn-icono border border-slate-200" title="Etiqueta en PDF" aria-label="Etiqueta en PDF"><i class="fa-regular fa-file-pdf"></i></a>
                    <a href="{{ route('bienes.etiqueta', [$bien, 'png']) }}" class="btn-icono border border-slate-200" title="Etiqueta en PNG (apps Bluetooth)" aria-label="Etiqueta en PNG"><i class="fa-regular fa-image"></i></a>
                </div>
            @endcan
        </div>
    </section>

    @if($bien->baja && $bien->baja->estado !== 'rechazada')
        <div class="tarjeta p-4 sm:p-5 flex items-start gap-3 {{ $bien->baja->estado === 'aprobada' ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-amber-50 border-amber-200 text-amber-900' }}">
            <i class="fa-solid fa-ban mt-0.5"></i>
            <p class="text-sm">
                <strong>{{ $bien->baja->estado_badge['label'] }}</strong> · Motivo: {{ str_replace('_', ' ', $bien->baja->motivo) }} · {{ $bien->baja->fecha_baja?->format('d/m/Y') }}
                @if($bien->baja->documento_referencia) · {{ $bien->baja->documento_referencia }} @endif
                @if($bien->baja->descripcion)<br><span class="opacity-80">{{ $bien->baja->descripcion }}</span>@endif
            </p>
        </div>
    @endif

    {{-- Pestañas del mockup + mantenimientos y documentos --}}
    <section class="tarjeta overflow-hidden" data-pestanas>
        <div class="flex overflow-x-auto border-b border-slate-200 px-2" role="tablist">
            @foreach(['movimientos' => 'Historial de movimientos', 'estados' => 'Historial de estados', 'observaciones' => 'Observaciones', 'documentos' => 'Documentos ('.$bien->documentos->count().')'] as $clave => $titulo)
                <button type="button" role="tab" data-pestana="{{ $clave }}" class="min-h-12 px-4 text-sm font-semibold whitespace-nowrap border-b-2 border-transparent text-slate-500 hover:text-marino-800">{{ $titulo }}</button>
            @endforeach
        </div>

        <div data-panel="movimientos" role="tabpanel">
            <table class="tabla tabla-tarjetas">
                <thead><tr><th>Fecha</th><th>Movimiento</th><th>Detalle</th><th>Usuario</th></tr></thead>
                <tbody>
                    @foreach($historial as $evento)
                        <tr>
                            <td data-label="Fecha" class="text-slate-500 whitespace-nowrap">{{ $evento['fecha']?->format('d/m/Y') }}</td>
                            <td data-label="Movimiento" class="font-medium"><i class="fa-solid {{ $evento['icono'] }} text-slate-400 mr-1"></i> {{ $evento['evento'] }}</td>
                            <td data-label="Detalle" class="text-slate-600">{{ $evento['detalle'] }}</td>
                            <td data-label="Usuario" class="text-slate-600">{{ $evento['usuario'] ?? 'Sistema' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div data-panel="estados" role="tabpanel" class="hidden">
            @if($historialEstados->isEmpty())
                <x-estado-vacio icono="fa-clock-rotate-left" titulo="Sin cambios de estado registrados" />
            @else
                <table class="tabla tabla-tarjetas">
                    <thead><tr><th>Fecha</th><th>Estado anterior</th><th>Estado nuevo</th><th>Usuario</th></tr></thead>
                    <tbody>
                        @foreach($historialEstados as $cambio)
                            <tr>
                                <td data-label="Fecha" class="text-slate-500 whitespace-nowrap">{{ $cambio['fecha']?->format('d/m/Y H:i') }}</td>
                                <td data-label="Estado anterior">@if($cambio['anterior'])<x-estado-bien :estado="$cambio['anterior']" />@else <span class="text-slate-400">Alta</span> @endif</td>
                                <td data-label="Estado nuevo"><x-estado-bien :estado="$cambio['nuevo']" /></td>
                                <td data-label="Usuario" class="text-slate-600">{{ $cambio['usuario'] ?? 'Sistema' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div data-panel="observaciones" role="tabpanel" class="hidden p-5 sm:p-6 space-y-4 text-sm">
            @if($bien->descripcion)
                <div><p class="font-semibold text-slate-700">Descripción</p><p class="text-slate-600 mt-1">{{ $bien->descripcion }}</p></div>
            @endif
            <div>
                <p class="font-semibold text-slate-700">Observaciones</p>
                <p class="text-slate-600 mt-1">{{ $bien->observaciones ?: 'Sin observaciones registradas.' }}</p>
            </div>
            @if($bien->mantenimientos->isNotEmpty())
                <div>
                    <p class="font-semibold text-slate-700">Mantenimientos</p>
                    <ul class="mt-1 space-y-1 text-slate-600">
                        @foreach($bien->mantenimientos as $mantenimiento)
                            <li>{{ $mantenimiento->fecha_inicio->format('d/m/Y') }} · {{ ucfirst($mantenimiento->tipo) }} · {{ $mantenimiento->descripcion }} <span class="badge-gris">{{ str_replace('_', ' ', $mantenimiento->estado) }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div data-panel="documentos" role="tabpanel" class="hidden">
            @if($bien->documentos->isEmpty() && ! $bien->comprobante_archivo)
                <x-estado-vacio icono="fa-folder-open" titulo="Sin documentos asociados" />
            @else
                <ul class="divide-y divide-slate-100">
                    @if($bien->comprobante_archivo)
                        <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                            <span><i class="fa-solid fa-file-invoice text-slate-400 mr-2"></i>Comprobante de ingreso {{ $bien->numero_comprobante ? '('.$bien->numero_comprobante.')' : '' }}</span>
                            <a href="{{ $bien->url_comprobante }}" target="_blank" class="btn-icono" aria-label="Abrir comprobante"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                        </li>
                    @endif
                    @foreach($bien->documentos as $documento)
                        <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                            <span>
                                <i class="fa-regular fa-file text-slate-400 mr-2"></i>{{ $documento->nombre }}
                                <span class="block text-xs text-slate-400">{{ ucfirst($documento->tipo) }} · {{ $documento->created_at?->format('d/m/Y') }} · {{ $documento->usuario?->nombre_completo }}</span>
                            </span>
                            <a href="{{ $documento->url }}" target="_blank" class="btn-icono" aria-label="Abrir documento"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</div>
@endsection
