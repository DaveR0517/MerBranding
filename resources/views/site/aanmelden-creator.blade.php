@extends('site.layout')

@section('title', 'Aanmelden als creator')

@section('content')
<section style="max-width: 1280px; margin: 0 auto; padding-left: 24px; padding-right: 24px; box-sizing: border-box; padding-top: clamp(56px, 7vw, 110px); padding-bottom: clamp(40px, 5vw, 72px); display: flex; flex-direction: column; gap: 20px">
<p style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 900; font-size: clamp(min(56px, calc((100vw - 48px) / 5.8)), 9vw, 132px); line-height: 0.9; letter-spacing: -4px; color: #2E2B48">aanmelden<span style="background: linear-gradient(90deg,#D9303E,#614ED9); -webkit-background-clip: text; background-clip: text; color: transparent">.</span></p>
<h1 style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: clamp(22px, 2.4vw, 32px); line-height: 1.25; color: #2E2B48; max-width: 820px">Word creator bij MEBRANDING.</h1>
<p style="margin: 0; font-family: 'Noto Sans',Arial,sans-serif; font-size: 17px; line-height: 1.7; color: #5E5A78; max-width: 720px">Ons team bekijkt elke aanmelding zelf, zodat de kwaliteit hoog blijft. Je hoort binnen een paar werkdagen van ons.</p></section><section style="max-width: 1280px; margin: 0 auto; padding-left: 24px; padding-right: 24px; box-sizing: border-box; padding-bottom: clamp(64px, 8vw, 110px)"><div style="border-top: 1px solid #E3E0EC; padding-top: clamp(32px, 4vw, 48px); display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: clamp(28px, 5vw, 80px); align-items: start">
<div style="display: flex; flex-direction: column; gap: 12px"><h2 style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 800; font-size: clamp(24px, 2.4vw, 32px); color: #2E2B48">Je gegevens</h2><p style="margin: 0; font-family: 'Noto Sans',Arial,sans-serif; font-size: 15px; line-height: 1.7; color: #5E5A78">Voor Instagram heb je een professioneel account nodig. Omzetten is gratis in de app. Je bent 18 jaar of ouder.</p></div>
@if (session('aangemeld'))
@include('site.partials.bedankt', ['kop' => 'Dank je, je aanmelding is binnen.', 'tekst' => 'Je krijgt een bevestiging per e-mail. Ons team bekijkt je aanmelding zelf en je hoort binnen een paar werkdagen van ons.'])
@else
<form method="post" action="{{ route('site.aanmelden-creator.versturen') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 18px">
@include('site.partials.begin')
@include('site.partials.veld', ['naam' => 'voornaam', 'label' => 'Voornaam', 'verplicht' => true, 'attrs' => 'autocomplete="given-name"'])
@include('site.partials.veld', ['naam' => 'achternaam', 'label' => 'Achternaam', 'verplicht' => true, 'attrs' => 'autocomplete="family-name"'])
@include('site.partials.veld', ['naam' => 'email', 'label' => 'E-mailadres', 'type' => 'email', 'verplicht' => true, 'attrs' => 'autocomplete="email"'])
@include('site.partials.veld', ['naam' => 'geboortedatum', 'label' => 'Geboortedatum', 'type' => 'date', 'verplicht' => true, 'attrs' => 'autocomplete="bday" max="'.now()->subYears(18)->toDateString().'"'])
@include('site.partials.veld', ['naam' => 'instagram-naam', 'label' => 'Instagram', 'placeholder' => '@jouwnaam'])
@include('site.partials.veld', ['naam' => 'tiktok-naam', 'label' => 'TikTok', 'placeholder' => '@jouwnaam'])
@include('site.partials.veld', ['naam' => 'niche', 'label' => 'Wat voor creator ben je?', 'type' => 'select', 'opties' => array_map(fn ($keuze) => $keuze[0], \App\Http\Controllers\Site\LeadController::CREATORSOORTEN)])
@include('site.partials.veld', ['naam' => 'woonplaats', 'label' => 'Woonplaats', 'verplicht' => true, 'attrs' => 'autocomplete="address-level2"'])
@include('site.partials.veld', ['naam' => 'bericht', 'label' => 'Vertel iets over jezelf', 'type' => 'textarea', 'breed' => true, 'placeholder' => 'Wat maak je graag, en waarom past MEBRANDING bij jou?'])
@include('site.partials.akkoord', ['knop' => 'Aanmelden'])
</form>
@endif
</div></section>
@endsection
