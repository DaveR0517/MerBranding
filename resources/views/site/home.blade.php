@extends('site.layout')

@section('title', 'Lokaal gezien worden')
@section('description', 'Influencer marketing voor ondernemers in Noord-Nederland. Creators komen langs, maken een video en plaatsen die op TikTok en Instagram. MEBRANDING regelt alles.')
@section('body_class', 'swarm-on notes-on hoverplay')

@php
    // Kleursets van de nagebootste video's, zoals in het gekozen ontwerp.
    $kleur = [
        ['#E8A87C', '#C0392B', '#3A3659'], ['#9C8CFF', '#614ED9', '#1F1D33'], ['#F6D365', '#FDA085', '#D9303E'],
        ['#84FAB0', '#3A7BD5', '#3A3659'], ['#FBC2EB', '#A18CD1', '#614ED9'], ['#FFB88C', '#DE6262', '#3A3659'],
        ['#C2E9FB', '#A1C4FD', '#614ED9'], ['#FDCBF1', '#E6DEE9', '#9C3FA0'],
    ];
    $clip = fn (int $k, int $d, ?string $tag = null, ?string $tekst = null, array $extra = []) => [
        'a' => $kleur[$k][0], 'b' => $kleur[$k][1], 'c' => $kleur[$k][2], 'd' => $d, 'tag' => $tag, 'tekst' => $tekst,
    ] + $extra;

    $band = [
        [0, 9, 'Lunchroom', 'Beste koffie van Leeuwarden? Ik ging kijken.'],
        [1, 11, 'Hotel', 'Een nacht aan het wad, dit viel me op.'],
        [2, 13, 'Bakkerij', 'Om 6 uur al vers. Proeven!'],
        [3, 15, 'Museum', 'Verborgen parel in Groningen.'],
        [4, 17, 'Restaurant', 'Dit gerecht moet je proberen.'],
        [5, 9, 'Winkel', 'Lokaal shoppen, mijn top 3.'],
        [6, 11, 'IJssalon', 'De lekkerste bol van Sneek.'],
        [7, 13, 'Bloemist', 'Zo maak je een boeket in 30 sec.'],
    ];

    $branches = [
        'Restaurant' => [[0, 9, 'Dit gerecht moet je proberen.'], [1, 11, 'Chef laat zien hoe het gemaakt wordt.'], [2, 13, 'Date night tip in Groningen.']],
        'Lunchroom' => [[3, 15, 'Beste koffie van Leeuwarden? Ik ging kijken.'], [4, 17, 'Lunch onder de 15 euro, zo goed.'], [5, 9, 'Hier zit je echt heerlijk.']],
        'Bakkerij' => [[6, 11, 'Om 6 uur al vers. Proeven!'], [7, 13, 'De lekkerste bol van Sneek.'], [0, 15, 'Achter de schermen bij de bakker.']],
        'Hotel' => [[1, 17, 'Een nacht aan het wad, dit viel me op.'], [2, 9, 'Kamertour met uitzicht.'], [3, 11, 'Ontbijt op bed, zo ziet het eruit.']],
        'Museum' => [[4, 13, 'Verborgen parel in Groningen.'], [5, 15, 'Dit wist je nog niet over het noorden.'], [6, 17, 'Leuk uitje voor een regenachtige dag.']],
        'Winkel' => [[7, 9, 'Lokaal shoppen, mijn top 3.'], [0, 11, 'Nieuwe collectie binnen.'], [1, 13, 'Cadeautip uit Assen.']],
    ];

    $stappen = [
        ['Kies je abonnement', 'Een vast bedrag per maand. Je vertelt wat je wilt laten zien.', 'Abonnement gekozen'],
        ['Wij vinden de creator', 'Een creator die past bij jouw zaak en de mensen die je wilt bereiken.', 'Creator gekoppeld'],
        ['Het bezoek', 'De creator komt langs, ervaart je zaak en filmt ter plekke.', 'Op bezoek'],
        ['Live en meten', 'De video gaat live, herkenbaar als reclame met het label Betaalde samenwerking. Jij ziet in je dashboard wat hij oplevert.', 'Live'],
    ];
@endphp

