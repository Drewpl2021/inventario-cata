@extends('layouts.app')

@section('title', 'Auditoría')

@php
    $acciones = ['crear' => 'badge-verde', 'actualizar' => 'badge-azul', 'eliminar' => 'badge-rojo', 'desactivar' => 'badge-gris', 'restaurar' => 'badge-verde', 'permisos' => 'badge-morado', 'imprimir_etiquetas' => 'badge-gris', 'exportar_reporte' => 'badge-gris', 'inicio_sesion' => 'badge-verde', 'inicio_sesion_fallido' => 'badge-rojo', 'inicio_sesion_bloqueado' => 'badge-rojo', 'restablecer_password' => 'badge-azul', 'migrar_archivos' => 'badge-gris'];
@endphp

@section('content')
<x-encabezado titulo="Auditoría" subtitulo="Quién hizo qué, cuándo y desde dónde. Los cambios guardan los datos anteriores y los nuevos." />

<section class="tarjeta overflow-hidden">
    <form method="GET" class="p-4 sm:p-5 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div>
            <label for="filtro-tabla" class="etiqueta-campo text-xs">Tabla</label>
            <select id="filtro-tabla" name="tabla" class="campo">
                <option value="">Todas</option>
                @foreach($tablas as $tabla)
                    <option value="{{ $tabla }}" @selected(request('tabla') === $tabla)>{{ $tabla }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filtro-accion" class="etiqueta-campo text-xs">Acción</label>
            <select id="filtro-accion" name="accion" class="campo">
                <option value="">Todas</option>
                @foreach($acciones as $accion => $badge)
                    <option value="{{ $accion }}" @selected(request('accion') === $accion)>{{ str_replace('_', ' ', $accion) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filtro-usuario" class="etiqueta-campo text-xs">Usuario</label>
            <select id="filtro-usuario" name="usuario_id" class="campo">
                <option value="">Todos</option>
                @foreach($usuarios as $usuario)
                    <option value="{{ $usuario->id }}" @selected(request('usuario_id') == $usuario->id)>{{ $usuario->nombre_completo }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filtro-desde" class="etiqueta-campo text-xs">Desde</label>
            <input type="date" id="filtro-desde" name="desde" value="{{ request('desde') }}" class="campo">
        </div>
        <div>
            <label for="filtro-hasta" class="etiqueta-campo text-xs">Hasta</label>
            <input type="date" id="filtro-hasta" name="hasta" value="{{ request('hasta') }}" class="campo">
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn-secundario w-full"><i class="fa-solid fa-filter"></i> Filtrar</button>
        </div>
    </form>

    @if($auditorias->isEmpty())
        <x-estado-vacio icono="fa-clock-rotate-left" titulo="No hay registros de auditoría" :mensaje="request()->hasAny(['tabla', 'accion', 'usuario_id', 'desde', 'hasta']) ? 'Pruebe con otros filtros.' : null" />
    @else
        <table class="tabla">
            <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Registro</th><th>IP</th><th>Detalle</th></tr></thead>
            <tbody>
                @foreach($auditorias as $registro)
                    <tr class="align-top">
                        <td class="text-slate-500 whitespace-nowrap">{{ $registro->fecha?->format('d/m/Y H:i:s') }}</td>
                        <td class="font-medium text-slate-800">{{ $registro->usuario?->nombre_completo ?? 'Sistema' }}</td>
                        <td><span class="{{ $acciones[$registro->accion] ?? 'badge-gris' }}">{{ str_replace('_', ' ', $registro->accion) }}</span></td>
                        <td class="font-mono text-xs">{{ $registro->tabla }}{{ $registro->registro_id ? ' #'.$registro->registro_id : '' }}</td>
                        <td class="font-mono text-xs text-slate-500">{{ $registro->ip ?? '—' }}</td>
                        <td>
                            @if($registro->datos_anteriores || $registro->datos_nuevos)
                                <details class="text-xs text-left">
                                    <summary class="cursor-pointer text-marino-700 font-semibold min-h-11 inline-flex items-center">Ver cambios</summary>
                                    <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 max-w-md">
                                        @foreach(array_unique(array_merge(array_keys($registro->datos_anteriores ?? []), array_keys($registro->datos_nuevos ?? []))) as $campo)
                                            <dt class="font-mono text-slate-500">{{ $campo }}</dt>
                                            <dd class="break-all">
                                                @if(array_key_exists($campo, $registro->datos_anteriores ?? []))
                                                    <span class="line-through text-rose-600">{{ is_scalar($registro->datos_anteriores[$campo]) || $registro->datos_anteriores[$campo] === null ? ($registro->datos_anteriores[$campo] ?? 'vacío') : json_encode($registro->datos_anteriores[$campo], JSON_UNESCAPED_UNICODE) }}</span>
                                                @endif
                                                @if(array_key_exists($campo, $registro->datos_nuevos ?? []))
                                                    <span class="text-emerald-700">{{ is_scalar($registro->datos_nuevos[$campo]) || $registro->datos_nuevos[$campo] === null ? ($registro->datos_nuevos[$campo] ?? 'vacío') : json_encode($registro->datos_nuevos[$campo], JSON_UNESCAPED_UNICODE) }}</span>
                                                @endif
                                            </dd>
                                        @endforeach
                                    </dl>
                                </details>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($auditorias->hasPages())<div class="p-4 border-t border-slate-100">{{ $auditorias->links() }}</div>@endif
    @endif
</section>
@endsection
