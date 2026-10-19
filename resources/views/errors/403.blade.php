@extends('errors.layout')

@section('codigo', '403')
@section('titulo', 'Acceso no permitido')

@section('mensaje')
    <p>Su cuenta no tiene permiso para ver esta página o realizar esta acción.</p>
    <p>Si cree que debería tenerlo, comuníquese con el Administrador del sistema.</p>
@endsection
