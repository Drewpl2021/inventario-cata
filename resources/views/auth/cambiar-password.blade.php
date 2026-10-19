@extends('layouts.app')

@section('title', 'Cambiar contraseña')
@section('page_header', 'Cambiar contraseña')

@section('content')
<div class="max-w-lg mx-auto space-y-6">
    @if($obligatorio)
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm flex items-start gap-3">
            <i class="fa-solid fa-key text-amber-600 text-lg mt-0.5"></i>
            <div>
                <p class="font-bold">Debe cambiar su contraseña temporal</p>
                <p class="text-xs mt-0.5">Su cuenta tiene una contraseña asignada por el Administrador. Elija una contraseña propia para continuar usando el sistema.</p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.cambiar.update') }}" class="bg-white p-8 rounded-2xl border border-slate-200/80 shadow-sm space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="password_actual" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Contraseña actual *</label>
            <input type="password" id="password_actual" name="password_actual" required autocomplete="current-password" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            @error('password_actual') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Nueva contraseña *</label>
            <input type="password" id="password" name="password" required autocomplete="new-password" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            <p class="text-[11px] text-slate-400 mt-1">Mínimo 8 caracteres, con letras y números.</p>
            @error('password') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">Confirmar nueva contraseña *</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-5">
            <button type="submit" class="px-6 py-2.5 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-900/20 flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk text-xs"></i> Guardar contraseña
            </button>
        </div>
    </form>
</div>
@endsection
