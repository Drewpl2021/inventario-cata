{{--
    Páginas de error en español con la identidad del sistema y sin detalles técnicos.
    Estilos propios (no usa Vite): se muestran aunque falten los archivos compilados.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('titulo') · SIBIP</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        :root { --marino-900: #082c55; --marino-800: #0b3b70; --marino-600: #1d559c; --marino-200: #b9d0ec; --marino-50: #eef4fb; --texto: #334155; --suave: #64748b; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px 16px;
               background: var(--marino-50); color: var(--texto); font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif; }
        .marca { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; color: var(--marino-900); text-decoration: none; }
        .marca img { height: 48px; width: auto; }
        .marca strong { display: block; font-size: 20px; letter-spacing: .5px; }
        .marca span { font-size: 12px; color: var(--suave); }
        .tarjeta { width: 100%; max-width: 480px; background: #fff; border: 1px solid var(--marino-200); border-radius: 24px; padding: 32px 24px; text-align: center;
                   box-shadow: 0 10px 30px rgba(8, 44, 85, .08); }
        .codigo { display: inline-flex; align-items: center; justify-content: center; min-width: 88px; height: 44px; padding: 0 16px; border-radius: 999px;
                  background: var(--marino-50); color: var(--marino-800); font-weight: 800; font-size: 18px; letter-spacing: 1px; }
        h1 { margin: 20px 0 8px; font-size: 22px; color: var(--marino-900); }
        p { margin: 0 0 8px; font-size: 15px; line-height: 1.6; }
        .acciones { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 24px; }
        .boton { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 20px; border-radius: 12px; font-weight: 600;
                 font-size: 14px; text-decoration: none; border: 1px solid var(--marino-200); color: var(--marino-800); background: #fff; }
        .boton.primario { background: var(--marino-800); border-color: var(--marino-800); color: #fff; }
        .boton:focus-visible { outline: 3px solid var(--marino-200); outline-offset: 2px; }
        footer { margin-top: 24px; font-size: 12px; color: var(--suave); text-align: center; }
    </style>
</head>
<body>
    <a class="marca" href="{{ url('/') }}">
        <img src="{{ asset('images/cata_logo.png') }}" alt="">
        <span><strong>SIBIP</strong>Colegio Adventista Túpac Amaru</span>
    </a>

    <main class="tarjeta">
        <span class="codigo">@yield('codigo')</span>
        <h1>@yield('titulo')</h1>
        @yield('mensaje')

        <div class="acciones">
            @hasSection('acciones')
                @yield('acciones')
            @else
                @if(url()->previous() !== url()->current())
                    <a class="boton" href="{{ url()->previous() }}">Volver atrás</a>
                @endif
                <a class="boton primario" href="{{ url('/') }}">Ir al inicio</a>
            @endif
        </div>
    </main>

    <footer>Sistema Integrado de Bienes Patrimoniales · Juliaca</footer>
</body>
</html>
