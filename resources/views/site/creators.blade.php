@extends('site.layout')

@section('title', 'Word MEBRANDING-creator')
@section('description', 'Maak video\'s over de leukste zaken in Noord-Nederland en deel ze op TikTok en Instagram. Gratis bezoek, vanaf Platinum een vaste vergoeding. Meld je aan als creator.')

@php
    $soorten = [
        'influencer' => ['Influencer', 'Je post op je eigen TikTok of Instagram en bereikt je volgers in de regio.', '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c.8-3.6 3.2-5.5 6-5.5s5.2 1.9 6 5.5"/><path d="M17 5.5a3 3 0 0 1 0 5M19.5 3a6.5 6.5 0 0 1 0 10"/>'],
        'ugc' => ['UGC-maker', 'Je maakt video\'s die de ondernemer zelf gebruikt. Volgers maken niet uit.', '<rect x="2.5" y="6" width="13" height="12" rx="2"/><path d="M15.5 10.5l6-3.5v10l-6-3.5z"/>'],
        'beide' => ['Beide', 'Je post op je eigen account en maakt ook video\'s voor de ondernemer.', '<circle cx="7.5" cy="8" r="3"/><path d="M2.5 19c.6-3 2.6-4.8 5-4.8 1.6 0 3 .8 3.9 2.1"/><rect x="13" y="10" width="7" height="6" rx="1.2"/><path d="M20 12l2.5-1.5v5L20 14"/>'],
    ];
    $kanalen = ['tiktok' => 'TikTok', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'facebook' => 'Facebook', 'snapchat' => 'Snapchat'];
    $niches = ['eten' => 'Eten en drinken', 'lifestyle' => 'Lifestyle', 'uitjes' => 'Uitjes en cultuur', 'reizen' => 'Reizen en overnachten', 'mode' => 'Mode en winkelen', 'gezin' => 'Gezin', 'anders' => 'Anders'];
    $stapnamen = ['Soort creator', 'Over jou', 'Je kanalen', 'Je werk', 'Niches en academy', 'Controleren'];
    $soortKeuze = old('soort', 'beide');
    $nicheKeuze = old('niches', []);
    $maxDatum = now()->subYears(18)->toDateString();

    // Onderdeel van de wizard: koptekst en knoppen per stap.
    $stapKop = fn (int $n, string $titel, string $sub) => '<p class="k">Stap '.$n.' van 6</p><h3 id="wz-kop'.$n.'">'.e($titel).'</h3><p class="sub">'.e($sub).'</p>';
@endphp

@section('content')
<div class="phead">
    @include('site.partials.aurora')
    <div class="wrap chero">
        <section class="cl" aria-labelledby="creator-kop">
            <p class="eb fade">Voor creators die van content maken houden</p>
            <h1 id="creator-kop" class="fade" style="animation-delay:.1s">Word MEBRANDING-<span class="grad2">creator.</span></h1>
            <p class="lead fade" style="animation-delay:.25s">Maak video's over de leukste zaken in Noord-Nederland en deel ze met je volgers op TikTok en Instagram. Wij regelen de samenwerking, jij maakt de content.</p>
            <ul class="chk-list fade" style="animation-delay:.4s">
                <li>Samenwerkingen met ondernemers in Noord-Nederland</li>
                <li>Gratis bezoek of vaste vergoeding, afspraken vooraf duidelijk</li>
                <li>Groei van Bronze naar Elite op basis van je resultaten</li>
            </ul>
            <a class="btn fade" style="animation-delay:.5s" href="#aanmelden">Meld je aan →</a>
        </section>
        <aside class="levels fade" style="animation-delay:.35s" aria-labelledby="groei-kop">
            <p class="eb">Jouw groeipad</p>
            <h2 id="groei-kop">Van Bronze naar Elite</h2>
            <ol>
                <li><i style="background:#B8794A" aria-hidden="true"></i>Bronze<small>startniveau</small></li>
                <li><i style="background:#C4C3CF" aria-hidden="true"></i>Silver</li>
                <li><i style="background:#E0B23A" aria-hidden="true"></i>Gold</li>
                <li><i style="background:#9ED9E0" aria-hidden="true"></i>Platinum<small>met vergoeding</small></li>
                <li class="top"><i style="background:linear-gradient(135deg,#D9303E,#B67CFF)" aria-hidden="true"></i>Elite<small>topniveau</small></li>
            </ol>
            <p>Je score bestaat uit bereik, engagement, advertentieresultaat, klantoordeel en betrouwbaarheid. Nieuwe creators starten met een gemiddelde. Bij Bronze, Silver en Gold krijg je een gratis bezoek (eten, overnachting of entree), betaald door de ondernemer. Vanaf Platinum krijg je daarnaast een vaste vergoeding.</p>
        </aside>
    </div>
</div>

