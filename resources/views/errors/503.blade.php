@extends('site.layout')

@section('title', 'Onderhoud')
@section('description', 'MEBRANDING is even in onderhoud.')

@section('content')
@include('errors.partials.fout', [
    'code' => 503,
    'label' => 'Even geduld',
    'regel1' => 'We zijn even',
    'regel2' => 'aan het onderhouden',
    'tekst' => 'We maken MEBRANDING sneller en beter. Inloggen en de portalen zijn tijdelijk niet beschikbaar. Video\'s die live staan blijven online en geplande bezoeken gaan gewoon door. Probeer het straks opnieuw.',
    'knoppen' => [['Opnieuw proberen', url()->current(), true]],
    'onder' => 'Dringend? Mail <a href="mailto:'.e(config('mebranding.team_email')).'">'.e(config('mebranding.team_email')).'</a>. Uploads, berichten en goedkeuren kunnen weer zodra we terug zijn.',
])
@endsection
