<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('reportes.pdf._estilos')
    <style>
        .acta { page-break-after: always; }
        .acta:last-child { page-break-after: auto; }
        .acta h2 { font-size: 14px; text-align: center; color: #0b3b70; margin: 0 0 8px; letter-spacing: 1px; }
        .acta p.texto { font-size: 9.5px; line-height: 1.5; text-align: justify; margin: 0 0 8px; }
        table.firmas { width: 100%; margin-top: 40px; }
        table.firmas td { width: 50%; text-align: center; font-size: 9px; padding: 0 20px; vertical-align: top; }
        .linea { border-top: 1px solid #1e293b; padding-top: 4px; margin-top: 45px; }
    </style>
</head>
<body>
    @include('reportes.pdf._encabezado', ['titulo' => 'Acta de asignación de bienes'])

    @php
        $grupos = \App\Reportes\ExportadorReportes::agrupadas($reporte, $filas);
    @endphp

    @forelse($grupos as $grupo => $filasDelGrupo)
        @php
            $responsable = $responsables[$filasDelGrupo->first()['_responsable_id'] ?? 0] ?? null;
        @endphp
        <section class="acta">
            <h2>ACTA DE ASIGNACIÓN DE BIENES</h2>
            <p class="texto">
                En la ciudad de Juliaca, a los {{ $generado->day }} días del mes de {{ $generado->translatedFormat('F') }} de {{ $generado->year }}, la Administración Patrimonial del
                Colegio Adventista Túpac Amaru entrega en custodia a
                @php
                    $datosResponsable = collect([
                        $responsable?->dni ? "identificado(a) con DNI N.° {$responsable->dni}" : null,
                        $responsable?->cargo,
                        $responsable?->area ? "del área {$responsable->area->nombre} ({$responsable->sede?->nombre})" : null,
                    ])->filter()->join(', ');
                @endphp
                <strong>{{ $responsable?->nombre_completo ?? $grupo }}</strong>{{ $datosResponsable ? ', '.$datosResponsable : '' }},
                los bienes patrimoniales detallados a continuación. El responsable se compromete a velar por su buen uso y conservación, y a comunicar
                cualquier pérdida, daño o traslado.
            </p>

            <table class="datos">
                <thead>
                    <tr><th>N.°</th><th>Código</th><th>Bien</th><th>Categoría</th><th>Ubicación</th><th>Estado</th><th class="derecha">Valor (S/)</th></tr>
                </thead>
                <tbody>
                    @foreach($filasDelGrupo->values() as $i => $fila)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="mono">{{ $fila['codigo'] }}</td>
                            <td>{{ $fila['nombre'] }}</td>
                            <td>{{ $fila['categoria'] }}</td>
                            <td>{{ $fila['sede'] }}{{ $fila['area'] ? ' / '.$fila['area'] : '' }}</td>
                            <td>{{ $fila['estado'] }}</td>
                            <td class="derecha">{{ $fila['valor'] !== null ? number_format($fila['valor'], 2) : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal">
                        <td colspan="6">Total: {{ $filasDelGrupo->count() }} bienes</td>
                        <td class="derecha">S/ {{ number_format($totales['grupos'][$grupo]['valor'] ?? 0, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="firmas">
                <tr>
                    <td><div class="linea">Entrega<br>Administración Patrimonial</div></td>
                    <td><div class="linea">Recibe conforme<br>{{ $responsable?->nombre_completo ?? $grupo }}@if($responsable?->dni)<br>DNI {{ $responsable->dni }}@endif</div></td>
                </tr>
            </table>
        </section>
    @empty
        <p>No hay bienes asignados con estos filtros.</p>
    @endforelse
</body>
</html>
