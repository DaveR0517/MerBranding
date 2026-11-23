{{-- Begin van een websiteformulier: beveiliging, spamval en de samenvatting als er iets ontbreekt. --}}
@csrf
{{-- Spamval: echte bezoekers zien dit veld niet. De controller slaat inzendingen waarin het is ingevuld niet op. --}}
<div class="onzichtbaar" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
@if ($errors->any())
<p class="melding melding-fout" role="alert" tabindex="-1" data-focus>Er ontbreekt nog iets. Kijk de velden met een melding na.</p>
@endif
