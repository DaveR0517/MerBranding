@extends('mail.layout')

@section('alineas')
@foreach ($alineas as $alinea)
<p style="margin:0 0 14px;">{{ $alinea }}</p>
@endforeach
@endsection
