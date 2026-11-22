@extends('site.layout')

@section('title', 'Cookies')
@section('description', 'MEBRANDING gebruikt op deze website alleen functionele cookies. Geen tracking, geen advertentiecookies.')

@section('content')
<div class="phead">
    @include('site.partials.aurora')
    <div class="wrap">
        <p class="eb">Cookies</p>
        <h1>Cookies</h1>
        <p class="lead">Kort: we gebruiken alleen cookies die nodig zijn om de site en de formulieren te laten werken. Geen tracking.</p>
    </div>
</div>

<section class="legal sec">
    <div class="wrap in">
        <p class="concept" role="note"><b>Concept, versie 0.1.</b> De definitieve cookieverklaring volgt.</p>

        <h2>Welke cookies</h2>
        <div class="tblw">
            <table>
                <thead><tr><th scope="col">Cookie</th><th scope="col">Waarvoor</th><th scope="col">Hoe lang</th></tr></thead>
                <tbody>
                    <tr><td>{{ config('session.cookie') }}</td><td>Houdt je bezoek bij elkaar, zodat een formulier na versturen je bedankmelding of je foutmeldingen kan tonen.</td><td>{{ config('session.lifetime') }} minuten</td></tr>
                    <tr><td>XSRF-TOKEN</td><td>Beveiliging: controleert dat een formulier echt vanaf deze website wordt verstuurd.</td><td>{{ config('session.lifetime') }} minuten</td></tr>
                </tbody>
            </table>
        </div>

        <h2>Wat we niet doen</h2>
        <ul>
            <li>Geen statistiek- of analysecookies.</li>
            <li>Geen advertentie- of trackingcookies, ook niet van Instagram, TikTok, Google of Meta.</li>
            <li>Geen lettertypes of scripts van andere partijen: alles komt van onze eigen server.</li>
        </ul>
        <p>Omdat we alleen functionele cookies gebruiken, vragen we geen toestemming en tonen we geen cookiemelding. Gaat dat ooit veranderen, dan vragen we eerst je toestemming.</p>

        <h2>Vragen</h2>
        <p>Mail naar <a href="mailto:{{ config('mebranding.team_email') }}">{{ config('mebranding.team_email') }}</a>. Lees ook onze <a href="{{ route('site.privacy') }}">privacyverklaring</a>.</p>
    </div>
</section>
@endsection
