@php
    use App\Services\Geld;
    $afzender = config('geld.sender');
    $status = $f->weergaveStatus();
    $kleur = ['betaald' => 'groen', 'te_laat' => 'rood', 'openstaand' => 'amber', 'concept' => 'violet'][$status] ?? 'violet';
    $statusTekst = ['betaald' => 'Betaald', 'te_laat' => 'Te laat', 'openstaand' => 'Openstaand', 'concept' => 'Concept'][$status] ?? $status;
    $eigenaar = $f->company->owners->first();
@endphp
@extends('geld.document', ['terug' => $terug])
@section('titel', "Factuur {$f->number}")
@section('inhoud')
    <div class="rij" style="align-items:flex-start">
        <div>
            <span class="streep"></span>
            <div class="logo">MEBRANDING</div>
        </div>
        <div style="text-align:right">
            <h1 style="font-size:28px">Factuur</h1>
            <div class="kop" style="font-size:16px">{{ $f->number }}</div>
            <div style="margin-top:6px"><span class="badge {{ $kleur }}">{{ $statusTekst }}</span></div>
        </div>
    </div>

    <div class="rij sectie">
        <div>
            <div class="label">Van</div>
            <div><strong>{{ $afzender['name'] }}</strong><br>{{ $afzender['address'] }}<br>KvK {{ $afzender['kvk'] }}<br>Btw {{ $afzender['vat'] }}<br>{{ $afzender['email'] }}</div>
        </div>
        <div>
            <div class="label">Aan</div>
            <div><strong>{{ $f->company->name }}</strong>@if ($eigenaar)<br>t.a.v. {{ $eigenaar->name }}@endif @if ($f->company->kvk)<br>KvK {{ $f->company->kvk }}@endif<br>Klantnummer K-{{ str_pad((string) $f->company_id, 4, '0', STR_PAD_LEFT) }}</div>
        </div>
        <div>
            <div class="label">Factuurdatum</div><div>{{ $f->issued_on->translatedFormat('D j M Y') }}</div>
            <div class="label" style="margin-top:6px">Vervaldatum</div><div>{{ $f->due_on->translatedFormat('D j M Y') }}</div>
            <div class="label" style="margin-top:6px">Periode</div><div>{{ $f->period_start->translatedFormat('j') }} tot en met {{ $f->period_end->translatedFormat('j M Y') }}</div>
        </div>
    </div>

    <div class="sectie tabel-wrap">
        <table>
            <thead><tr><th>Omschrijving</th><th class="r">Aantal</th><th class="r">Prijs</th><th class="r">Bedrag</th></tr></thead>
            <tbody>
            @foreach ($f->lines as $regel)
                <tr>
                    <td><strong>{{ $regel['omschrijving'] }}</strong>@if (! empty($regel['toelichting']))<br><span class="klein">{{ $regel['toelichting'] }}</span>@endif</td>
                    <td class="r">{{ $regel['aantal'] }}</td>
                    <td class="r">{{ Geld::euro($regel['prijs_cents']) }}</td>
                    <td class="r">{{ Geld::euro($regel['bedrag_cents']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <table style="max-width:320px;margin-left:auto;margin-top:8px">
            <tr><td>Subtotaal</td><td class="r">{{ Geld::euro($f->subtotal_cents) }}</td></tr>
            <tr><td>Btw {{ $f->vat_rate }}%</td><td class="r">{{ Geld::euro($f->vat_cents) }}</td></tr>
            <tr><td><strong>Totaal</strong></td><td class="r"><strong>{{ Geld::euro($f->total_cents) }}</strong></td></tr>
        </table>
    </div>

    <p class="sectie">
        @if ($status === 'betaald')
            Betaald op {{ $f->paid_at->translatedFormat('D j M Y') }}. Dank je wel.
        @else
            Openstaand, vervalt {{ $f->due_on->translatedFormat('D j M Y') }}. Maak {{ Geld::euro($f->total_cents) }} over naar {{ $afzender['iban'] }} o.v.v. {{ $f->number }}.
        @endif
    </p>

    <p class="klein sectie" style="border-top:1px solid var(--lijn);padding-top:12px">
        {{ $afzender['name'] }} · {{ $afzender['address'] }} · Bank {{ $afzender['iban'] }} · KvK en btw {{ $afzender['kvk'] }} · {{ $afzender['vat'] }}<br>
        De gegevens van MEBRANDING (adres, KvK, btw, IBAN) zijn nog placeholders.
    </p>
@endsection
