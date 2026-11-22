@extends('site.layout')

@section('title', 'Pakketten')
@section('description', 'Kies je pakket: Lokaal, Groei of Regio. Maandelijks, met een vast bedrag. Diamond, alles van A tot Z, komt binnenkort.')

@php
    $pakketten = [
        ['lokaal', 'Lokaal', 'Voor de zelfstandige ondernemer', '€ 349', ['2 samenwerkingen per maand', 'Content op TikTok en Instagram', 'Resultaten in je dashboard'], ''],
        ['groei', 'Groei', 'Voor de zaak die vaker gezien wil worden', '€ 795', ['5 samenwerkingen per maand', 'Vaste contactpersoon', 'Content ook voor je eigen kanalen', 'Maandrapport'], 'hl'],
        ['regio', 'Regio', 'Voor merken met meerdere vestigingen', '€ 1.695', ['12 samenwerkingen per maand', 'Meerdere vestigingen', 'Campagnes door heel Noord-Nederland', 'Advertenties met creator-content'], ''],
    ];
    $ja = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Ja"><path d="M4 12.5l5 5L20 6.5"/></svg>';
    $nee = '<span aria-label="Nee">–</span>';
    $rijen = [
        ['Samenwerkingen per maand', '2', '5', '12', 'Als Regio'],
        ['Content op TikTok en Instagram', 1, 1, 1, 1],
        ['Resultaten in je dashboard', 1, 1, 1, 1],
        ['Vaste contactpersoon', 0, 1, 1, 1],
        ['Meerdere vestigingen', 0, 0, 1, 1],
        ['Advertenties met creator-content', 0, 0, 1, 1],
        ['Posts voor je eigen kanalen, geschreven en gepland', 0, 0, 0, 1],
        ['Fotoshoot elk kwartaal', 0, 0, 0, 1],
        ['Website-onderhoud', 0, 0, 0, 1],
        ['Adverteren in Google (SEA)', 'Los', 'Los', 'Los', 'Los'],
        ['Prijs per maand, excl. btw', '€ 349', '€ 795', '€ 1.695', 'Op aanvraag'],
    ];
@endphp

@section('content')
<div class="phead">
    @include('site.partials.aurora')
    <div class="wrap">
        <section aria-labelledby="pk-kop">
            <p class="eb fade">Voor ondernemers in Noord-Nederland</p>
            <h1 id="pk-kop" class="fade" style="animation-delay:.1s">Kies je <span class="grad2">pakket.</span></h1>
            <p class="lead fade" style="animation-delay:.25s">Van een paar video's per maand tot je complete online zichtbaarheid. Elk pakket is maandelijks, met een vast bedrag.</p>
        </section>

        <section class="pk" aria-label="Pakketten">
            @foreach ($pakketten as [$sleutel, $naam, $voor, $prijs, $punten, $stijl])
                <article class="pcard {{ $stijl }} rv">
                    <h2>{{ $naam }} @if ($stijl === 'hl')<span class="tag v">Meest gekozen</span>@endif</h2>
                    <p class="for">{{ $voor }}</p>
                    <p class="price">{{ $prijs }}</p>
                    <p class="per">per maand, excl. btw</p>
                    <ul class="chk-list">@foreach ($punten as $punt)<li>{{ $punt }}</li>@endforeach</ul>
                    <a class="btn {{ $stijl === 'hl' ? 'w' : 'o' }}" href="{{ route('site.ondernemers', ['pakket' => $sleutel]) }}#formulier">Kies {{ $naam }}<span class="sr">: plan een kennismaking</span></a>
                </article>
            @endforeach
            <article class="pcard dm rv">
                <h2>Diamond <span class="tag">Binnenkort</span></h2>
                <p class="for">Alles van A tot Z, één partner</p>
                <p class="price sm">Prijs op aanvraag</p>
                <p class="per">maandelijks, minimaal 6 maanden</p>
                <ul class="chk-list"><li>Alles uit Regio</li><li>Posts voor je eigen kanalen</li><li>Fotoshoot elk kwartaal en website-onderhoud</li><li>Wekelijkse meting van je vindbaarheid</li></ul>
                <a class="btn" href="#diamond">Bekijk Diamond</a>
            </article>
        </section>
        <p class="pk-hint" aria-hidden="true">Veeg voor alle vier de pakketten →</p>
        <p class="fine">Prijzen per maand, excl. btw. Een samenwerking is één bezoek van een creator met een video op zijn eigen account. Diamond is een werknaam en komt binnenkort, de prijs is op aanvraag.</p>
    </div>
</div>

