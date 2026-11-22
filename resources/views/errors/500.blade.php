@extends('site.layout')

@section('title', 'Er ging iets mis')
@section('description', 'Er ging iets mis aan onze kant.')

@section('content')
@include('errors.partials.fout', [
    'code' => 500,
    'label' => 'Foutcode 500',
    'regel1' => 'Er ging iets mis',
    'regel2' => 'aan onze kant',
    'tekst' => 'Dit ligt niet aan jou. De fout staat in ons foutenlogboek en we zoeken het uit. Probeer het over een paar minuten opnieuw. Wat je net invulde, is mogelijk niet opgeslagen.',
    'knoppen' => [['Opnieuw proberen', url()->current(), true], ['Naar de website', route('site.home'), false]],
    'onder' => 'Tijdstip: <b>'.e(now()->timezone('Europe/Amsterdam')->translatedFormat('D j M Y, H:i')).'</b>. Noem het tijdstip als je contact opneemt: <a href="mailto:'.e(config('mebranding.team_email')).'?subject='.rawurlencode('Foutmelding '.now()->timezone('Europe/Amsterdam')->format('d-m-Y H:i')).'">meld het probleem</a>.',
])
@endsection
