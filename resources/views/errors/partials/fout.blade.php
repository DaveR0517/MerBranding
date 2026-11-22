{{--
    Foutpagina in huisstijl, voor de website en het portaal (ontwerpen Fout404, Fout500, Onderhoud).
    Gebruik: @include('errors.partials.fout', ['code' => 404, 'label' => 'Foutcode 404', 'regel1' => '...', 'regel2' => '...', 'tekst' => '...', 'knoppen' => [[tekst, url, hoofd?]], 'links' => [[titel, sub, url]], 'onder' => html])
--}}
<section class="fout" aria-labelledby="fout-titel">
    <span class="fout-code" aria-hidden="true">{{ $code }}</span>
    <div class="wrap fout-wrap">
        <p class="eb">{{ $label }}</p>
        <h1 id="fout-titel">{{ $regel1 }}<span>{{ $regel2 }}</span></h1>
        <p class="fout-tekst">{{ $tekst }}</p>
        @if (! empty($knoppen))
            <p class="fout-knoppen">
                @foreach ($knoppen as [$knop, $url, $hoofd])
                    <a class="btn{{ $hoofd ? '' : ' fout-rand' }}" href="{{ $url }}">{{ $knop }}</a>
                @endforeach
            </p>
        @endif
        @if (! empty($links))
            <ul class="fout-links">
                @foreach ($links as [$titel, $sub, $url])
                    <li><a href="{{ $url }}"><b>{{ $titel }}</b><small>{{ $sub }}</small></a></li>
                @endforeach
            </ul>
        @endif
        @isset($onder)
            <div class="fout-onder">{!! $onder !!}</div>
        @endisset
    </div>
</section>
<style>
    .fout{position:relative;overflow:hidden;min-height:78vh;display:flex;align-items:center;padding:132px 0 72px;isolation:isolate}
    .fout::before{content:"";position:absolute;inset:auto -20% -40% auto;width:70vw;height:70vw;max-width:900px;max-height:900px;border-radius:50%;background:radial-gradient(closest-side,rgba(97,78,217,.45),transparent);z-index:-1}
    .fout-code{position:absolute;right:-2vw;bottom:-6vw;font-family:var(--fh);font-weight:900;font-size:clamp(160px,34vw,520px);line-height:.8;letter-spacing:-.06em;color:transparent;-webkit-text-stroke:2px rgba(255,255,255,.12);z-index:-1;user-select:none}
    .fout-wrap{width:100%}
    .fout h1{margin:14px 0 18px;font-weight:900;text-transform:uppercase;letter-spacing:-.03em;line-height:.92;font-size:clamp(36px,7.4vw,92px);max-width:14ch}
    .fout h1 span{display:block;background:linear-gradient(135deg,var(--red),var(--lvio));-webkit-background-clip:text;background-clip:text;color:transparent}
    .fout-tekst{max-width:56ch;margin:0 0 28px;font-size:clamp(16px,1.4vw,19px);line-height:1.6;color:#E4E1F2}
    .fout-knoppen{display:flex;flex-wrap:wrap;gap:12px;margin:0 0 36px}
    .fout-rand{background:transparent;border:2px solid rgba(255,255,255,.5)}
    .fout-rand:hover{border-color:#fff;box-shadow:none}
    .fout-links{list-style:none;padding:0;margin:0 0 28px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));max-width:980px}
    .fout-links a{display:block;padding:16px 18px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);color:#fff;text-decoration:none;transition:border-color .2s,translate .2s}
    .fout-links a:hover{border-color:var(--lvio);translate:0 -2px}
    .fout-links b{display:block;font-family:var(--fh);font-weight:800}
    .fout-links small{display:block;margin-top:4px;color:#CFCBE0;font-size:14px}
    .fout-onder{color:#CFCBE0;font-size:14px;line-height:1.6;max-width:62ch}
    .fout-onder a{color:#fff}
    .fout-onder code{font-family:ui-monospace,monospace;background:rgba(255,255,255,.08);padding:2px 6px;word-break:break-all}
    @media (max-width:600px){.fout{padding:108px 0 56px}.wrap.fout-wrap{padding:0 16px}.fout-knoppen .btn{flex:1 1 100%;justify-content:center}}
</style>
