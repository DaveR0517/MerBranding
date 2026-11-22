@extends('site.layout')

@section('title', 'Privacyverklaring')
@section('description', 'Welke gegevens MEBRANDING opslaat als je een formulier op deze website invult, waarvoor en hoe je contact opneemt.')

@section('content')
<div class="phead">
    @include('site.partials.aurora')
    <div class="wrap">
        <p class="eb">Privacy</p>
        <h1>Privacy&shy;verklaring</h1>
        <p class="lead">Wat we bewaren als je een formulier op deze website invult, waarvoor, en hoe je ons bereikt.</p>
    </div>
</div>

<section class="legal sec">
    <div class="wrap in">
        <p class="concept" role="note"><b>Concept, versie 0.1.</b> De definitieve privacyverklaring volgt.</p>

        <h2>Wat we opslaan</h2>
        <p>Alleen wat je zelf invult in een van de twee formulieren, plus een paar technische gegevens.</p>
        <div class="tblw">
            <table>
                <thead><tr><th scope="col">Formulier</th><th scope="col">Gegevens</th></tr></thead>
                <tbody>
                    <tr><td>Kennismaking voor ondernemers</td><td>Naam, e-mailadres, telefoonnummer, bedrijfsnaam, plaats, branche, aantal vestigingen, het pakket dat je interesseert en je toelichting.</td></tr>
                    <tr><td>Aanmelding als creator</td><td>Naam, e-mailadres, telefoonnummer, woonplaats, geboortedatum, werkgebied, soort creator, je kanalen met gebruikersnaam en aantal volgers, links naar je werk, je niches en eventueel je certificaatnummer.</td></tr>
                    <tr><td>Bij beide</td><td>Het moment waarop je akkoord gaf en de versie van deze verklaring, je IP-adres en het type browser. Die bewaren we om misbruik en spam tegen te gaan.</td></tr>
                </tbody>
            </table>
        </div>

        <h2>Waarvoor we het gebruiken</h2>
        <ul>
            <li>Om contact met je op te nemen over je aanvraag of aanmelding.</li>
            <li>Om je een bevestiging per e-mail te sturen.</li>
            <li>Om als team je aanmelding als creator te beoordelen. Je geboortedatum gebruiken we om te controleren dat je 18 jaar of ouder bent.</li>
        </ul>
        <p>We verkopen je gegevens niet en gebruiken ze niet voor advertenties. Deze website gebruikt geen trackingcookies; zie de <a href="{{ route('site.cookies') }}">cookieverklaring</a>.</p>

        <h2>Hoe lang we het bewaren</h2>
        <p>Niet langer dan nodig voor je aanvraag of aanmelding. De precieze bewaartermijnen staan straks in de definitieve verklaring.</p>

        <h2>Jouw rechten en contact</h2>
        <p>Je mag je gegevens inzien, laten aanpassen of laten verwijderen. Mail daarvoor naar <a href="mailto:{{ config('mebranding.team_email') }}">{{ config('mebranding.team_email') }}</a>. Heb je een klacht over hoe we met je gegevens omgaan, dan kun je ook terecht bij de Autoriteit Persoonsgegevens.</p>
    </div>
</section>
@endsection
