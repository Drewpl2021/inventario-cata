@extends('errors.layout')

@section('codigo', '503')
@section('titulo', 'Sistema en mantenimiento')

@section('mensaje')
    <p>Estamos realizando tareas de mantenimiento para mejorar el sistema.</p>
    <p>Vuelva a ingresar en unos minutos. Gracias por su paciencia.</p>
@endsection

@section('acciones')
    <a class="boton primario" href="{{ url('/') }}">Reintentar</a>
@endsection
