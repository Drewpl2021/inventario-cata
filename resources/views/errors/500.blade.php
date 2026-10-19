@extends('errors.layout')

@section('codigo', '500')
@section('titulo', 'Ocurrió un problema')

@section('mensaje')
    <p>El sistema no pudo completar la operación. El error quedó registrado para que el equipo técnico lo revise.</p>
    <p>Vuelva a intentarlo en unos minutos. Si el problema continúa, avise al Administrador indicando qué estaba haciendo.</p>
@endsection
