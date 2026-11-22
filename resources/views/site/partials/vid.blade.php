{{--
    Nagebootste verticale video (animatie in CSS, geen videobestand). Puur decoratief.
    $v: a, b, c (kleuren), d (duur in s), tag, tekst; optioneel cls, side (iconen rechts), attrs (extra HTML-attributen).
--}}
@php($v += ['cls' => '', 'side' => false, 'attrs' => '', 'tag' => null, 'tekst' => null])
<div class="vid {{ $v['cls'] }}" style="--a:{{ $v['a'] }};--b:{{ $v['b'] }};--c:{{ $v['c'] }};--d:{{ $v['d'] }}s" {!! $v['attrs'] !!} aria-hidden="true">
    <div class="sc"></div><div class="pb"><i></i></div>
    @if ($v['tag'])<span class="tagv">{{ $v['tag'] }}</span>@endif
    @if ($v['side'])
        <div class="side">
            <span><svg width="24" height="24" viewBox="0 0 24 24" fill="#fff"><path d="M12 21s-7-4.5-9.5-9C.8 8.6 3 5 6.5 5c2 0 3.5 1.2 5.5 3 2-1.8 3.5-3 5.5-3C21 5 23.2 8.6 21.5 12 19 16.5 12 21 12 21z"/></svg></span>
            <span><svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="M4 4h16v12H8l-4 4z"/></svg></span>
            <span><svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="M14 5l7 7-7 7v-4c-5 0-8 1.5-10 5 1-5 4-9 10-10z"/></svg></span>
        </div>
    @endif
    @if ($v['tekst'])<div class="ui"><b>&#64;creator</b><small>{{ $v['tekst'] }}</small></div>@endif
    <span class="playic"><svg width="26" height="26" viewBox="0 0 24 24"><path d="M7 4l13 8-13 8z" fill="#fff"/></svg></span>
</div>
