@php
    $titel = trim($__env->yieldContent('title'));
    $volledigeTitel = $titel !== '' ? $titel.' | MEBRANDING' : 'MEBRANDING. Echte creators. Echte verhalen. Meer klanten.';
    $omschrijving = trim($__env->yieldContent('description')) ?: 'MEBRANDING koppelt ondernemers in Noord-Nederland aan creators. Zij komen langs, maken een video en plaatsen die op TikTok en Instagram. Wij regelen de rest.';
    $inloggen = 'https://'.config('mebranding.domains.app').'/login';
    $huidige = request()->route()?->getName();
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $volledigeTitel }}</title>
    <meta name="description" content="{{ $omschrijving }}">
    <meta name="theme-color" content="#2E2B48">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="nl_NL">
    <meta property="og:site_name" content="MEBRANDING">
    <meta property="og:title" content="{{ $volledigeTitel }}">
    <meta property="og:description" content="{{ $omschrijving }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ url('/brand/icoon-512.png') }}">
    <script>document.documentElement.classList.add('js')</script>
    @vite(['resources/css/site.css', 'resources/js/site.js'])
</head>
<body class="@yield('body_class')">
<a class="skip" href="#inhoud">Naar de inhoud</a>

<header class="nav" id="nav">
    <div class="wrap">
        <a class="brand" href="{{ route('site.home') }}"><img src="/brand/mebranding-logo-wit.svg" alt="MEBRANDING, naar de homepage" width="163" height="26"></a>
        <nav aria-label="Hoofdmenu">
            <a href="{{ route('site.home') }}#werkwijze">Werkwijze</a>
            <a href="{{ route('site.pakketten') }}" @if ($huidige === 'site.pakketten') aria-current="page" @endif>Pakketten</a>
            <a href="{{ route('site.creators') }}" @if ($huidige === 'site.creators') aria-current="page" @endif>Voor creators</a>
        </nav>
        <span class="nr">
            <a class="login" href="{{ $inloggen }}">Inloggen</a>
            <a class="btn" href="{{ route('site.ondernemers') }}" @if ($huidige === 'site.ondernemers') aria-current="page" @endif>Kennismaken</a>
            <button type="button" class="mbtn" id="mbtn" aria-expanded="false" aria-controls="mmenu" aria-label="Menu openen"><i></i><i></i><i></i></button>
        </span>
    </div>
    <div class="mmenu" id="mmenu">
        <nav class="wrap" aria-label="Menu">
            <a href="{{ route('site.home') }}#werkwijze">Werkwijze</a>
            <a href="{{ route('site.pakketten') }}" @if ($huidige === 'site.pakketten') aria-current="page" @endif>Pakketten</a>
            <a href="{{ route('site.creators') }}" @if ($huidige === 'site.creators') aria-current="page" @endif>Voor creators</a>
            <a href="{{ route('site.ondernemers') }}" @if ($huidige === 'site.ondernemers') aria-current="page" @endif>Kennismaken</a>
            <a href="{{ $inloggen }}">Inloggen</a>
        </nav>
    </div>
</header>

<main id="inhoud" tabindex="-1">
@yield('content')
</main>

<footer>
    <div class="wrap foot">
        <a href="{{ route('site.home') }}"><img src="/brand/mebranding-logo-wit.svg" alt="MEBRANDING" width="151" height="24"></a>
        <nav aria-label="Voettekst">
            <a href="{{ route('site.ondernemers') }}">Voor ondernemers</a>
            <a href="{{ route('site.creators') }}">Voor creators</a>
            <a href="{{ route('site.pakketten') }}">Pakketten</a>
            <a href="{{ route('site.privacy') }}">Privacy</a>
            <a href="{{ route('site.cookies') }}">Cookies</a>
            <a href="{{ $inloggen }}">Inloggen</a>
        </nav>
        <p><b>MEBRANDING.</b> Echte creators. Echte verhalen. Meer klanten.</p>
    </div>
</footer>
</body>
</html>
