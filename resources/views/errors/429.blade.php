@extends('site.layout')

@section('title', 'Even geduld')
@section('description', 'Te veel verzoeken in korte tijd.')

@section('content')
<section style="min-height:60vh;display:grid;place-items:center;padding:96px 16px;text-align:center">
    <div style="max-width:520px">
        <h1 style="font-family:'Montserrat Variable',sans-serif;font-weight:800;font-size:clamp(28px,6vw,44px);margin:0 0 16px">Even geduld</h1>
        <p style="margin:0 0 24px;line-height:1.6">Je hebt in korte tijd vaak een formulier verstuurd. Probeer het over een uur opnieuw, of mail ons op <a href="mailto:{{ config('mebranding.team_email') }}">{{ config('mebranding.team_email') }}</a>.</p>
        <a href="{{ route('site.home') }}">Terug naar de homepage</a>
    </div>
</section>
@endsection
