@extends('errors.layout')

@section('codigo', '429')
@section('titulo', 'Demasiadas solicitudes')

@section('mensaje')
    @php($espera = (int) ($exception->getHeaders()['Retry-After'] ?? 60))
    <p>Se hicieron demasiadas solicitudes en poco tiempo.</p>
    <p>Por seguridad, espere {{ $espera >= 60 ? intdiv($espera + 59, 60).' minuto(s)' : $espera.' segundos' }} y vuelva a intentarlo.</p>
@endsection
