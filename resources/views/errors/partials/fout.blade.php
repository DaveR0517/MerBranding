{{--
    Foutpagina in de stijl van de website, voor de website en het portaal.
    Gebruik: @include('errors.partials.fout', ['code' => 404, 'label' => 'Foutcode 404', 'regel1' => '...', 'regel2' => '...', 'tekst' => '...', 'knoppen' => [[tekst, url, hoofd?]], 'links' => [[titel, sub, url]], 'onder' => html])
--}}
<section aria-labelledby="fout-titel" style="max-width: 1280px; margin: 0 auto; padding-left: 24px; padding-right: 24px; box-sizing: border-box; padding-top: clamp(56px, 7vw, 110px); padding-bottom: clamp(64px, 8vw, 110px); display: flex; flex-direction: column; gap: 20px">
<p aria-hidden="true" style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 900; font-size: clamp(56px, 9vw, 132px); line-height: 0.9; letter-spacing: -4px; color: #2E2B48">{{ $code }}<span style="background: linear-gradient(90deg,#D9303E,#614ED9); -webkit-background-clip: text; background-clip: text; color: transparent">.</span></p>
<p style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 13px; letter-spacing: 1.5px; text-transform: uppercase; color: #614ED9">{{ $label }}</p>
<h1 id="fout-titel" style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: clamp(22px, 2.4vw, 32px); line-height: 1.25; color: #2E2B48; max-width: 820px">{{ $regel1 }} {{ $regel2 }}.</h1>
<p style="margin: 0; font-family: 'Noto Sans',Arial,sans-serif; font-size: 17px; line-height: 1.7; color: #5E5A78; max-width: 720px">{{ $tekst }}</p>
@if (! empty($knoppen))
<div style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 4px">
@foreach ($knoppen as [$knop, $url, $hoofd])
<a href="{{ $url }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 15px; text-decoration: none; border-radius: 999px; min-height: 54px; display: inline-flex; align-items: center; padding: 0 28px; {{ $hoofd ? 'color: #FCFBFE; background: #2E2B48; border: 2px solid #2E2B48' : 'color: #2E2B48; border: 2px solid #2E2B48' }}">{{ $knop }}</a>
@endforeach
</div>
@endif
@if (! empty($links))
<div style="border-top: 1px solid #E3E0EC; margin-top: 20px">
@foreach ($links as [$titel, $sub, $url])
<a href="{{ $url }}" style="display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 6px 20px; padding: 20px 0; border-bottom: 1px solid #E3E0EC; text-decoration: none"><span style="font-family: Montserrat,Arial,sans-serif; font-weight: 800; font-size: clamp(20px, 2vw, 26px); color: #2E2B48">{{ $titel }}</span><span style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 15px; color: #5E5A78">{{ $sub }} <span aria-hidden="true" style="color: #614ED9">→</span></span></a>
@endforeach
</div>
@endif
@isset($onder)
<div class="fout-onder">{!! $onder !!}</div>
@endisset
</section>
