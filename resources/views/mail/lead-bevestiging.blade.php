@extends('mail.layout', [
    'onderwerp' => $lead->isOndernemer() ? 'We hebben je aanvraag ontvangen' : 'Je aanmelding is binnen',
    'preheader' => $lead->isOndernemer() ? 'We nemen binnen twee werkdagen contact met je op.' : 'We bekijken je profiel en laten je weten wat de volgende stap is.',
    'aanhef' => 'Hoi '.\Illuminate\Support\Str::before($lead->name, ' ').',',
])

@section('alineas')
@if ($lead->isOndernemer())
<p style="margin:0 0 14px;">Bedankt voor je interesse in MEBRANDING. We hebben je aanvraag voor een kennismaking{{ $lead->company ? ' voor '.$lead->company : '' }} goed ontvangen.</p>
<p style="margin:0 0 14px;">Binnen twee werkdagen nemen we contact met je op om een moment te plannen. In dat gesprek kijken we samen welke creators en welk pakket bij jouw zaak passen.</p>
@else
<p style="margin:0 0 14px;">Bedankt voor je aanmelding als creator bij MEBRANDING. We hebben alles goed ontvangen.</p>
<p style="margin:0 0 14px;">Ons team bekijkt je profiel en je werk. Je hoort binnen vijf werkdagen van ons of we je kunnen toelaten, of dat we nog iets van je nodig hebben.</p>
@endif
<p style="margin:0 0 14px;">Heb je in de tussentijd een vraag? Beantwoord deze e-mail gewoon.</p>
@endsection
