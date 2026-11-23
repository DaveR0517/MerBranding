{{--
    Fotovak: toont public/site/fotos/{$bestand} zodra die er staat, anders een merkvlak (geen lege grijze vlakken).
    $bestand, $alt, $hoogte (CSS), $radius (CSS, standaard 20px).
--}}
@php($radius ??= '20px')
@if (is_file(public_path('site/fotos/'.$bestand)))
<img src="/site/fotos/{{ $bestand }}" alt="{{ $alt }}" loading="lazy" style="display: block; width: 100%; height: {{ $hoogte }}; object-fit: cover; border-radius: {{ $radius }}">
@else
<div role="presentation" style="position: relative; overflow: hidden; height: {{ $hoogte }}; border-radius: {{ $radius }}; background: radial-gradient(120% 90% at 85% 10%, #614ED9 0%, rgba(97,78,217,0) 55%), radial-gradient(90% 80% at 10% 95%, #D9303E 0%, rgba(217,48,62,0) 55%), #2E2B48">
<img src="/brand/mebranding-icoon.svg" alt="" style="position: absolute; right: 8%; bottom: 10%; width: clamp(80px, 22%, 180px); opacity: 0.9">
</div>
@endif
