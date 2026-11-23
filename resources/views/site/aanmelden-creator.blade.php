@extends('site.layout')

@section('title', 'Aanmelden als creator')

@section('content')
<section style="max-width: 1280px; margin: 0 auto; padding-left: 24px; padding-right: 24px; box-sizing: border-box; padding-top: clamp(56px, 7vw, 110px); padding-bottom: clamp(40px, 5vw, 72px); display: flex; flex-direction: column; gap: 20px">
<p style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 900; font-size: clamp(min(56px, calc((100vw - 48px) / 5.8)), 9vw, 132px); line-height: 0.9; letter-spacing: -4px; color: #2E2B48">aanmelden<span style="background: linear-gradient(90deg,#D9303E,#614ED9); -webkit-background-clip: text; background-clip: text; color: transparent">.</span></p>
<h1 style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: clamp(22px, 2.4vw, 32px); line-height: 1.25; color: #2E2B48; max-width: 820px">Word creator bij MEBRANDING.</h1>
<p style="margin: 0; font-family: 'Noto Sans',Arial,sans-serif; font-size: 17px; line-height: 1.7; color: #5E5A78; max-width: 720px">Ons team bekijkt elke aanmelding zelf, zodat de kwaliteit hoog blijft. Je hoort binnen een paar werkdagen van ons.</p></section><section style="max-width: 1280px; margin: 0 auto; padding-left: 24px; padding-right: 24px; box-sizing: border-box; padding-bottom: clamp(64px, 8vw, 110px)"><div class="formulier">
@include('site.partials.formulier-kaart', ['kop' => 'Zo gaat het verder', 'tekst' => 'Je bent 18 jaar of ouder. Voor Instagram heb je een professioneel account nodig; omzetten is gratis in de app.', 'stappen' => [
    ['Wij bekijken je aanmelding', 'Een teamlid kijkt naar je profiel, je accounts en je werk. Je hoort binnen een paar werkdagen van ons.'],
    ['Je krijgt je eigen omgeving', 'Na goedkeuring ontvang je een persoonlijke link. Daarmee kies je een wachtwoord en log je in.'],
    ['Je eerste opdracht', 'Kies opdrachten die bij je passen, plan je bezoek en maak je video.'],
]])
@if (session('aangemeld'))
@include('site.partials.bedankt', ['kop' => 'Dank je, je aanmelding is binnen.', 'tekst' => 'Je krijgt een bevestiging per e-mail. Ons team bekijkt je aanmelding zelf en je hoort binnen een paar werkdagen van ons.'])
@else
<form method="post" action="{{ route('site.aanmelden-creator.versturen') }}" class="formulier-velden">
@include('site.partials.begin')
<fieldset><legend>Over jou</legend>
@include('site.partials.veld', ['naam' => 'voornaam', 'label' => 'Voornaam', 'verplicht' => true, 'attrs' => 'autocomplete="given-name"'])
@include('site.partials.veld', ['naam' => 'achternaam', 'label' => 'Achternaam', 'verplicht' => true, 'attrs' => 'autocomplete="family-name"'])
@include('site.partials.veld', ['naam' => 'email', 'label' => 'E-mailadres', 'type' => 'email', 'verplicht' => true, 'breed' => true, 'attrs' => 'autocomplete="email"'])
@include('site.partials.veld', ['naam' => 'geboortedatum', 'label' => 'Geboortedatum', 'type' => 'date', 'verplicht' => true, 'attrs' => 'autocomplete="bday" max="'.now()->subYears(18)->toDateString().'"'])
@include('site.partials.veld', ['naam' => 'woonplaats', 'label' => 'Woonplaats', 'verplicht' => true, 'attrs' => 'autocomplete="address-level2"'])
</fieldset>
<fieldset><legend>Je kanalen</legend>
@include('site.partials.veld', ['naam' => 'instagram-naam', 'label' => 'Instagram', 'placeholder' => '@jouwnaam'])
@include('site.partials.veld', ['naam' => 'tiktok-naam', 'label' => 'TikTok', 'placeholder' => '@jouwnaam'])
@include('site.partials.veld', ['naam' => 'niche', 'label' => 'Wat voor creator ben je?', 'type' => 'select', 'breed' => true, 'opties' => array_map(fn ($keuze) => $keuze[0], \App\Http\Controllers\Site\LeadController::CREATORSOORTEN)])
</fieldset>
<fieldset><legend>Over je werk</legend>
@include('site.partials.veld', ['naam' => 'bericht', 'label' => 'Vertel iets over jezelf', 'type' => 'textarea', 'breed' => true, 'placeholder' => 'Wat maak je graag, en waarom past MEBRANDING bij jou?'])
</fieldset>
<div style="display: grid">@include('site.partials.akkoord', ['knop' => 'Aanmelden'])</div>
</form>
@endif
</div></section>
@endsection
