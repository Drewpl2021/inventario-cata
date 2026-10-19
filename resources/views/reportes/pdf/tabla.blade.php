<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('reportes.pdf._estilos')
</head>
<body>
    @include('reportes.pdf._encabezado')

    @php
        $columnas = $reporte->columnas();
        $columnaValor = $reporte->columnaValor();
        $grupos = \App\Reportes\ExportadorReportes::agrupadas($reporte, $filas);
    @endphp

    <table class="datos">
        <thead>
            <tr>@foreach($columnas as [$etiqueta, $tipo])<th @if($tipo === 'moneda') class="derecha" @endif>{{ $etiqueta }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse($grupos as $grupo => $filasDelGrupo)
                @if($grupo !== '')
                    <tr class="grupo"><td colspan="{{ count($columnas) }}">{{ $grupo }}</td></tr>
                @endif
                @foreach($filasDelGrupo as $fila)
                    <tr>
                        @foreach($columnas as $clave => [$etiqueta, $tipo])
                            @php $valor = $fila[$clave] ?? null; @endphp
                            <td @class(['derecha' => $tipo === 'moneda', 'mono' => $clave === 'codigo'])>
                                @if($valor === null || $valor === '')
                                    —
                                @elseif($tipo === 'moneda')
                                    {{ number_format($valor, 2) }}
                                @elseif($tipo === 'fecha')
                                    {{ $valor->format('d/m/Y') }}
                                @elseif($tipo === 'fechahora')
                                    {{ $valor->format('d/m/Y H:i') }}
                                @else
                                    {{ $valor }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                @if($grupo !== '')
                    <tr class="subtotal">
                        <td colspan="{{ $columnaValor ? count($columnas) - 1 : count($columnas) }}">Subtotal {{ $grupo }}: {{ $totales['grupos'][$grupo]['cantidad'] }} {{ $reporte->unidad() }}</td>
                        @if($columnaValor)<td class="derecha">S/ {{ number_format($totales['grupos'][$grupo]['valor'], 2) }}</td>@endif
                    </tr>
                @endif
            @empty
                <tr><td colspan="{{ count($columnas) }}">No hay registros con estos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totales">
        <tr>
            <td>Total: <strong>{{ number_format($totales['cantidad']) }}</strong> {{ $reporte->unidad() }}</td>
            @if($totales['valor'] !== null)
                <td class="derecha">Valor de adquisición sumado: <strong>S/ {{ number_format($totales['valor'], 2) }}</strong></td>
            @endif
        </tr>
    </table>
</body>
</html>
