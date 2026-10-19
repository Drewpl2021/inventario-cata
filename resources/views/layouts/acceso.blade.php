<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · SIBIP</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Pantallas sin sesión (login y recuperación de contraseña) con la identidad del colegio --}}
<body class="min-h-screen bg-marino-900">
    <div class="relative min-h-screen flex flex-col lg:flex-row">
        {{-- Foto del colegio cubriendo todo el panel izquierdo (y el fondo en escritorio), con degradado institucional --}}
        <div class="absolute inset-0 overflow-hidden" aria-hidden="true">
            <img src="{{ asset('images/edificio_cata.jpg') }}" alt="" class="w-full h-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-r from-marino-900/95 via-marino-800/80 to-marino-700/40"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-marino-900/80 via-transparent to-transparent lg:hidden"></div>
        </div>

        {{-- Identidad --}}
        <section class="relative flex-1 flex flex-col justify-between gap-10 px-6 sm:px-12 lg:px-16 pt-8 pb-6 lg:py-14 text-white">
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/logo_cata_emblema.png') }}" alt="Emblema del Colegio Adventista Túpac Amaru" class="h-16 sm:h-20 w-auto">
                <p class="text-lg sm:text-2xl font-extrabold leading-tight uppercase tracking-wide">Colegio<br>Adventista<br>Túpac Amaru</p>
            </div>
            <div class="max-w-lg">
                <h1 class="text-5xl sm:text-6xl font-extrabold tracking-tight">SIBIP</h1>
                <p class="text-lg sm:text-2xl font-semibold mt-2 leading-snug">Sistema Integrado de Bienes Patrimoniales</p>
                <p class="mt-4 text-base sm:text-lg text-white/85">Organizamos, controlamos y protegemos el patrimonio de nuestras tres sedes.</p>
            </div>
            <p class="hidden lg:block font-lema text-2xl text-white/90">Más que educación, una misión</p>
        </section>

        {{-- Tarjeta --}}
        <section class="relative w-full lg:w-[480px] xl:w-[520px] flex items-center justify-center px-4 sm:px-8 pb-8 lg:py-10">
            <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-6 sm:p-10">
                <h2 class="text-2xl sm:text-3xl font-bold text-marino-900 text-center">@yield('titulo_tarjeta')</h2>
                <p class="text-sm text-slate-500 text-center mt-2">@yield('subtitulo_tarjeta')</p>

                @if(session('success'))
                    <div class="mt-6 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm" role="status">
                        <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="mt-6 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}
                    </div>
                @endif

                @yield('content')

                <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                    <p class="text-sm font-semibold text-marino-900">Colegio Adventista Túpac Amaru</p>
                    <p class="text-sm text-marino-600 mt-1">Juliaca <span class="text-slate-300 mx-2">|</span> Jerusalén</p>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