<section class="sec" id="diamond" aria-labelledby="dm-kop" style="background:var(--dark)">
    <div class="wrap">
        <div class="dm-wrap">
            <div>
                <p class="eb rv">Diamond: binnenkort, prijs op aanvraag</p>
                <h2 id="dm-kop" class="rv">Van A <span class="grad2">tot Z.</span></h2>
                <p class="lead rv">Eén partner voor je hele online zichtbaarheid. Algoritme, branding, content en digital marketing, door één team dat je zaak kent. Jij runt je zaak, wij zorgen dat je gezien wordt.</p>
                <p class="rv" style="margin-top:22px;color:#C8C5D8">Minimaal 6 maanden, met eenmalige startkosten voor merkscan, profielen inrichten en de eerste fotoshoot. Je krijgt een voorstel op maat.</p>
                <a class="btn rv" style="margin-top:26px" href="{{ route('site.ondernemers', ['pakket' => 'diamond']) }}#formulier">Vraag Diamond aan</a>
            </div>
            <div class="abcd rv" aria-hidden="true">
                <div><b>A</b><span>Algoritme</span></div><div><b>B</b><span>Branding</span></div>
                <div><b>C</b><span>Content</span></div><div><b>D</b><span>Digital marketing</span></div>
            </div>
        </div>

        <div class="dm-grid">
            @foreach ([
                ['A', 'Algoritme', 'Gevonden worden op Google, in Maps en door AI-assistenten.', ['Wekelijkse meting van je vindbaarheid, met acties', 'Google-profiel bijgehouden en compleet', 'Reviews bewaakt en beantwoord']],
                ['B', 'Branding', 'Eén herkenbaar gezicht, van je Google-foto\'s tot je Instagram.', ['Merkscan, huisstijlcheck en tone of voice bij de start', 'Professionele fotoshoot elk kwartaal', 'Al je profielen gelijk getrokken']],
                ['C', 'Content', 'Video\'s van creators en posts voor je eigen kanalen.', ['Alles uit Regio: samenwerkingen met creators', 'Posts voor je eigen kanalen, geschreven en gepland', 'Contentkalender en hergebruik als clips en stories']],
                ['D', 'Digital marketing', 'Advertenties en je website, zodat zichtbaarheid klanten oplevert.', ['Creator-content als advertentie, met de juiste licenties', 'Website-onderhoud: updates, beveiliging, back-ups', 'Live dashboard en maandrapport']],
            ] as [$letter, $titel, $uitleg, $punten])
                <article class="dm-card rv" data-l="{{ $letter }}">
                    <span class="l" aria-hidden="true">{{ $letter }}</span>
                    <h3>{{ $titel }}</h3>
                    <p>{{ $uitleg }}</p>
                    <ul class="chk-list">@foreach ($punten as $punt)<li>{{ $punt }}</li>@endforeach</ul>
                </article>
            @endforeach
        </div>

        <h3 class="rv" style="margin:56px 0 0;font-size:clamp(24px,2.4vw,34px);font-weight:900;text-transform:uppercase;letter-spacing:-.02em">Voor wie</h3>
        <div class="who">
            <div class="rv"><b>Zaken met meerdere vestigingen</b><p>Eén lijn in alles, van Leeuwarden tot Groningen.</p></div>
            <div class="rv d1"><b>Ondernemers zonder marketingmedewerker</b><p>Wij zijn je marketingafdeling, jij runt je zaak.</p></div>
            <div class="rv d2"><b>Wie liever één aanspreekpunt heeft</b><p>Geen bureau voor de website, één voor ads en één voor social.</p></div>
        </div>
        <div class="honest rv">
            <b>Eerlijk is eerlijk</b>
            <ul class="chk-list">
                <li>Ons team reageert ma tot za, 9 tot 18 uur, niet 24/7.</li>
                <li>Of een AI-assistent je noemt, kan niemand garanderen. We meten het en doen wat helpt.</li>
                <li>Je positie op Google hangt ook af van je concurrenten. We laten elke week zien waar je staat.</li>
                <li>Niet inbegrepen: je advertentiebudget, de bezoekwaarde bij creatorbezoeken en het bouwen van een nieuwe website.</li>
            </ul>
        </div>
    </div>
</section>

<section class="light sec" aria-labelledby="vgl-kop">
    <div class="wrap">
        <h2 id="vgl-kop" class="rv">Vergelijk</h2>
        <div class="tbl" tabindex="0" role="region" aria-labelledby="vgl-kop">
            <table class="cmpt">
                <thead><tr><th scope="col"><span class="sr">Onderdeel</span></th><th scope="col">Lokaal</th><th scope="col">Groei</th><th scope="col">Regio</th><th scope="col" class="d">Diamond</th></tr></thead>
                <tbody>
                    @foreach ($rijen as [$onderdeel, $l, $g, $r, $d])
                        <tr>
                            <th scope="row">{{ $onderdeel }}</th>
                            @foreach ([$l, $g, $r, $d] as $i => $cel)
                                <td class="{{ $i === 3 ? 'd' : '' }} {{ $cel === 1 ? 'y' : ($cel === 0 ? 'n' : '') }}">{!! $cel === 1 ? $ja : ($cel === 0 ? $nee : e($cel)) !!}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="fine">Prijzen per maand, excl. btw. Diamond is een werknaam en komt binnenkort; prijs en startkosten zijn op aanvraag. Adverteren in Google (SEA) is altijd los: een beheerfee plus je eigen advertentiebudget.</p>

        <div class="faq" aria-label="Vragen over pakketten">
            <details><summary>Kan ik later overstappen?</summary><p>Ja. Opwaarderen kan per de eerstvolgende maand. Lopende samenwerkingen lopen gewoon door.</p></details>
            <details><summary>Wat is het verschil tussen Regio en Diamond?</summary><p>Regio draait om veel samenwerkingen met creators. Diamond is je complete online zichtbaarheid: Google, je merk, content voor je eigen kanalen en advertenties, door één team.</p></details>
            <details><summary>Is de video herkenbaar als reclame?</summary><p>Ja, altijd. Elke post krijgt het label Betaalde samenwerking op Instagram of de schakelaar voor commerciële content op TikTok, plus de vermelding aan het begin van het bijschrift en in beeld. Wij controleren dat bij het concept en bij de live post.</p></details>
        </div>

        <div class="ctab rv">
            <div><h2>Twijfel je nog?</h2><p>Plan een kennismaking. We kijken samen welk pakket past, en of Diamond iets voor je is.</p></div>
            <a class="btn w" href="{{ route('site.ondernemers') }}#formulier">Plan een kennismaking</a>
        </div>
    </div>
</section>
@endsection
