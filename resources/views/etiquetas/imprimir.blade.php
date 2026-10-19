<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir etiquetas - SIBIP</title>
    <style>
        /* Una etiqueta por página, del tamaño exacto y sin márgenes. */
        @page { size: {{ $config->anchoMm }}mm {{ $config->altoMm }}mm; margin: 0; }

        html, body { margin: 0; padding: 0; font-family: system-ui, sans-serif; }

        .etiqueta {
            width: {{ $config->anchoMm }}mm;
            height: {{ $config->altoMm }}mm;
            overflow: hidden;
            break-after: page;
            page-break-after: always;
        }
        .etiqueta:last-child { break-after: auto; page-break-after: auto; }
        .etiqueta img { display: block; width: 100%; height: 100%; image-rendering: pixelated; }

        @media screen {
            body { background: #e2e8f0; padding: 16px; }
            .barra { max-width: 760px; margin: 0 auto 16px; background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
            .barra h1 { margin: 0 0 4px; font-size: 18px; color: #0b3b70; }
            .barra p, .barra li { font-size: 13px; color: #475569; margin: 4px 0; }
            .barra button { margin-top: 8px; padding: 10px 18px; background: #0b3b70; color: #fff; border: 0; border-radius: 10px; font-weight: 700; cursor: pointer; }
            .hoja { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
            .etiqueta { background: #fff; outline: 1px dashed #94a3b8; }
        }
        @media print {
            .barra { display: none; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <h1>{{ count($etiquetas) }} {{ count($etiquetas) === 1 ? 'etiqueta' : 'etiquetas' }} · {{ $config->descripcion() }}</h1>
        <p>En el diálogo de impresión elija:</p>
        <ul>
            <li>Impresora: su impresora de etiquetas, con el papel de <strong>{{ $config->anchoMm }} × {{ $config->altoMm }} mm</strong>.</li>
            <li>Márgenes: <strong>Ninguno</strong> · Escala: <strong>100 %</strong> (desactive "Ajustar a la página").</li>
            <li>Desactive encabezados y pies de página.</li>
        </ul>
        <button type="button" id="imprimir">Imprimir</button>
    </div>

    <div class="hoja">
        @foreach($etiquetas as $etiqueta)
            <div class="etiqueta">
                <img src="data:image/png;base64,{{ base64_encode($etiqueta['png']) }}" alt="Etiqueta {{ $etiqueta['codigo'] }}">
            </div>
        @endforeach
    </div>

    <script nonce="{{ Vite::cspNonce() }}">
        // Abre el diálogo de impresión cuando las imágenes ya cargaron.
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
        document.getElementById('imprimir').addEventListener('click', () => window.print());
    </script>
</body>
</html>
