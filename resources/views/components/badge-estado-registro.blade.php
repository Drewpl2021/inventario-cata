@props(['estado'])

<span {{ $attributes->class($estado === 'activo' ? 'badge-verde' : 'badge-gris') }}>{{ $estado === 'activo' ? 'Activo' : 'Inactivo' }}</span>
