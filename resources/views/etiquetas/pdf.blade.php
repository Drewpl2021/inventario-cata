<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        .etiqueta { width: {{ $config->anchoMm }}mm; height: {{ $config->altoMm }}mm; overflow: hidden; }
        .etiqueta + .etiqueta { page-break-before: always; }
        img { display: block; width: {{ $config->anchoMm }}mm; height: {{ $config->altoMm }}mm; }
    </style>
</head>
<body>
    @foreach($imagenes as $imagen)
        <div class="etiqueta"><img src="{{ $imagen }}" alt=""></div>
    @endforeach
</body>
</html>
