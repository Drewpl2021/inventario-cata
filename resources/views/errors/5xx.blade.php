@extends('errors.layout')

@section('codigo', $exception->getStatusCode())
@section('titulo', 'Ocurrió un problema')

@section('mensaje')
    <p>El sistema no pudo completar la operación. El error quedó registrado para que el equipo técnico lo revise.</p>
    <p>Vuelva a intentarlo en unos minutos.</p>
@endsection
