@extends('site.layout')

@section('title', 'Word zichtbaar: plan een kennismaking')
@section('description', 'Voor ondernemers in Noord-Nederland. Creators die passen bij jouw zaak, wij regelen alles, voor een vast bedrag per maand. Plan een kennismaking.')

@php
    $pakketKeuze = old('abonnement', request('pakket', 'onbekend'));
    $branchekeuze = ['Horeca', 'Hotel', 'Museum of attractie', 'Winkel', 'Anders'];
@endphp

@section('content')
<div class="phead">
    @include('site.partials.aurora')
    <div class="wrap zgrid">
        <section class="zleft" aria-labelledby="zichtbaar-kop">
            <p class="eb fade">Voor ondernemers in Noord-Nederland</p>
            <h1 id="zichtbaar-kop" class="fade" style="animation-delay:.1s">Word <span class="grad2">zichtbaar.</span></h1>
            <p class="lead fade" style="animation-delay:.25s">Laat de mensen bij jou in de buurt zien wat jouw zaak bijzonder maakt. Plan een kennismaking, dan bespreken we wat bij jouw onderneming past.</p>

            <ol class="points fade" style="animation-delay:.4s">
                <li><span class="n" aria-hidden="true">01</span><div><b>Creators die passen</b><p>Creators die passen bij jouw zaak en weten hoe ze mensen inspireren. Echte mensen, met een publiek dat echt langs kan komen.</p></div></li>
                <li><span class="n" aria-hidden="true">02</span><div><b>Wij regelen alles</b><p>Wij zoeken de creator, plannen het bezoek, controleren de video en laten de resultaten zien in je dashboard.</p></div></li>
                <li><span class="n" aria-hidden="true">03</span><div><b>Vast bedrag per maand</b><p>Een abonnement dat past bij jouw onderneming. Geen verrassingen achteraf, je weet vooraf waar je aan toe bent.</p></div></li>
            </ol>

            <div class="note-box rv">
                <h2>Altijd herkenbaar als reclame</h2>
                <p>Ook als de creator alleen een gratis bezoek krijgt, is de video reclame. Daarom krijgt elke post het label <strong>Betaalde samenwerking</strong> op Instagram of de schakelaar voor commerciële content op TikTok. Aan het begin van het bijschrift staat "Advertentie" of "Betaalde samenwerking met" jouw bedrijf, en het staat ook in beeld.</p>
                <p>Wij controleren dat bij het concept én bij de live post. Zo ben jij gedekt, en de creator ook.</p>
            </div>

            <div class="note-box rv" style="border-top-color:var(--vio)">
                <h2>Wat er na je aanvraag gebeurt</h2>
                <ol class="next">
                    <li><span aria-hidden="true">1</span><div><b>We bellen je binnen 1 werkdag</b><small>Iemand van ons team stelt een paar korte vragen over je zaak.</small></div></li>
                    <li><span aria-hidden="true">2</span><div><b>Kennismaking in je zaak of per video</b><small>30 minuten. We laten het portaal zien en bespreken wat bij jou past.</small></div></li>
                    <li><span aria-hidden="true">3</span><div><b>Je krijgt een voorstel</b><small>Per e-mail, met abonnement en startdatum.</small></div></li>
                </ol>
            </div>
        </section>

        <section class="card fade" style="animation-delay:.25s" id="formulier" aria-labelledby="formulier-kop">
            @include('site.partials.bedankt', ['volgende' => [
                ['We bellen je binnen 1 werkdag', 'Iemand van ons team stelt een paar korte vragen over je zaak.'],
                ['Kennismaking in je zaak of per video', '30 minuten. We bespreken wat bij jou past.'],
                ['Je krijgt een voorstel', 'Per e-mail, met abonnement en startdatum.'],
            ]])
            <h2 id="formulier-kop">Plan een kennismaking</h2>
            <p class="intro">Vul je gegevens in. Velden met <span class="req">*</span> zijn verplicht.</p>

            @if ($errors->any())
                <div class="errsum" role="alert">Er ontbreekt nog iets. Kijk de velden met een melding na.</div>
            @endif

            <form class="f" method="POST" action="{{ route('site.ondernemers.aanmelden') }}">
                @csrf
                @include('site.partials.honeypot')

                @include('site.partials.veld', ['naam' => 'bedrijfsnaam', 'label' => 'Bedrijfsnaam', 'verplicht' => true, 'attrs' => 'autocomplete="organization"'])
                <div class="f2">
                    <label class="fld" for="veld-branche">
                        <span>Branche</span>
                        <select id="veld-branche" name="branche">
                            <option value="">Kies je branche</option>
                            @foreach ($branchekeuze as $optie)
                                <option @selected(old('branche') === $optie)>{{ $optie }}</option>
                            @endforeach
                        </select>
                    </label>
                    @include('site.partials.veld', ['naam' => 'plaats', 'label' => 'Plaats', 'attrs' => 'autocomplete="address-level2"'])
                </div>
                <div class="f2">
                    @include('site.partials.veld', ['naam' => 'naam', 'label' => 'Naam', 'verplicht' => true, 'attrs' => 'autocomplete="name"'])
                    @include('site.partials.veld', ['naam' => 'vestigingen', 'label' => 'Aantal vestigingen', 'type' => 'number', 'standaard' => '1', 'attrs' => 'min="1" max="999" inputmode="numeric"'])
                </div>
                <div class="f2">
                    @include('site.partials.veld', ['naam' => 'email', 'label' => 'E-mail', 'type' => 'email', 'verplicht' => true, 'attrs' => 'autocomplete="email" placeholder="naam@bedrijf.nl"'])
                    @include('site.partials.veld', ['naam' => 'telefoon', 'label' => 'Telefoon', 'type' => 'tel', 'attrs' => 'autocomplete="tel"'])
                </div>

                <fieldset class="grp">
                    <legend>Welk pakket interesseert je?</legend>
                    <div class="chips">
                        @foreach (['lokaal' => 'Lokaal', 'groei' => 'Groei', 'regio' => 'Regio', 'diamond' => 'Diamond', 'onbekend' => 'Weet ik nog niet'] as $waarde => $label)
                            <label><input type="radio" name="abonnement" value="{{ $waarde }}" @checked($pakketKeuze === $waarde)>{{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>

                @include('site.partials.veld', ['naam' => 'bericht', 'label' => 'Toelichting', 'type' => 'textarea', 'attrs' => 'placeholder="Vertel kort over je zaak en wat je wilt bereiken."'])

                <label class="chk">
                    <input type="checkbox" name="akkoord" value="1" required @checked(old('akkoord')) @error('akkoord') aria-invalid="true" aria-describedby="fout-akkoord" @enderror>
                    <span>Ik ga akkoord met de <a href="{{ route('site.privacy') }}">privacyverklaring</a>. <span class="req" aria-hidden="true">*</span></span>
                </label>
                @include('site.partials.fout', ['veld' => 'akkoord'])

                <button type="submit" class="sbtn">Plan een kennismaking</button>
                <p class="after">We nemen binnen 1 werkdag contact op.</p>
                <div class="later"><span class="tag">Later</span><span><b>Direct starten?</b> Binnenkort kies je hier zelf je pakket, maak je een account en start je meteen, zonder gesprek.</span></div>
            </form>
        </section>
    </div>
</div>
@endsection
