{{-- Foutmelding bij een veld. $veld: naam van het veld. --}}
@error($veld)<span class="err" id="fout-{{ str_replace(['[', ']'], ['-', ''], $veld) }}">{{ $message }}</span>@enderror