@section('content')
<section class="hero" id="top">
    @include('site.partials.aurora')
    <div class="swarm" id="swarm" aria-hidden="true"></div>
    <div class="wrap"><div class="cols">
        <div class="left">
            <p class="eb fade" style="animation-delay:.05s;text-transform:none;letter-spacing:0;font-size:17px">Influencer marketing voor ondernemers in Noord-Nederland</p>
            <h1 aria-label="Lokaal gezien worden."><span class="line" aria-hidden="true"><span style="animation-delay:.1s">Lokaal</span></span><span class="line" aria-hidden="true"><span style="animation-delay:.22s">gezien</span></span><span class="line" aria-hidden="true"><span class="grad shine" style="animation-delay:.34s">worden.</span></span></h1>
            <p class="lead fade" style="animation-delay:.6s;color:#E4E2F0">Jouw onderneming verdient het om gezien te worden. Niet door iedereen, maar door de mensen die ertoe doen. Wij verbinden je met creatieve makers die weten hoe ze mensen inspireren op TikTok en Instagram.</p>
            <div class="tiles fade" style="animation-delay:.75s">
                <a class="tile" href="{{ route('site.ondernemers') }}"><small>Ik ben ondernemer</small><b>Word zichtbaar →</b></a>
                <a class="tile" href="{{ route('site.creators') }}"><small>Ik ben creator</small><b>Word MEBRANDING-creator →</b></a>
                <a class="tile" href="{{ route('site.pakketten') }}"><small>Ik wil weten wat het kost</small><b>Bekijk de pakketten →</b></a>
            </div>
        </div>
        <div class="right" id="right">
            @include('site.partials.vid', ['v' => $clip(1, 11, 'Hotel', 'Een nacht aan het wad, dit viel me op.', ['cls' => 'g2 sm', 'attrs' => 'data-d="14"'])])
            @include('site.partials.vid', ['v' => $clip(2, 13, 'Bakkerij', 'Om 6 uur al vers. Proeven!', ['cls' => 'g3 sm', 'attrs' => 'data-d="10"'])])
            @include('site.partials.vid', ['v' => $clip(0, 9, 'Lunchroom', 'Beste koffie van Leeuwarden? Ik ging kijken.', ['cls' => 'g1', 'side' => true, 'attrs' => 'data-d="26"'])])
            <div class="stat"><i aria-hidden="true"></i><b id="cnt" data-tel="12480">12.480</b><span>views in de<br>eerste week</span><span class="vb">voorbeeld</span></div>
            <div class="notes" id="notes" aria-hidden="true"></div>
        </div>
    </div></div>
</section>

<section class="band" aria-labelledby="band-kop">
    <div class="wrap"><div class="head"><h2 id="band-kop">Zo zien onze video's eruit</h2><span class="bhint hint" data-touch="Video's spelen af zodra ze in beeld zijn">Wijs een video aan om hem af te spelen</span></div></div>
    <p class="sr">Een rij voorbeeldvideo's van creators bij een lunchroom, hotel, bakkerij, museum, restaurant, winkel, ijssalon en bloemist.</p>
    <div class="track" aria-hidden="true">
        @foreach ([...$band, ...$band] as [$k, $d, $tag, $tekst])
            @include('site.partials.vid', ['v' => $clip($k, $d, $tag, $tekst, ['cls' => 'hv', 'side' => true])])
        @endforeach
    </div>
</section>

<section class="light cmp" id="werkwijze"><div class="wrap">
    <div class="story">
        <div class="shead">
            <div class="eb rv">Hoe het werkt</div>
            <h2 class="rv">Geen gedoe. <span style="color:var(--vio)">Wij regelen alles.</span></h2>
            <p class="lead rv">Vier stappen. <span class="hint" data-touch="Tik op een stap of kijk mee op de telefoon.">Klik een stap aan of kijk mee op de telefoon.</span></p>
        </div>
        <div class="steps">
            @foreach ($stappen as $i => [$titel, $uitleg])
                <button type="button" class="st @if ($i === 0) on @endif" data-i="{{ $i }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" style="font:inherit;text-align:left;color:inherit"><span class="n">0{{ $i + 1 }}</span><span class="t">{{ $titel }}</span><span class="p">{{ $uitleg }}</span><span class="bar"></span></button>
            @endforeach
        </div>
        <div class="sticky" aria-hidden="true"><div class="phone"><span class="lbl" id="plbl">Stap 1</span><div class="scr">
            @foreach ($stappen as $i => [, , $label])
                @include('site.partials.vid', ['v' => $clip([0, 1, 2, 3][$i], [9, 11, 13, 15][$i], $label, $band[$i][3], ['cls' => 'sv'.($i === 0 ? ' on' : ''), 'side' => true, 'attrs' => 'data-i="'.$i.'"'])])
            @endforeach
        </div></div></div>
    </div>
</div></section>

<section class="light cmp" style="padding-top:0"><div class="wrap bwrap">
    <div>
        <div class="eb rv">Voorbeelden per branche</div>
        <h2 class="rv">Jouw zaak. <span class="grad">Hun verhaal.</span></h2>
        <p class="lead rv"><span class="hint" data-touch="Zo kan het eruitzien voor jouw branche.">Kies je branche en zie hoe het eruit kan zien.</span> <span class="hint" data-touch="Swipe door de video's.">Wijs een video aan om hem af te spelen.</span></p>
        <div class="tabs" role="tablist" aria-label="Branche">
            @foreach (array_keys($branches) as $b => $naam)
                <button type="button" class="tab" role="tab" id="tab-{{ $b }}" aria-controls="br-{{ $b }}" aria-selected="{{ $b === 0 ? 'true' : 'false' }}" tabindex="{{ $b === 0 ? '0' : '-1' }}" data-b="{{ $b }}">{{ $naam }}</button>
            @endforeach
        </div>
        <p style="margin-top:16px;font-size:12px;color:var(--mute)">Impressie. Hier komen straks echte video's van onze creators.</p>
    </div>
    <div class="bgrid" id="bgrid">
        @foreach (array_values($branches) as $b => $clips)
            <div class="bgroup" role="tabpanel" id="br-{{ $b }}" aria-labelledby="tab-{{ $b }}" @if ($b !== 0) data-hidden @endif>
                @foreach ($clips as [$k, $d, $tekst])
                    <div class="bitem">@include('site.partials.vid', ['v' => $clip($k, $d, array_keys($branches)[$b], $tekst, ['cls' => 'hv', 'side' => true])])</div>
                @endforeach
            </div>
        @endforeach
    </div>
