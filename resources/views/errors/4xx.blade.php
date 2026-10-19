@extends('errors.layout')

@section('codigo', $exception->getStatusCode())
@section('titulo', 'No se pudo completar la solicitud')

@section('mensaje')
    <p>La solicitud no es válida o ya no está disponible.</p>
    <p>Vuelva a la página anterior e inténtelo de nuevo.</p>
@endsection
