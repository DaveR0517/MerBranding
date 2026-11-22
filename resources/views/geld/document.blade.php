{{-- Afdrukbaar document (factuur, maandrapport): los van de portaalschil, A4 bij afdrukken. Opslaan als pdf via de afdrukknop. --}}
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titel') - MEBRANDING</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --aubergine: #3a3659; --violet: #614ed9; --rood: #d9303e; --grijs: #6b6880; --lijn: #e6e3ef; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f2f9; color: var(--aubergine); font: 15px/1.5 'Noto Sans', system-ui, sans-serif; }
        h1, h2, h3, .kop { font-family: 'Montserrat', 'Noto Sans', sans-serif; margin: 0; }
        .balk { max-width: 860px; margin: 0 auto; padding: 16px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; }
        .balk a { color: var(--violet); font-weight: 600; text-decoration: none; }
        .knop { border: 0; border-radius: 10px; background: var(--violet); color: #fff; font: 700 14px 'Noto Sans', sans-serif; padding: 10px 16px; cursor: pointer; min-height: 44px; }
        .knop.rustig { background: #fff; color: var(--aubergine); border: 1px solid var(--lijn); }
        .vel { max-width: 860px; margin: 0 auto 40px; background: #fff; border-radius: 18px; padding: 24px 16px; box-shadow: 0 1px 3px rgba(46,43,72,.08); }
        @media (min-width: 700px) { .vel { padding: 44px 52px; } }
        .streep { display: block; width: 40px; height: 4px; border-radius: 9px; background: linear-gradient(135deg, #d9303e, #614ed9); margin-bottom: 10px; }
        .logo { font: 800 22px 'Montserrat', sans-serif; letter-spacing: .04em; }
        .klein { font-size: 13px; color: var(--grijs); }
        .label { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--grijs); }
        .rij { display: flex; flex-wrap: wrap; gap: 24px; justify-content: space-between; }
        .rij > * { min-width: 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--grijs); padding: 8px 6px; border-bottom: 2px solid var(--lijn); }
        td { padding: 10px 6px; border-bottom: 1px solid var(--lijn); vertical-align: top; overflow-wrap: anywhere; }
        .r { text-align: right; white-space: nowrap; }
        .badge { display: inline-block; border-radius: 99px; padding: 2px 10px; font-size: 12px; font-weight: 700; }
        .badge.groen { background: #d1fae5; color: #065f46; } .badge.rood { background: #fde2e4; color: var(--rood); } .badge.violet { background: #ece9fb; color: var(--violet); } .badge.amber { background: #fef3c7; color: #92400e; }
        .tegels { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        @media (min-width: 700px) { .tegels { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .tegel { border: 1px solid var(--lijn); border-radius: 12px; padding: 12px; }
        .tegel b { display: block; font: 800 24px 'Montserrat', sans-serif; }
        .sectie { margin-top: 28px; }
        .sectie h2 { font-size: 17px; margin-bottom: 8px; }
        .tabel-wrap { overflow-x: auto; }
        @media print {
            body { background: #fff; font-size: 12px; }
            .balk { display: none; }
            .vel { box-shadow: none; border-radius: 0; margin: 0; max-width: none; padding: 0; }
            @page { size: A4; margin: 16mm; }
        }
    </style>
</head>
<body>
    <div class="balk">
        <a href="{{ $terug }}">&larr; Terug</a>
        <span style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="knop rustig" type="button" onclick="window.print()">Afdrukken</button>
            <button class="knop" type="button" onclick="window.print()" title="Kies bij afdrukken: Opslaan als pdf">Downloaden (pdf)</button>
        </span>
    </div>
    <main class="vel">
        @yield('inhoud')
    </main>
</body>
</html>
