<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        .encabezado { border-bottom: 2px solid #0b3b70; padding-bottom: 6px; margin-bottom: 10px; }
        .encabezado img { height: 38px; float: left; margin-right: 10px; }
        .encabezado h1 { font-size: 14px; margin: 0; color: #0b3b70; }
        .encabezado p { margin: 1px 0; color: #475569; }
        .totales { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
        .totales td { border: 1px solid #cbd5e1; padding: 5px; text-align: center; }
        .totales strong { display: block; font-size: 13px; }
        table.detalle { width: 100%; border-collapse: collapse; }
        table.detalle th { background: #0b3b70; color: #fff; padding: 4px; text-align: left; font-size: 8px; }
        table.detalle td { border-bottom: 1px solid #e2e8f0; padding: 3px 4px; }
        .pie { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7px; color: #94a3b8; text-align: right; }
    </style>
</head>
<body>
    <div class="encabezado">
        <img src="{{ public_path('images/cata_logo.png') }}" alt="">
        <h1>Resultado de toma de inventario · {{ $inventario->codigo }}</h1>
        <p>Colegio Adventista Túpac Amaru · SIBIP</p>
        <p>{{ $inventario->nombre }} — {{ $inventario->ambito() }}</p>
        <p>
            Del {{ $inventario->fecha_inicio->format('d/m/Y') }}{{ $inventario->fecha_fin ? ' al '.$inventario->fecha_fin->format('d/m/Y') : '' }}
            · {{ $inventario->estaAbierto() ? 'Campaña ABIERTA (resultados provisionales)' : 'Cerrada el '.$inventario->cerrado_at->format('d/m/Y H:i').' por '.$inventario->cerradoPor?->nombre_completo }}
        </p>
        <div style="clear: both;"></div>
    </div>

    <table class="totales">
        <tr>
            <td><strong>{{ $resumen['porcentaje'] }} %</strong>Avance ({{ $resumen['escaneados'] }}/{{ $resumen['esperados'] }})</td>
            <td><strong>{{ $resumen['encontrado'] }}</strong>Encontrados</td>
            <td><strong>{{ $resumen['faltante'] }}</strong>Faltantes</td>
            <td><strong>{{ $resumen['fuera_de_ubicacion'] }}</strong>Fuera de ubicación</td>
            <td><strong>{{ $resumen['sobrante_no_registrado'] }}</strong>Sobrantes</td>
            <td><strong>{{ $resumen['dado_de_baja'] }}</strong>De baja encontrados</td>
        </tr>
    </table>

    <table class="detalle">
        <thead>
            <tr>
                <th>Resultado</th><th>Código</th><th>Bien</th><th>Ubicación registrada</th><th>Contado en</th><th>Estado</th><th>Escaneado por</th><th>Fecha y hora</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $fila)
                <tr>
                    @foreach($fila as $celda)
                        <td>{{ $celda }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pie">Generado el {{ $generado->format('d/m/Y H:i') }} (hora de Lima)</div>
</body>
</html>
