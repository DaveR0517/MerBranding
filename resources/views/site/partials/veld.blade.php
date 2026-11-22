{{--
    Invoerveld met label, foutmelding en oude waarde.
    $naam, $label; optioneel $type (text), $verplicht (false), $hint, $attrs (extra HTML-attributen), $standaard (standaardwaarde).
--}}
@php
    $type ??= 'text';
    $verplicht ??= false;
    $id = 'veld-'.$naam;
    $fout = $errors->has($naam);
    $beschrijving = trim(($fout ? 'fout-'.$naam : '').' '.(isset($hint) ? $id.'-hint' : ''));
@endphp
<label class="fld" for="{{ $id }}">
    <span>{{ $label }}@if ($verplicht) <span class="req" aria-hidden="true">*</span>@endif</span>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $naam }}" rows="4" @if ($verplicht) required @endif @if ($fout) aria-invalid="true" @endif @if ($beschrijving) aria-describedby="{{ $beschrijving }}" @endif {!! $attrs ?? '' !!}>{{ old($naam, $standaard ?? '') }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $naam }}" value="{{ old($naam, $standaard ?? '') }}" @if ($verplicht) required @endif @if ($fout) aria-invalid="true" @endif @if ($beschrijving) aria-describedby="{{ $beschrijving }}" @endif {!! $attrs ?? '' !!}>
    @endif
    @isset($hint)<span class="hint" id="{{ $id }}-hint">{{ $hint }}</span>@endisset
    @include('site.partials.fout', ['veld' => $naam])
</label>
