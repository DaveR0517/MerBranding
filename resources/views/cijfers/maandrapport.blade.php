@php
    $getal = fn ($n) => number_format((int) $n, 0, ',', '.');
    $bron = fn ($bronnen) => implode(', ', array_map(fn ($b) => ['handmatig' => 'handmatig', 'instagram' => 'Instagram', 'tiktok' => 'TikTok'][$b] ?? $b, $bronnen));
    $platform = fn ($p) => ['instagram' => 'Instagram', 'tiktok' => 'TikTok'][$p] ?? ucfirst($p);
    $terug = url()->previous();
@endphp
@extends('geld.document', ['terug' => $terug])
@section('titel', "Maandrapport {$r['maand_label']}, {$r['bedrijf']['name']}")
@section('inhoud')
    <span class="streep"></span>
    <div class="label">Maandrapport · {{ $r['maand_label'] }}</div>
    <h1 style="font-size:26px;margin-top:4px">{{ $r['bedrijf']['name'] }}</h1>
    <p class="klein">@if ($r['pakket'])Pakket {{ $r['pakket'] }}. @endif @if ($r['lopend'])Deze maand loopt nog: de cijfers zijn een tussenstand.@endif Cijfers jonger dan 14 dagen zijn voorlopig.</p>

    <div class="tegels sectie">
        <div class="tegel"><span class="label">Aangevraagd</span><b>{{ $r['tellingen']['aangevraagd'] }}</b></div>
        <div class="tegel"><span class="label">Live gegaan</span><b>{{ $r['tellingen']['live'] }}</b></div>
        <div class="tegel"><span class="label">Afgerond</span><b>{{ $r['tellingen']['afgerond'] }}</b></div>
        <div class="tegel"><span class="label">Creators</span><b>{{ $r['tellingen']['creators'] }}</b></div>
    </div>

    <div class="sectie">
        <h2>Cijfers per platform</h2>
        <p class="klein">Per platform opgeteld, niet over platforms heen: elk platform telt views anders.</p>
        @forelse ($r['platforms'] as $p)
            <div class="tegel" style="margin-top:8px">
                <strong>{{ $platform($p['platform']) }}</strong> <span class="klein">{{ $p['posts'] }} posts · bron {{ $bron($p['sources']) ?: 'nog geen cijfers' }}@if ($p['is_estimate']) · deels schatting @endif</span>
                <div class="tegels" style="margin-top:6px">
                    @foreach (config('geld.metrics') as $m => $label)
                        <div><span class="label">{{ $label }}</span><div class="kop">{{ $p['totals'][$m] === null ? 'geen cijfer' : $getal($p['totals'][$m]) }}</div></div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="klein">Deze maand ging er geen post live.</p>
        @endforelse
    </div>

    @if (count($r['publicaties']))
        <div class="sectie tabel-wrap">
            <h2>Alle posts op views</h2>
            <table>
                <thead><tr><th>Post</th><th>Platform</th><th class="r">Views</th><th class="r">Interactie</th><th>Bron</th></tr></thead>
                <tbody>
                @foreach ($r['publicaties'] as $i => $p)
                    <tr>
                        <td><strong>{{ $i + 1 }}. {{ $p['title'] }}</strong><br><span class="klein">{{ $p['creator'] }}</span></td>
                        <td>{{ $platform($p['platform']) }}</td>
                        <td class="r">{{ isset($p['metrics']['views']) ? $getal($p['metrics']['views']['value']) : 'nog niet' }}</td>
                        <td class="r">{{ $p['interaction'] !== null ? $getal($p['interaction']) : '' }}</td>
                        <td class="klein">{{ $bron($p['sources']) }}@if ($p['is_estimate']), schatting @endif @if ($p['fetched_at'])<br>{{ \Carbon\Carbon::parse($p['fetched_at'])->timezone('Europe/Amsterdam')->translatedFormat('j M H:i') }}@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="sectie tabel-wrap">
        <h2>Samenwerkingen</h2>
        @if (count($r['samenwerkingen']))
            <table>
                <thead><tr><th>Samenwerking</th><th>Creator</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($r['samenwerkingen'] as $c)
                    <tr><td><strong>{{ $c['title'] }}</strong><br><span class="klein">{{ $c['location'] }} · {{ $c['number'] }}</span></td><td>{{ $c['creator'] ?? 'Nog geen creator' }}</td><td>{{ $c['status']['label'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="klein">Geen samenwerkingen in deze maand.</p>
        @endif
    </div>

    <div class="sectie">
        <h2>Tegoed</h2>
        <div class="tegels">
            <div class="tegel"><span class="label">Toegekend</span><b>{{ $r['tegoed']['toegekend'] }}</b><span class="klein">punten</span></div>
            <div class="tegel"><span class="label">Verbruikt</span><b>{{ $r['tegoed']['verbruikt'] }}</b><span class="klein">punten</span></div>
            <div class="tegel"><span class="label">Terug</span><b>{{ $r['tegoed']['vrijgegeven'] }}</b><span class="klein">punten</span></div>
            <div class="tegel"><span class="label">Saldo {{ $r['lopend'] ? 'nu' : 'einde maand' }}</span><b>{{ $r['tegoed']['saldo_einde'] }}</b><span class="klein">punten</span></div>
        </div>
        <p class="klein">Ongebruikt tegoed aan het einde van de maand: {{ $r['ongebruikt_tegoed'] === 'vervallen' ? 'vervalt' : 'gaat mee naar de volgende maand' }} (voorstel, nog niet besloten).</p>
    </div>

    <p class="klein sectie" style="border-top:1px solid var(--lijn);padding-top:12px">MEBRANDING · gemaakt op {{ now()->timezone('Europe/Amsterdam')->translatedFormat('j F Y, H:i') }}. Bron per cijfer: Instagram, TikTok of handmatig ingevoerd met screenshot.</p>
@endsection
