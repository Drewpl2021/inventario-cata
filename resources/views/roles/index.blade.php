@extends('layouts.app')

@section('title', 'Roles y permisos')
@section('page_header', 'Roles y permisos')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Roles y permisos</h1>
            <p class="text-xs text-slate-500 mt-0.5">Defina qué módulos y acciones puede usar cada rol. Los cambios se aplican de inmediato.</p>
        </div>
        @can('roles.crear')
            <a href="{{ route('roles.create') }}" class="px-4 py-2 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-lg text-sm font-semibold transition shadow-md shadow-blue-900/20 flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Nuevo rol
            </a>
        @endcan
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($roles as $rol)
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col gap-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-user-shield text-indigo-500"></i> {{ $rol->nombre }}
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">{{ $rol->descripcion ?: 'Sin descripción.' }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @if($rol->es_sistema)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-indigo-50 text-indigo-700 border border-indigo-200">Sistema</span>
                        @endif
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border {{ $rol->estaActivo() ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' }}">{{ $rol->estado }}</span>
                    </div>
                </div>

                <div class="flex gap-4 text-xs text-slate-600">
                    <span><strong class="text-slate-800">{{ $rol->usuarios_count }}</strong> usuarios</span>
                    <span><strong class="text-slate-800">{{ $rol->esAdministrador() ? 'Todos los' : $rol->permisos_count }}</strong> permisos</span>
                </div>

                <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-100 mt-auto">
                    @can('roles.editar')
                        <a href="{{ route('roles.edit', $rol) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold">
                            {{ $rol->esAdministrador() ? 'Ver permisos' : 'Editar permisos' }}
                        </a>
                        @unless($rol->esAdministrador())
                            <form method="POST" action="{{ route('roles.estado', $rol) }}" data-confirmar="¿Confirma el cambio de estado del rol?">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="estado" value="{{ $rol->estaActivo() ? 'inactivo' : 'activo' }}">
                                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $rol->estaActivo() ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                    {{ $rol->estaActivo() ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        @endunless
                    @endcan
                    @can('roles.eliminar')
                        @unless($rol->es_sistema)
                            <form method="POST" action="{{ route('roles.destroy', $rol) }}" data-confirmar="¿Eliminar este rol?" data-peligro data-aceptar="Eliminar">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 text-rose-600 hover:bg-rose-50 rounded-lg text-xs font-semibold">Eliminar</button>
                            </form>
                        @endunless
                    @endcan
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
