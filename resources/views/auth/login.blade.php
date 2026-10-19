@extends('layouts.acceso')

@section('title', 'Iniciar sesión')
@section('titulo_tarjeta', 'Iniciar sesión')
@section('subtitulo_tarjeta', 'Ingresa tus credenciales para acceder al sistema')

@section('content')
    @if(request()->boolean('expirada') && ! $errors->any() && ! session('success'))
        <div class="mt-6 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm" role="status">
            <i class="fa-solid fa-clock"></i> Su sesión se cerró por inactividad. Vuelva a ingresar.
        </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}" class="mt-6 space-y-4">
        @csrf
        <div class="relative">
            <label for="login_input" class="sr-only">Usuario o correo</label>
            <i class="fa-regular fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" id="login_input" name="login" value="{{ old('login') }}" required autofocus autocomplete="username"
                   placeholder="Usuario" class="campo h-13 pl-11 text-base">
        </div>
        <div class="relative">
            <label for="password_input" class="sr-only">Contraseña</label>
            <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="password" id="password_input" name="password" required autocomplete="current-password"
                   placeholder="Contraseña" class="campo h-13 pl-11 pr-12 text-base">
            <button type="button" data-ver-password="password_input" class="btn-icono absolute right-1 top-1/2 -translate-y-1/2" aria-label="Mostrar contraseña">
                <i class="fa-regular fa-eye"></i>
            </button>
        </div>
        <button type="submit" class="btn-primario w-full h-13 text-base">Ingresar</button>
    </form>

    <div class="text-center mt-4">
        <a href="{{ route('password.request') }}" class="inline-flex items-center text-sm font-semibold text-marino-600 hover:text-marino-800 min-h-11">¿Olvidaste tu contraseña?</a>
    </div>

    @env('local')
        <div class="mt-6 p-3 rounded-2xl bg-slate-50 border border-slate-100">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 text-center mb-2">Acceso rápido demo (solo en local)</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach(\Database\Seeders\DemoSeeder::CREDENCIALES as $correo => [$clave, $rol])
                    <button type="button" data-demo-correo="{{ $correo }}" data-demo-clave="{{ $clave }}" class="btn-secundario text-xs flex-col gap-0 py-1.5">
                        <span class="font-bold text-marino-800">{{ $rol }}</span>
                        <span class="text-[10px] text-slate-400 font-normal">{{ $correo }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endenv
@endsection
