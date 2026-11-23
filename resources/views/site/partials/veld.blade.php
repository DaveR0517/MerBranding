{{--
    Formulierveld zoals in het ontwerp (label boven, veld eronder), met oude waarde en foutmelding.
    $naam, $label; optioneel $type (text, email, tel, date, select, textarea), $opties (waarde => tekst, bij select),
    $placeholder, $verplicht (false), $breed (over de hele breedte), $attrs (extra HTML-attributen).
--}}
@php
    $type ??= 'text';
    $id = 'veld-'.$naam;
    $fout = $errors->first($naam);
    $aria = ($verplicht ?? false ? ' required' : '').($fout ? ' aria-invalid="true" aria-describedby="fout-'.$naam.'"' : '').' '.($attrs ?? '');
@endphp
<label for="{{ $id }}" style="display: flex; flex-direction: column; gap: 8px; {{ ($breed ?? false) ? 'grid-column: 1 / -1;' : '' }} font-family: Montserrat,Arial,sans-serif; font-weight: 600; font-size: 14px; color: #2E2B48"><span>{{ $label }}@unless (($verplicht ?? false) || $type === 'select') <span class="optioneel">(optioneel)</span>@endunless</span>
@if ($type === 'select')
<select id="{{ $id }}" name="{{ $naam }}" @class(['fout-veld' => $fout]) {!! $aria !!} style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 16px; min-height: 52px; padding: 0 14px; border: 1px solid #CFCADC; border-radius: 12px; background: #FFFFFF; color: #2A2840">@foreach ($opties as $waarde => $tekst)<option value="{{ $waarde }}" @selected((string) old($naam) === (string) $waarde)>{{ $tekst }}</option>@endforeach</select>
@elseif ($type === 'textarea')
<textarea id="{{ $id }}" name="{{ $naam }}" placeholder="{{ $placeholder ?? '' }}" rows="4" @class(['fout-veld' => $fout]) {!! $aria !!} style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 16px; padding: 14px 16px; border: 1px solid #CFCADC; border-radius: 12px; resize: vertical; color: #2A2840">{{ old($naam) }}</textarea>
@else
<input id="{{ $id }}" type="{{ $type }}" name="{{ $naam }}" value="{{ old($naam) }}" placeholder="{{ $placeholder ?? '' }}" @class(['fout-veld' => $fout]) {!! $aria !!} style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 16px; min-height: 52px; padding: 0 16px; border: 1px solid #CFCADC; border-radius: 12px; color: #2A2840">
@endif
@if ($fout)<span class="fout-tekst" id="fout-{{ $naam }}">{{ $fout }}</span>@endif
</label>
