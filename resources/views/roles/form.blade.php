@extends('layouts.app')

@php
    $editando = $rol->exists;
    $esAdministrador = $editando && $rol->esAdministrador();
    $seleccionados = old('permisos', $seleccionados);
@endphp

@section('title', $editando ? 'Editar rol' : 'Nuevo rol')
@section('page_header', 'Roles y permisos')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">{{ $editando ? "Rol: {$rol->nombre}" : 'Nuevo rol' }}</h1>
            <p class="text-xs text-slate-500 mt-0.5">Marque las acciones permitidas en cada módulo.</p>
        </div>
        <a href="{{ route('roles.index') }}" class="px-3.5 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg text-sm font-medium transition">&larr; Volver</a>
    </div>

    @if($esAdministrador)
        <div class="p-4 bg-indigo-50 border border-indigo-200 rounded-2xl text-indigo-900 text-sm flex items-center gap-3">
            <i class="fa-solid fa-lock text-indigo-600"></i>
            El rol Administrador tiene siempre todos los permisos; no se le pueden quitar.
        </div>
    @endif

    <form method="POST" action="{{ $editando ? route('roles.update', $rol) : route('roles.store') }}" class="space-y-6">
        @csrf
        @if($editando) @method('PUT') @endif

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label for="nombre" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Nombre del rol *</label>
                <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $rol->nombre) }}" required maxlength="50"
                       @readonly($rol->es_sistema)
                       class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm {{ $rol->es_sistema ? 'bg-slate-100 text-slate-500' : 'bg-slate-50' }}">
                @if($rol->es_sistema)
                    <p class="text-[11px] text-slate-400 mt-1">Los roles del sistema no se pueden renombrar.</p>
                @endif
                @error('nombre') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="descripcion" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Descripción</label>
                <input type="text" id="descripcion" name="descripcion" value="{{ old('descripcion', $rol->descripcion) }}" maxlength="255" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-sin-tarjetas>
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                        <tr>
                            <th class="py-3 px-4 text-left">Módulo</th>
                            @foreach(\App\Models\Permiso::ACCIONES as $etiqueta)
                                <th class="py-3 px-2 text-center">{{ $etiqueta }}</th>
                            @endforeach
                            <th class="py-3 px-2 text-center">Todo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($modulos as $modulo)
                            @php($porAccion = $modulo->permisos->keyBy('accion'))
                            <tr class="hover:bg-slate-50/60" data-fila-modulo>
                                <td class="py-2.5 px-4 font-medium text-slate-700 whitespace-nowrap">
                                    <i class="fa-solid {{ $modulo->icono }} text-slate-400 w-5"></i> {{ $modulo->nombre }}
                                </td>
                                @foreach(array_keys(\App\Models\Permiso::ACCIONES) as $accion)
                                    <td class="py-2.5 px-2 text-center">
                                        @if($permiso = $porAccion->get($accion))
                                            <input type="checkbox" name="permisos[]" value="{{ $permiso->id }}"
                                                   title="{{ $permiso->slug }}"
                                                   @checked($esAdministrador || in_array($permiso->id, $seleccionados))
                                                   @disabled($esAdministrador)
                                                   class="w-4 h-4 accent-[#0b3b70] cursor-pointer">
                                        @else
                                            <span class="text-slate-200">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-2.5 px-2 text-center">
                                    <input type="checkbox" data-marcar-fila @disabled($esAdministrador) class="w-4 h-4 accent-slate-600 cursor-pointer" title="Marcar o desmarcar todo el módulo">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-900/20 flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk text-xs"></i> {{ $editando ? 'Guardar cambios' : 'Crear rol' }}
            </button>
        </div>
    </form>
</div>

<script nonce="{{ Vite::cspNonce() }}">
    document.querySelectorAll('[data-fila-modulo]').forEach(function (fila) {
        const todo = fila.querySelector('[data-marcar-fila]');
        const casillas = fila.querySelectorAll('input[name="permisos[]"]');
        const sincronizar = () => { todo.checked = casillas.length > 0 && [...casillas].every((c) => c.checked); };
        todo.addEventListener('change', () => casillas.forEach((c) => { if (!c.disabled) c.checked = todo.checked; }));
        casillas.forEach((c) => c.addEventListener('change', sincronizar));
        sincronizar();
    });
</script>
@endsection
