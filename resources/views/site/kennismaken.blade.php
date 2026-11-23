@extends('site.layout')

@section('title', 'Kennismaken')

@section('content')
<section style="max-width: 1280px; margin: 0 auto; padding-left: 24px; padding-right: 24px; box-sizing: border-box; padding-top: clamp(56px, 7vw, 110px); padding-bottom: clamp(40px, 5vw, 72px); display: flex; flex-direction: column; gap: 20px">
<p style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 900; font-size: clamp(min(56px, calc((100vw - 48px) / 7.3)), 9vw, 132px); line-height: 0.9; letter-spacing: -4px; color: #2E2B48">kennismaken<span style="background: linear-gradient(90deg,#D9303E,#614ED9); -webkit-background-clip: text; background-clip: text; color: transparent">.</span></p>
<h1 style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: clamp(22px, 2.4vw, 32px); line-height: 1.25; color: #2E2B48; max-width: 820px">Laten we kijken wat creators voor jouw zaak kunnen doen.</h1>
<p style="margin: 0; font-family: 'Noto Sans',Arial,sans-serif; font-size: 17px; line-height: 1.7; color: #5E5A78; max-width: 720px">Laat je gegevens achter. We nemen binnen een werkdag contact op en plannen een kort gesprek, bij jou of online.</p></section><section style="max-width: 1280px; margin: 0 auto; padding-left: 24px; padding-right: 24px; box-sizing: border-box; padding-bottom: clamp(64px, 8vw, 110px)"><div style="border-top: 1px solid #E3E0EC; padding-top: clamp(32px, 4vw, 48px); display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: clamp(28px, 5vw, 80px); align-items: start">
<div style="display: flex; flex-direction: column; gap: 12px"><h2 style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 800; font-size: clamp(24px, 2.4vw, 32px); color: #2E2B48">Plan een kennismaking</h2><p style="margin: 0; font-family: 'Noto Sans',Arial,sans-serif; font-size: 15px; line-height: 1.7; color: #5E5A78">Na het gesprek krijg je een voorstel op maat. Je zit nergens aan vast.</p></div>
@if (session('aangemeld'))
@include('site.partials.bedankt', ['kop' => 'Dank je, we hebben je aanvraag ontvangen.', 'tekst' => 'Je krijgt een bevestiging per e-mail. We nemen binnen een werkdag contact op om een kort gesprek te plannen, bij jou of online.'])
@else
<form method="post" action="{{ route('site.kennismaken.versturen') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 18px">
@include('site.partials.begin')
@if (in_array(request('pakket', old('pakket')), ['lokaal', 'groei', 'regio'], true))<input type="hidden" name="pakket" value="{{ request('pakket', old('pakket')) }}">@endif
@include('site.partials.veld', ['naam' => 'naam', 'label' => 'Je naam', 'verplicht' => true, 'attrs' => 'autocomplete="name"'])
@include('site.partials.veld', ['naam' => 'bedrijfsnaam', 'label' => 'Naam van je zaak', 'verplicht' => true, 'attrs' => 'autocomplete="organization"'])
@include('site.partials.veld', ['naam' => 'email', 'label' => 'E-mailadres', 'type' => 'email', 'verplicht' => true, 'attrs' => 'autocomplete="email"'])
@include('site.partials.veld', ['naam' => 'telefoon', 'label' => 'Telefoonnummer', 'type' => 'tel', 'attrs' => 'autocomplete="tel"'])
@include('site.partials.veld', ['naam' => 'branche', 'label' => 'Soort zaak', 'type' => 'select', 'opties' => array_combine(\App\Http\Controllers\Site\LeadController::BRANCHES, \App\Http\Controllers\Site\LeadController::BRANCHES)])
@include('site.partials.veld', ['naam' => 'vestigingen', 'label' => 'Aantal vestigingen', 'type' => 'select', 'opties' => array_combine(\App\Http\Controllers\Site\LeadController::VESTIGINGEN, \App\Http\Controllers\Site\LeadController::VESTIGINGEN)])
@include('site.partials.veld', ['naam' => 'bericht', 'label' => 'Wat wil je laten zien?', 'type' => 'textarea', 'breed' => true, 'placeholder' => 'Bijvoorbeeld een nieuw gerecht, je terras of een overnachting'])
@include('site.partials.akkoord', ['knop' => 'Verstuur'])
</form>
@endif
</div></section>
@endsection
