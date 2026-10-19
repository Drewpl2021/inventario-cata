@extends('layouts.app')

@php($editando = $usuario->exists)

@section('title', $editando ? 'Editar usuario' : 'Nuevo usuario')
@section('page_header', $editando ? 'Editar usuario' : 'Nuevo usuario')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">{{ $editando ? $usuario->nombre_completo : 'Registrar nueva cuenta' }}</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                @if($editando)
                    Los cambios de rol y estado se aplican en la siguiente acción del usuario.
                @else
                    El sistema genera una contraseña temporal que se muestra una sola vez.
                @endif
            </p>
        </div>
        <a href="{{ route('usuarios.index') }}" class="px-3.5 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg text-sm font-medium transition">&larr; Volver</a>
    </div>

    <form method="POST" action="{{ $editando ? route('usuarios.update', $usuario) : route('usuarios.store') }}" class="bg-white p-8 rounded-2xl border border-slate-200/80 shadow-sm space-y-6">
        @csrf
        @if($editando) @method('PUT') @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="nombre" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Nombre *</label>
                <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $usuario->nombre) }}" required maxlength="100" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                @error('nombre') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="apellido" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Apellido *</label>
                <input type="text" id="apellido" name="apellido" value="{{ old('apellido', $usuario->apellido) }}" required maxlength="100" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                @error('apellido') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Correo electrónico *</label>
                <input type="email" id="email" name="email" value="{{ old('email', $usuario->email) }}" required maxlength="150" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                @error('email') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="rol_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Rol *</label>
                <select id="rol_id" name="rol_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" @disabled($editando && $usuario->id === Auth::id())>
                    <option value="">-- Seleccionar rol --</option>
                    @foreach($roles as $rol)
                        <option value="{{ $rol->id }}" @selected(old('rol_id', $usuario->rol_id) == $rol->id)>{{ $rol->nombre }}</option>
                    @endforeach
                </select>
                @if($editando && $usuario->id === Auth::id())
                    <input type="hidden" name="rol_id" value="{{ $usuario->rol_id }}">
                    <p class="text-[11px] text-slate-400 mt-1">No puede cambiar su propio rol.</p>
                @endif
                @error('rol_id') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="estado" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Estado *</label>
                <select id="estado" name="estado" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" @disabled($editando && $usuario->id === Auth::id())>
                    <option value="activo" @selected(old('estado', $usuario->estado) === 'activo')>Activo</option>
                    <option value="inactivo" @selected(old('estado', $usuario->estado) === 'inactivo')>Inactivo</option>
                </select>
                @if($editando && $usuario->id === Auth::id())
                    <input type="hidden" name="estado" value="activo">
                @endif
                @error('estado') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="sede_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Sede <span class="marca-responsable hidden text-rose-500">*</span></label>
                <select id="sede_id" name="sede_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                    <option value="">-- Sin sede --</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" @selected(old('sede_id', $usuario->sede_id) == $sede->id)>{{ $sede->nombre }}</option>
                    @endforeach
                </select>
                @error('sede_id') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="area_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Área <span class="marca-responsable hidden text-rose-500">*</span></label>
                <select id="area_id" name="area_id" data-sede-select="sede_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                    <option value="">-- Sin área --</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}" data-sede="{{ $area->sede_id }}" @selected(old('area_id', $usuario->area_id) == $area->id)>{{ $area->nombre }}</option>
                    @endforeach
                </select>
                @error('area_id') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div id="bloque-responsable" class="md:col-span-2 hidden p-4 bg-emerald-50/60 border border-emerald-200 rounded-xl space-y-2">
                <label for="responsable_existente_id" class="block text-xs font-semibold uppercase tracking-wider text-emerald-800">Responsable de bienes vinculado</label>
                <select id="responsable_existente_id" name="responsable_existente_id" class="w-full px-3.5 py-2.5 bg-white border border-emerald-200 rounded-xl text-sm">
                    <option value="">{{ $usuario->responsable ? 'Mantener: '.$usuario->responsable->nombre_completo : 'Crear un responsable nuevo con los datos de la cuenta' }}</option>
                    @foreach($responsablesLibres as $responsable)
                        @continue($usuario->responsable && $usuario->responsable->id === $responsable->id)
                        <option value="{{ $responsable->id }}" @selected(old('responsable_existente_id') == $responsable->id)>{{ $responsable->nombre_completo }}{{ $responsable->dni ? ' — DNI '.$responsable->dni : '' }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-emerald-800/80">Las cuentas de rol "Usuario" quedan vinculadas a un responsable: solo verán los bienes a su cargo.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                    <div>
                        <label for="dni" class="block text-xs font-semibold uppercase tracking-wider text-emerald-800 mb-1">DNI</label>
                        <input type="text" id="dni" name="dni" inputmode="numeric" maxlength="20" value="{{ old('dni', $usuario->responsable?->dni) }}" class="w-full px-3.5 py-2.5 bg-white border border-emerald-200 rounded-xl text-sm">
                        @error('dni') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cargo" class="block text-xs font-semibold uppercase tracking-wider text-emerald-800 mb-1">Cargo</label>
                        <input type="text" id="cargo" name="cargo" maxlength="100" value="{{ old('cargo', $usuario->responsable?->cargo) }}" placeholder="Docente, secretaria..." class="w-full px-3.5 py-2.5 bg-white border border-emerald-200 rounded-xl text-sm">
                    </div>
                    <div>
                        <label for="telefono" class="block text-xs font-semibold uppercase tracking-wider text-emerald-800 mb-1">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" maxlength="30" value="{{ old('telefono', $usuario->responsable?->telefono) }}" class="w-full px-3.5 py-2.5 bg-white border border-emerald-200 rounded-xl text-sm">
                    </div>
                </div>
                @error('responsable_existente_id') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="px-6 py-2.5 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-900/20 flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk text-xs"></i> {{ $editando ? 'Guardar cambios' : 'Crear usuario' }}
            </button>
        </div>
    </form>
</div>

@include('partials.filtro-areas-por-sede')

<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        const rolesConResponsable = @json($rolesConResponsable);
        const selectRol = document.getElementById('rol_id');
        const bloque = document.getElementById('bloque-responsable');
        const marcas = document.querySelectorAll('.marca-responsable');

        const actualizar = function () {
            const requiere = rolesConResponsable.includes(parseInt(selectRol.value, 10));
            bloque.classList.toggle('hidden', !requiere);
            marcas.forEach((m) => m.classList.toggle('hidden', !requiere));
            document.getElementById('sede_id').required = requiere;
            document.getElementById('area_id').required = requiere;
        };

        selectRol.addEventListener('change', actualizar);
        actualizar();
    })();
</script>
@endsection
