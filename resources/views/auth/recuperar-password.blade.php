@extends('layouts.acceso')

@section('title', 'Recuperar contraseña')
@section('titulo_tarjeta', 'Recuperar contraseña')
@section('subtitulo_tarjeta', 'Le enviaremos un enlace a su correo para elegir una contraseña nueva')

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <div class="relative">
            <label for="email" class="sr-only">Correo electrónico</label>
            <i class="fa-regular fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   placeholder="Correo electrónico" class="campo h-13 pl-11 text-base">
        </div>
        <button type="submit" class="btn-primario w-full h-13 text-base">Enviar enlace</button>
    </form>

    <p class="text-xs text-slate-500 text-center mt-4">Si su cuenta no tiene correo o no lo recibe, comuníquese con el Administrador: le asignará una contraseña temporal.</p>

    <div class="text-center mt-2">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-marino-600 hover:text-marino-800 min-h-11">
            <i class="fa-solid fa-arrow-left"></i> Volver a iniciar sesión
        </a>
    </div>
@endsection
