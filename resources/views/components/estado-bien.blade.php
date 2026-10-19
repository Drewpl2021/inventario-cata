@props(['estado'])

@php
    $clase = match ($estado) {
        'nuevo', 'bueno' => 'badge-verde',
        'regular' => 'badge-ambar',
        'mantenimiento' => 'badge-ambar',
        'malo', 'inoperativo' => 'badge-rojo',
        'dado_de_baja' => 'badge-gris',
        default => 'badge-gris',
    };
@endphp

<span {{ $attributes->class($clase) }}>{{ \App\Models\Bien::ESTADOS[$estado] ?? ucfirst((string) $estado) }}</span>
