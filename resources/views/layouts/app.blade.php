<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Aviso de cierre por inactividad (resources/js/app.js) --}}
    <meta name="sesion-minutos" content="{{ config('session.lifetime') }}">
    <meta name="sesion-renovar" content="{{ route('sesion.renovar') }}">
    <title>@yield('title', 'Inicio') · SIBIP</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen">
    @php($usuarioActual = Auth::user())

    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[80] btn-primario">Saltar al contenido</a>

    {{-- Fondo oscuro del menú en celular y tablet --}}
    <div id="menu-fondo" class="hidden fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    {{-- Menú lateral --}}
    <aside id="menu-lateral" class="fixed inset-y-0 left-0 z-40 w-72 lg:w-64 -translate-x-full lg:translate-x-0 transition-transform duration-200 bg-gradient-to-b from-marino-800 to-marino-900 text-white flex flex-col shadow-xl" aria-label="Menú principal">
        <div class="flex items-center justify-between gap-3 px-5 pt-5 pb-4">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/logo_cata_emblema.png') }}" alt="" class="h-14 w-auto shrink-0">
                <span class="text-[13px] font-extrabold leading-tight tracking-wide uppercase">Colegio<br>Adventista<br>Túpac Amaru</span>
            </a>
            <button type="button" class="btn-icono text-white/80 hover:bg-white/10 hover:text-white lg:hidden" data-cerrar-menu aria-label="Cerrar menú">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-1">
            @foreach($menuLateral as $item)
                @if($item['tipo'] === 'enlace')
                    <a href="{{ $item['url'] }}" @class(['menu-item', 'menu-item-activo' => $item['activo']]) @if($item['activo']) aria-current="page" @endif>
                        <i class="fa-solid {{ $item['icono'] }} w-5 text-center"></i>
                        <span class="flex-1">{{ $item['nombre'] }}</span>
                        @if(($item['contador'] ?? 0) > 0)
                            <span class="min-w-6 h-6 px-1.5 rounded-full bg-amber-400 text-marino-900 text-xs font-bold flex items-center justify-center" aria-label="{{ $item['contador'] }} pendientes">{{ $item['contador'] }}</span>
                        @endif
                    </a>
                @else
                    <div>
                        <button type="button" data-submenu="{{ $item['id'] }}" aria-expanded="{{ $item['activo'] ? 'true' : 'false' }}" aria-controls="{{ $item['id'] }}" class="menu-item w-full">
                            <i class="fa-solid {{ $item['icono'] }} w-5 text-center"></i>
                            <span class="flex-1 text-left">{{ $item['nombre'] }}</span>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform {{ $item['activo'] ? 'rotate-180' : '' }}" data-flecha></i>
                        </button>
                        <div id="{{ $item['id'] }}" @class(['mt-1 space-y-1 pl-4', 'hidden' => ! $item['activo']])>
                            @foreach($item['hijos'] as $hijo)
                                <a href="{{ $hijo['url'] }}" @class(['menu-item text-[13px]', 'menu-item-activo' => $hijo['activo']]) @if($hijo['activo']) aria-current="page" @endif>
                                    <i class="fa-solid {{ $hijo['icono'] }} w-5 text-center text-xs"></i>
                                    <span>{{ $hijo['nombre'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        {{-- Usuario y sesión --}}
        <div class="p-3 border-t border-white/10 space-y-1">
            <div class="flex items-center gap-3 px-2 py-2">
                <span class="w-10 h-10 rounded-full bg-white/15 flex items-center justify-center font-bold">
                    {{ mb_strtoupper(mb_substr($usuarioActual->nombre ?? 'U', 0, 1).mb_substr($usuarioActual->apellido ?? '', 0, 1)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold truncate">{{ $usuarioActual?->nombre_completo }}</p>
                    <p class="text-xs text-white/60 truncate">{{ $usuarioActual?->area?->nombre ?? $usuarioActual?->rol?->nombre }}</p>
                </div>
            </div>
            <a href="{{ route('password.cambiar') }}" @class(['menu-item', 'menu-item-activo' => request()->routeIs('password.cambiar')])>
                <i class="fa-solid fa-key w-5 text-center"></i> Cambiar contraseña
            </a>
            <form method="POST" action="{{ route('logout') }}" data-cerrar-sesion>
                @csrf
                <button type="submit" class="menu-item w-full">
                    <i class="fa-solid fa-right-from-bracket w-5 text-center"></i> Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-64 min-h-screen flex flex-col">
        {{-- Encabezado --}}
        <header class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-slate-200">
            <div class="h-16 px-3 sm:px-6 flex items-center gap-2 sm:gap-4">
                <button type="button" class="btn-icono lg:hidden" data-abrir-menu aria-label="Abrir menú">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>

                <div id="buscador" class="relative flex-1 max-w-xl">
                    <label for="buscador-global" class="sr-only">Buscar un bien</label>
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="search" id="buscador-global" data-url="{{ route('buscar') }}" autocomplete="off"
                           placeholder="Buscar un bien por nombre, código o categoría..."
                           class="campo pl-10 bg-slate-50 border-slate-200">
                    <div id="buscador-resultados" class="hidden absolute left-0 right-0 mt-2 tarjeta py-2 max-h-96 overflow-y-auto shadow-lg"></div>
                </div>

                <div id="notificaciones" class="relative">
                    <button type="button" id="boton-notificaciones" class="btn-icono relative" aria-haspopup="true" aria-expanded="false" aria-label="Notificaciones{{ $notificacionesSinLeer ? ": {$notificacionesSinLeer} sin leer" : '' }}">
                        <i class="fa-regular fa-bell text-lg"></i>
                        @if($notificacionesSinLeer > 0)
                            <span id="contador-notificaciones" class="absolute top-1.5 right-1.5 min-w-5 h-5 px-1 rounded-full bg-rose-600 text-white text-[11px] font-bold flex items-center justify-center">{{ $notificacionesSinLeer > 9 ? '9+' : $notificacionesSinLeer }}</span>
                        @endif
                    </button>
                    <div id="panel-notificaciones" class="hidden absolute right-0 mt-2 w-[calc(100vw-1.5rem)] max-w-sm tarjeta shadow-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                            <p class="text-sm font-bold text-slate-800">Notificaciones</p>
                            @if($notificacionesSinLeer > 0)
                                <button type="button" class="text-xs font-semibold text-marino-700 hover:underline min-h-0" data-leer-todas data-url="{{ route('notificaciones.leer-todas') }}">Marcar todas como leídas</button>
                            @endif
                        </div>
                        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
                            @forelse($notificaciones as $notificacion)
                                <a href="{{ $notificacion->data['url'] ?? '#' }}" data-notificacion data-marcar="{{ route('notificaciones.leer', $notificacion->id) }}"
                                   class="flex gap-3 px-4 py-3 hover:bg-slate-50 {{ $notificacion->read_at ? '' : 'bg-marino-50/60' }}" @unless($notificacion->read_at) data-no-leida @endunless>
                                    <span class="w-9 h-9 shrink-0 rounded-full bg-marino-100 text-marino-700 flex items-center justify-center"><i class="fa-solid {{ $notificacion->data['icono'] ?? 'fa-bell' }} text-sm"></i></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-slate-800">{{ $notificacion->data['titulo'] ?? 'Notificación' }}</span>
                                        <span class="block text-xs text-slate-500 truncate">{{ $notificacion->data['mensaje'] ?? '' }}</span>
                                        <span class="block text-[11px] text-slate-400 mt-0.5">{{ $notificacion->created_at->diffForHumans() }}</span>
                                    </span>
                                    @unless($notificacion->read_at)
                                        <span class="w-2 h-2 mt-2 rounded-full bg-rose-600 shrink-0" data-punto></span>
                                    @endunless
                                </a>
                            @empty
                                <p class="px-4 py-8 text-center text-sm text-slate-400"><i class="fa-regular fa-bell-slash text-2xl block mb-2"></i>No tiene notificaciones.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <p class="hidden md:block text-sm text-slate-500 whitespace-nowrap">Juliaca, {{ now()->translatedFormat('j \d\e F \d\e Y') }}</p>
            </div>
        </header>

        <main id="contenido" class="flex-1 p-4 sm:p-6 lg:p-8">
            @if($errors->any() && ! View::hasSection('sin_resumen_de_errores'))
                <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-800" role="alert">
                    <p class="text-sm font-bold flex items-center gap-2"><i class="fa-solid fa-circle-exclamation"></i> Revise los datos ingresados:</p>
                    <ul class="list-disc list-inside text-sm mt-1 space-y-0.5">
                        @foreach($errors->all() as $mensaje)
                            <li>{{ $mensaje }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="px-4 sm:px-8 py-4 text-xs text-slate-400 flex flex-wrap justify-between gap-2">
            <p>SIBIP · Sistema Integrado de Bienes Patrimoniales</p>
            <p>Colegio Adventista Túpac Amaru · Juliaca</p>
        </footer>
    </div>

    <script type="application/json" id="toasts-iniciales">@json($toasts)</script>
    @stack('scripts')
</body>
</html>