<div class="cpage sec">
    <div class="wrap">
        <aside class="acb rv" aria-labelledby="academy-kop">
            <div>
                <p class="eb">MEBRANDING Academy</p>
                <h2 id="academy-kop">Nog geen ervaring? Volg straks een cursusdag</h2>
                <p>Leer in één dag hoe je een sterke video maakt voor een lokale ondernemer, met praktijkopdrachten, feedback en een certificaat. Een certificaat is niet verplicht om je aan te melden.</p>
            </div>
            <span class="tag">Data volgen binnenkort</span>
        </aside>

        <div class="ckgrid">
            <section class="card" id="aanmelden" aria-labelledby="aanmeld-kop">
                @include('site.partials.bedankt', ['volgende' => [
                    ['Screening van je kanalen en werk', 'We bekijken je kanalen, je voorbeeldvideo\'s en je posts.'],
                    ['Kennismaking', 'Een kort gesprek over je werkgebied, je niches en hoe samenwerkingen verlopen.'],
                    ['Eerste uitnodiging', 'Je ontvangt je eerste uitnodiging voor een samenwerking bij een ondernemer.'],
                ]])
                <h2 id="aanmeld-kop">Aanmelden als creator</h2>
                <p class="intro">In 6 korte stappen. Velden met <span class="req">*</span> zijn verplicht. Je aanmelding wordt beoordeeld door het MEBRANDING-team.</p>

                @if ($errors->any())
                    <div class="errsum" role="alert">Er ontbreekt nog iets. Kijk de velden met een melding na.</div>
                @endif

                <div id="wizard">
                    <ol class="stepper" aria-label="Stappen van de aanmelding">
                        @foreach ($stapnamen as $n => $stapnaam)
                            <li><button type="button"><span class="c">{{ $n + 1 }}</span><span class="t">{{ $stapnaam }}</span><span class="sr"> (stap {{ $n + 1 }})</span></button></li>
                        @endforeach
                    </ol>
                    <div class="wbar" aria-hidden="true"><i></i></div>

                    <form method="POST" action="{{ route('site.creators.aanmelden') }}">
                        @csrf
                        @include('site.partials.honeypot')

                        {{-- Stap 1 --}}
                        <section class="wstep" aria-labelledby="wz-kop1">
                            {!! $stapKop(1, 'Wat voor creator ben je?', 'Kies wat bij je past. Je kunt dit later altijd aanpassen.') !!}
                            <fieldset class="grp">
                                <legend class="sr">Soort creator</legend>
                                <div class="rcards">
                                    @foreach ($soorten as $waarde => [$titel, $uitleg, $icoon])
                                        <label class="rcard">
                                            <input type="radio" name="soort" value="{{ $waarde }}" @checked($soortKeuze === $waarde)>
                                            <span class="ic2" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icoon !!}</svg></span>
                                            <b>{{ $titel }}</b>
                                            <small>{{ $uitleg }}</small>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <p class="tip">Je keuze bepaalt wat we in stap 4 vragen: voorbeeldvideo's voor UGC, goed lopende posts voor influencers, of allebei.</p>
                            <div class="wnav"><button type="button" class="wbtn" disabled>‹ Vorige</button><span class="pos">Stap 1 van 6</span><button type="button" class="wbtn p" data-naar="1">Volgende: over jou ›</button></div>
                        </section>

                        {{-- Stap 2 --}}
                        <section class="wstep" aria-labelledby="wz-kop2">
                            {!! $stapKop(2, 'Over jou', 'We gebruiken je gegevens alleen om samenwerkingen te vinden die bij je passen.') !!}
                            <div class="f">
                                @include('site.partials.veld', ['naam' => 'naam', 'label' => 'Naam', 'verplicht' => true, 'attrs' => 'autocomplete="name"'])
                                <div class="f2">
                                    @include('site.partials.veld', ['naam' => 'email', 'label' => 'E-mail', 'type' => 'email', 'verplicht' => true, 'attrs' => 'autocomplete="email" placeholder="naam@voorbeeld.nl"'])
                                    @include('site.partials.veld', ['naam' => 'telefoon', 'label' => 'Telefoon', 'type' => 'tel', 'verplicht' => true, 'attrs' => 'autocomplete="tel"'])
                                </div>
                                <div class="f2">
                                    @include('site.partials.veld', ['naam' => 'woonplaats', 'label' => 'Woonplaats', 'verplicht' => true, 'attrs' => 'autocomplete="address-level2"'])
                                    @include('site.partials.veld', ['naam' => 'geboortedatum', 'label' => 'Geboortedatum', 'type' => 'date', 'verplicht' => true, 'hint' => 'Je moet minimaal 18 jaar zijn.', 'attrs' => 'max="'.$maxDatum.'" autocomplete="bday"'])
                                </div>
                                <div class="fld">
                                    <label for="veld-straal">Werkgebied: hoe ver wil je reizen voor een bezoek?</label>
                                    <div class="range"><input id="veld-straal" type="range" name="straal" min="5" max="75" step="5" value="{{ old('straal', 25) }}" data-uit="straal-uit"><output id="straal-uit" for="veld-straal">{{ old('straal', 25) }} km</output></div>
                                </div>
                            </div>
                            <div class="wnav"><button type="button" class="wbtn" data-naar="0">‹ Vorige</button><span class="pos">Stap 2 van 6</span><button type="button" class="wbtn p" data-naar="2">Volgende: je kanalen ›</button></div>
                        </section>

                        {{-- Stap 3 --}}
                        <section class="wstep" aria-labelledby="wz-kop3">
                            {!! $stapKop(3, 'Je kanalen', 'Vul minimaal één kanaal in. Je accounts koppelen kan later in de creator-app; we plaatsen nooit iets namens jou.') !!}
                            <div>
                                @foreach ($kanalen as $sleutel => $kanaal)
                                    <div class="chan" role="group" aria-labelledby="kanaal-{{ $sleutel }}">
                                        <b id="kanaal-{{ $sleutel }}">{{ $kanaal }}</b>
                                        <input type="text" name="{{ $sleutel }}-naam" value="{{ old($sleutel.'-naam') }}" aria-label="Gebruikersnaam {{ $kanaal }}" placeholder="@gebruikersnaam">
                                        <input type="text" inputmode="numeric" name="{{ $sleutel }}-volgers" value="{{ old($sleutel.'-volgers') }}" aria-label="{{ $sleutel === 'youtube' ? 'Abonnees' : 'Volgers' }} {{ $kanaal }}" placeholder="{{ $sleutel === 'youtube' ? 'Abonnees' : 'Volgers' }}">
                                        <label class="chk"><input type="checkbox" name="{{ $sleutel }}-zakelijk" value="1" @checked(old($sleutel.'-zakelijk'))>Zakelijk of creatoraccount</label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="f" style="margin-top:18px">
                                @include('site.partials.veld', ['naam' => 'portfolio', 'label' => 'Eigen website of portfolio', 'type' => 'url', 'attrs' => 'placeholder="https://"'])
                            </div>
                            <p class="tip">Voor Instagram heb je een professioneel account nodig (zakelijk of creator), zodat we na het koppelen de cijfers van je samenwerkingen kunnen zien.</p>
                            <div class="wnav"><button type="button" class="wbtn" data-naar="1">‹ Vorige</button><span class="pos">Stap 3 van 6</span><button type="button" class="wbtn p" data-naar="3">Volgende: je werk ›</button></div>
                        </section>

                        {{-- Stap 4 --}}
                        <section class="wstep" aria-labelledby="wz-kop4">
                            {!! $stapKop(4, 'Laat je werk zien', 'Plak links naar je werk. UGC-makers: een showreel of drie voorbeeldvideo\'s. Influencers: twee posts die goed liepen.') !!}
                            <div class="f">
                                <fieldset class="grp f">
                                    <legend>UGC: showreel of 3 voorbeeldvideo's</legend>
                                    @include('site.partials.veld', ['naam' => 'showreel', 'label' => 'Link naar je showreel', 'type' => 'url', 'attrs' => 'placeholder="https://"'])
                                    @foreach ([1, 2, 3] as $n)
                                        @include('site.partials.veld', ['naam' => 'ugc-video'.$n, 'label' => 'Voorbeeldvideo '.$n, 'type' => 'url', 'attrs' => 'placeholder="https://"'])
                                    @endforeach
                                </fieldset>
                                <fieldset class="grp f">
                                    <legend>Influencer: 2 posts die goed liepen</legend>
                                    @foreach ([1, 2] as $n)
                                        @include('site.partials.veld', ['naam' => 'post'.$n, 'label' => 'Link naar post '.$n, 'type' => 'url', 'attrs' => 'placeholder="https://"'])
                                    @endforeach
                                </fieldset>
                            </div>
                            <div class="wnav"><button type="button" class="wbtn" data-naar="2">‹ Vorige</button><span class="pos">Stap 4 van 6</span><button type="button" class="wbtn p" data-naar="4">Volgende: niches ›</button></div>
                        </section>

                        {{-- Stap 5 --}}
                        <section class="wstep" aria-labelledby="wz-kop5">
                            {!! $stapKop(5, 'Niches en academy', 'Kies waar je graag content over maakt. Een certificaat is niet verplicht.') !!}
                            <div class="f">
                                <fieldset class="grp">
                                    <legend>Niches (kies er een of meer)</legend>
                                    <div class="chips">
                                        @foreach ($niches as $waarde => $label)
                                            <label><input type="checkbox" name="niches[]" value="{{ $waarde }}" @checked(in_array($waarde, (array) $nicheKeuze, true))>{{ $label }}</label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                @include('site.partials.veld', ['naam' => 'certificaat', 'label' => 'Academy-certificaat (optioneel)', 'hint' => 'Heb je een cursusdag gevolgd? Vul dan je certificaatnummer in.'])
                                <p class="tip"><b>Proefvideo.</b> UGC-makers krijgen na aanmelding een korte proefopdracht. We beoordelen beeld, geluid, montage en of je de briefing volgt.</p>
                            </div>
                            <div class="wnav"><button type="button" class="wbtn" data-naar="3">‹ Vorige</button><span class="pos">Stap 5 van 6</span><button type="button" class="wbtn p" data-naar="5">Volgende: controleren ›</button></div>
                        </section>

                        {{-- Stap 6 --}}
                        <section class="wstep" aria-labelledby="wz-kop6">
                            {!! $stapKop(6, 'Controleren en versturen', 'Controleer je gegevens. Klopt er iets niet? Kies Wijzigen om terug te gaan naar die stap.') !!}
                            <dl class="sumlist">
                                @foreach ([['soort', 'Soort creator', 0], ['contact', 'Naam en contact', 1], ['plaats', 'Woonplaats en werkgebied', 1], ['geboortedatum', 'Geboortedatum', 1], ['kanalen', 'Kanalen', 2], ['werk', 'Voorbeeldwerk', 3], ['niches', 'Niches', 4], ['certificaat', 'Academy-certificaat', 4]] as [$sleutel, $label, $stap])
                                    <div><dt>{{ $label }}</dt><dd data-sum="{{ $sleutel }}"></dd><button type="button" data-naar="{{ $stap }}">Wijzigen<span class="sr"> {{ strtolower($label) }}</span></button></div>
                                @endforeach
                            </dl>
                            <label class="chk">
                                <input type="checkbox" name="akkoord" value="1" required @checked(old('akkoord')) @error('akkoord') aria-invalid="true" aria-describedby="fout-akkoord" @enderror>
                                <span>Ik ga akkoord met de <a href="{{ route('site.privacy') }}">privacyverklaring</a>. <span class="req" aria-hidden="true">*</span></span>
                            </label>
                            @include('site.partials.fout', ['veld' => 'akkoord'])
                            <button type="submit" class="sbtn send" style="margin-top:18px">Aanmelding versturen ›</button>
                            <div class="wnav"><button type="button" class="wbtn" data-naar="4">‹ Vorige</button><span class="pos">Stap 6 van 6</span><span></span></div>
                            <div class="after-list">
                                <h4>Wat gebeurt er hierna</h4>
                                <ol>
                                <li><span aria-hidden="true">01</span><div><b>Screening van je kanalen en werk</b><small>We bekijken je kanalen, je voorbeeldvideo's en je posts, en laten je weten wat de volgende stap is.</small></div></li>
                                <li><span aria-hidden="true">02</span><div><b>Proefvideo (alleen UGC)</b><small>Je ontvangt een korte proefopdracht.</small></div></li>
                                <li><span aria-hidden="true">03</span><div><b>Kennismaking</b><small>Een kort gesprek over je werkgebied, je niches en hoe samenwerkingen verlopen.</small></div></li>
                                <li><span aria-hidden="true">04</span><div><b>Eerste uitnodiging</b><small>Je ontvangt je eerste uitnodiging voor een samenwerking bij een ondernemer. Weigeren mag altijd, zonder gevolgen voor je score.</small></div></li>
                                </ol>
                            </div>
                        </section>
                    </form>
                </div>
            </section>

            <aside class="why" aria-labelledby="waarom-kop">
                <h2 id="waarom-kop">Waarom MEBRANDING</h2>
                <ol>
                    <li><span aria-hidden="true">1</span><div><b>Samenwerkingen die passen</b><small>Bij ondernemers die passen bij jouw niches en je werkgebied.</small></div></li>
                    <li><span aria-hidden="true">2</span><div><b>Eerlijk en duidelijk</b><small>Vergoeding en afspraken staan vast voordat je een bezoek plant.</small></div></li>
                    <li><span aria-hidden="true">3</span><div><b>Groeien op resultaat</b><small>Je score stijgt met je resultaten, van Bronze tot Elite.</small></div></li>
                    <li><span aria-hidden="true">4</span><div><b>Netjes volgens de regels</b><small>Elke post is herkenbaar als reclame met het label Betaalde samenwerking. Wij helpen je daarbij.</small></div></li>
                </ol>
                <p>Vragen over aanmelden? Mail naar <a href="mailto:{{ config('mebranding.team_email') }}">{{ config('mebranding.team_email') }}</a>.</p>
            </aside>
        </div>
    </div>
</div>
@endsection
