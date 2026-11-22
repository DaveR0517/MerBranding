@extends('site.layout')

@section('title', 'Geen toegang')
@section('description', 'Je hebt geen toegang tot deze pagina.')

@section('content')
@include('errors.partials.fout', [
    'code' => 403,
    'label' => 'Foutcode 403',
    'regel1' => 'Hier mag je',
    'regel2' => 'niet komen',
    'tekst' => (in_array($exception->getMessage(), ['', 'This action is unauthorized.', 'Forbidden'], true) ? 'Je account heeft geen rechten voor deze pagina.' : $exception->getMessage()).' Denk je dat dit niet klopt? Vraag het je accountmanager of een beheerder van MEBRANDING.',
    'knoppen' => [['Naar mijn omgeving', route('start'), true], ['Naar de website', route('site.home'), false]],
    'onder' => 'Vragen? Mail <a href="mailto:'.e(config('mebranding.team_email')).'">'.e(config('mebranding.team_email')).'</a>.',
])
@endsection
