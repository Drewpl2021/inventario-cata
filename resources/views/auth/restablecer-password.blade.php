@extends('layouts.acceso')

@section('title', 'Restablecer contraseña')
@section('titulo_tarjeta', 'Nueva contraseña')
@section('subtitulo_tarjeta', 'Mínimo 8 caracteres, con letras y números')

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="etiqueta-campo">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" class="campo">
        </div>
        <div>
            <label for="password" class="etiqueta-campo">Nueva contraseña</label>
            <div class="relative">
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" class="campo pr-12">
                <button type="button" data-ver-password="password" class="btn-icono absolute right-0 top-1/2 -translate-y-1/2" aria-label="Mostrar contraseña">
                    <i class="fa-regular fa-eye"></i>
                </button>
            </div>
        </div>
        <div>
            <label for="password_confirmation" class="etiqueta-campo">Repita la nueva contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password" class="campo">
        </div>
        <button type="submit" class="btn-primario w-full h-13 text-base">Guardar contraseña</button>
    </form>

    <div class="text-center mt-4">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-marino-600 hover:text-marino-800 min-h-11">
            <i class="fa-solid fa-arrow-left"></i> Volver a iniciar sesión
        </a>
    </div>
@endsection
