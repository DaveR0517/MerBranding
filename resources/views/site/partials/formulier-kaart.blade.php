{{-- Kaart naast een websiteformulier: wat er na het versturen gebeurt. $kop, $tekst, $stappen (lijst van [titel, uitleg]). --}}
<aside class="formulier-kaart" aria-labelledby="formulier-kaart-kop">
<h2 id="formulier-kaart-kop">{{ $kop }}</h2>
<p>{{ $tekst }}</p>
<ol>
@foreach ($stappen as $i => [$titel, $uitleg])
<li><span class="stap" aria-hidden="true">{{ $i + 1 }}</span><span><b>{{ $titel }}</b>{{ $uitleg }}</span></li>
@endforeach
</ol>
</aside>
