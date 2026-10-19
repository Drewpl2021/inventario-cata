{{-- Encabezado y pie fijos de cada página. El número de página lo agrega ExportadorReportes. --}}
<header>
    <img src="{{ public_path('images/cata_logo.png') }}" alt="">
    <p class="colegio">Colegio Adventista Túpac Amaru · SIBIP</p>
    <p class="titulo">{{ $titulo ?? $reporte->titulo() }}</p>
    <p class="meta">
        Filtros: {{ collect($filtrosLegibles)->map(fn ($v, $k) => "{$k}: {$v}")->join(' · ') ?: 'ninguno' }}
        &nbsp;|&nbsp; Generado el {{ $generado->format('d/m/Y H:i') }} (hora de Lima) por {{ $generadoPor }}
    </p>
</header>
<footer>Sistema Integrado de Bienes Patrimoniales · Juliaca</footer>
