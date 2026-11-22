@php
    $dag = $certificaat->enrollment->courseDay;
    $examen = $certificaat->enrollment->exam;
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificaat {{ $certificaat->number }} | MEBRANDING Academy</title>
    <style>
        :root{--aub:#3A3659;--dark:#2E2B48;--vio:#614ED9;--red:#D9303E;--ink:#22203A;--mute:#5E5A78;--line:#CFCBE0}
        *{box-sizing:border-box}
        body{margin:0;background:#F4F2FA;color:var(--ink);font-family:'Noto Sans',system-ui,sans-serif}
        .acties{max-width:900px;margin:0 auto;padding:16px;display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
        .acties a,.acties button{font:inherit;font-weight:700;padding:12px 18px;border:2px solid var(--vio);background:#fff;color:var(--vio);text-decoration:none;cursor:pointer;min-height:44px}
        .acties button{background:var(--vio);color:#fff}
        .vel{max-width:900px;margin:0 auto 32px;background:#fff;border:1px solid var(--line);padding:clamp(24px,6vw,64px);position:relative;overflow:hidden}
        .vel::before{content:"";position:absolute;left:0;top:0;right:0;height:10px;background:linear-gradient(90deg,var(--red),var(--vio))}
        .merk{font-family:'Montserrat',system-ui,sans-serif;font-weight:900;letter-spacing:.02em;color:var(--aub);font-size:20px}
        .eb{margin:32px 0 8px;color:var(--vio);text-transform:uppercase;letter-spacing:.1em;font-size:14px;font-weight:700}
        h1{font-family:'Montserrat',system-ui,sans-serif;font-weight:900;text-transform:uppercase;letter-spacing:-.02em;line-height:.95;margin:0 0 24px;font-size:clamp(32px,7vw,64px);color:var(--aub)}
        .naam{font-family:'Montserrat',system-ui,sans-serif;font-weight:800;font-size:clamp(26px,5vw,44px);margin:0 0 8px;overflow-wrap:anywhere}
        p{line-height:1.6;margin:0 0 8px}
        .gegevens{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:36px;padding-top:24px;border-top:1px solid var(--line)}
        .gegevens small{display:block;color:var(--mute);font-size:13px;text-transform:uppercase;letter-spacing:.06em}
        .gegevens b{overflow-wrap:anywhere}
        .controle{margin-top:24px;font-size:14px;color:var(--mute);overflow-wrap:anywhere}
        @media print{body{background:#fff}.acties{display:none}.vel{border:0;margin:0;max-width:none}@page{size:A4 landscape;margin:12mm}}
    </style>
</head>
<body>
@if ($afdrukken)
    <div class="acties">
        <a href="{{ route('academy.overzicht') }}">Terug naar Mijn academy</a>
        <a href="{{ route('academy.certificaat', ['certificaat' => $certificaat, 'download' => 1]) }}">Downloaden</a>
        <button type="button" onclick="window.print()">Afdrukken of opslaan als pdf</button>
    </div>
@endif
<main class="vel">
    <div class="merk">MEBRANDING ACADEMY</div>
    <p class="eb">Certificaat</p>
    <h1>Video maken voor lokale ondernemers</h1>
    <p>Hierbij verklaren wij dat</p>
    <p class="naam">{{ $certificaat->user->name }}</p>
    <p>de live cursusdag {{ $dag->title }} heeft gevolgd bij {{ $dag->location_name }} in {{ $dag->city }}{{ $examen?->average ? ' en het praktijkexamen heeft gehaald met gemiddeld '.number_format($examen->average, 1, ',', '').' van 5' : '' }}.</p>
    <div class="gegevens">
        <div><small>Nummer</small><b>{{ $certificaat->number }}</b></div>
        <div><small>Uitgereikt op</small><b>{{ $certificaat->issued_at->translatedFormat('j F Y') }}</b></div>
        <div><small>Cursusdag</small><b>{{ $dag->date->translatedFormat('j F Y') }}</b></div>
        @if ($dag->teacher_name)<div><small>Docent</small><b>{{ $dag->teacher_name }}</b></div>@endif
    </div>
    <p class="controle">Controleer dit certificaat op {{ $certificaat->controleUrl() }}</p>
</main>
</body>
</html>