</div></section>

<section class="white cmp"><div class="wrap">
    <div class="thead">
        <div><div class="eb rv">Zo staat het online</div><h2 class="rv">Echt bereik, <span style="color:var(--vio)">eerlijk in beeld</span></h2></div>
        <p class="lead rv">Elke video komt op het account van de creator, voor de mensen die hem al volgen. Wij zorgen dat alles klopt.</p>
    </div>
    <div class="tgrid">
        <div class="tcard rv d1">@include('site.partials.vid', ['v' => $clip(0, 9, null, null, ['cls' => 'sm'])])<div class="txt"><span class="dur">Op het account van de creator</span><p class="q">De creator plaatst de video op zijn eigen TikTok of Instagram, in zijn eigen woorden.</p><small>Echte mensen, met een publiek dat echt langs kan komen.</small></div></div>
        <div class="tcard rv d2">@include('site.partials.vid', ['v' => $clip(1, 11, null, null, ['cls' => 'sm'])])<div class="txt"><span class="dur">Herkenbaar als reclame</span><p class="q">Elke post krijgt het label Betaalde samenwerking, ook bij een gratis bezoek.</p><small>Op Instagram het platformlabel, op TikTok de schakelaar voor commerciële content, plus de vermelding in het bijschrift en in beeld.</small></div></div>
        <div class="tcard rv d3">@include('site.partials.vid', ['v' => $clip(2, 13, null, null, ['cls' => 'sm'])])<div class="txt"><span class="dur">Gecontroleerd en gemeten</span><p class="q">Wij controleren het concept en de live post, en meten wat de video oplevert.</p><small>De cijfers zie je terug in je eigen dashboard.</small></div></div>
    </div>
</div></section>

<section class="counter"><div class="wrap"><b id="bigcnt" data-tel="14">14</b><span>dagen meten we elke video, vanaf het moment dat hij live staat</span></div></section>

<section class="split">
    <div class="sp">
        <div class="bgv" aria-hidden="true">
            @foreach (array_slice($band, 0, 6) as [$k, $d, $tag, $tekst])
                @include('site.partials.vid', ['v' => $clip($k, $d, $tag, $tekst, ['cls' => 'sm'])])
            @endforeach
        </div>
        <div class="c rv"><div class="eb">Voor creators</div><h2>Inspireer je volgers. <span class="grad">Word beloond.</span></h2><p style="color:#D9D6EA">Samenwerkingen met ondernemers in Noord-Nederland. Je krijgt een gratis bezoek, vanaf Platinum ook een vaste vergoeding. Je groeit van Bronze naar Elite.</p><a class="btn w" href="{{ route('site.creators') }}">Word MEBRANDING-creator →</a></div>
    </div>
    <div class="sp ac">
        <div class="bgv" aria-hidden="true">
            @foreach (array_slice([...$band, ...$band], 3, 6) as [$k, $d, $tag, $tekst])
                @include('site.partials.vid', ['v' => $clip($k, $d, $tag, $tekst, ['cls' => 'sm'])])
            @endforeach
        </div>
        <div class="c rv"><div class="eb" style="color:#fff">MEBRANDING Academy</div><h2>Leer het live, <span style="color:#FFD9DC">op locatie</span></h2><p style="color:#F0EDFF">Een dag les en oefenen op locatie, bijvoorbeeld in een restaurant. Met praktijkopdrachten en een certificaat. De eerste cursusdagen maken we binnenkort bekend.</p><a class="btn w" href="{{ route('site.creators') }}">Meer voor creators →</a></div>
    </div>
</section>

<section class="cta pad">
    <div class="coll" aria-hidden="true">
        @foreach ([...$band, ...$band, ...$band] as $i => [$k, $d, $tag, $tekst])
            @include('site.partials.vid', ['v' => $clip($k, [9, 11, 13, 15, 17][$i % 5], $tag, $tekst, ['cls' => 'sm'])])
        @endforeach
    </div>
    <div class="wrap">
        <h2 class="rv" lang="en">Your brand.<br><span class="grad">Their story.</span></h2>
        <p class="lead rv" lang="en" style="margin:22px auto 30px">Real creators. Real influence. More business.</p>
        <a class="btn rv" href="{{ route('site.ondernemers') }}">Plan een kennismaking →</a>
    </div>
</section>
@endsection
