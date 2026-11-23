@extends('site.layout')

@section('title', 'Even geduld')
@section('description', 'Te veel verzoeken in korte tijd.')

@section('content')
@include('errors.partials.fout', [
    'code' => 429,
    'label' => 'Even geduld',
    'regel1' => 'Even geduld',
    'regel2' => 'alsjeblieft',
    'tekst' => 'Je hebt in korte tijd vaak een formulier verstuurd. Probeer het over een uur opnieuw.',
    'knoppen' => [['Terug naar de homepage', route('site.home'), true]],
    'onder' => 'Haast? Mail ons op <a href="mailto:'.e(config('mebranding.team_email')).'">'.e(config('mebranding.team_email')).'</a>.',
])
@endsection
