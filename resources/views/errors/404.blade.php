@extends('site.layout')

@section('title', 'Pagina niet gevonden')
@section('description', 'Deze pagina bestaat niet (meer).')

@section('content')
@include('errors.partials.fout', [
    'code' => 404,
    'label' => 'Foutcode 404',
    'regel1' => 'Deze pagina',
    'regel2' => 'bestaat niet (meer)',
    'tekst' => 'De link klopt niet of de pagina is verplaatst. Geen zorgen: hieronder vind je de meest bezochte plekken, of log direct in op je eigen omgeving.',
    'knoppen' => [['Naar de website', route('site.home'), true], ['Inloggen', route('login'), false]],
    'links' => [
        ['Pakketten', 'Lokaal, Groei en Regio', route('site.pakketten')],
        ['Word zichtbaar', 'Aanmelden als ondernemer', route('site.ondernemers')],
        ['Word creator', "Video's maken voor lokale zaken", route('site.creators')],
    ],
    'onder' => 'Gezocht: <code>'.e(request()->getHost().'/'.ltrim(request()->path(), '/')).'</code><br>Klopt er iets niet? <a href="mailto:'.e(config('mebranding.team_email')).'">Laat het ons weten</a>.',
])
@endsection
