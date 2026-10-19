@extends('errors.layout')

@section('codigo', '419')
@section('titulo', 'La página expiró')

@section('mensaje')
    <p>Pasó demasiado tiempo desde que abrió el formulario, o su sesión se cerró por inactividad.</p>
    <p>Vuelva a la página anterior, recárguela y envíe los datos otra vez.</p>
@endsection

@section('acciones')
    <a class="boton" href="{{ url()->previous() }}">Volver y recargar</a>
    <a class="boton primario" href="{{ route('login') }}">Iniciar sesión</a>
@endsection
