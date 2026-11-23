@php
    $titel = trim($__env->yieldContent('title'));
    $volledigeTitel = $titel !== '' ? $titel.' | MEBRANDING' : 'MEBRANDING, echte creators voor jouw zaak';
    $omschrijving = trim($__env->yieldContent('description')) ?: 'MEBRANDING brengt creators naar restaurants, hotels, musea en winkels. Zij maken een video voor Instagram en TikTok. Wij regelen de rest, voor een vast bedrag per maand.';
    // De keuze Ondernemers of Creators in de kop staat alleen op de home.
    $keuze = request()->routeIs('site.home');
@endphp
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $volledigeTitel }}</title>
<meta name="description" content="{{ $omschrijving }}">
<meta name="theme-color" content="#2E2B48">
<meta property="og:title" content="{{ $titel !== '' ? $titel : $volledigeTitel }}">
<meta property="og:description" content="{{ $omschrijving }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="nl_NL">
<meta property="og:site_name" content="MEBRANDING">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ url('/brand/icoon-512.png') }}">
<link rel="canonical" href="{{ url()->current() }}">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
@vite(['resources/css/site.css', 'resources/js/site.js'])
@stack('styles')
</head>
<body>
<div style="min-height: 100vh; background: #FFFFFF; font-family: 'Noto Sans',Arial,sans-serif; color: #2A2840">
<header @class(['kop-keuze' => $keuze]) style="background: #FFFFFF; border-bottom: 1px solid #E3E0EC; position: relative">
<div style="max-width: 1280px; margin: 0 auto; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 20px">
<div style="display: flex; align-items: center; gap: 28px">
<a href="{{ route('site.home') }}" aria-label="MEBRANDING home"><img src="/brand/mebranding-logo-aubergine.svg" width="627" height="100" alt="MEBRANDING" style="height: 24px; width: auto; display: block"></a>@if ($keuze)
<div class="navl"><div role="group" aria-label="Kies wie je bent" style="display: inline-flex; background: #EFEDF5; border-radius: 999px; padding: 4px">
<button type="button" data-set="o" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; border: 0; cursor: pointer; min-height: 44px; padding: 0 18px; border-radius: 999px; background: #2E2B48; color: #FCFBFE">Ondernemers</button>
<button type="button" data-set="c" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; border: 0; cursor: pointer; min-height: 44px; padding: 0 18px; border-radius: 999px; background: transparent; color: #2E2B48">Creators</button></div></div>
@endif</div>
<nav class="navl" style="gap: 26px; align-items: center"><a href="{{ route('site.hoe-het-werkt') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 600; font-size: 14px; text-decoration: none; color: #2E2B48">Hoe het werkt</a><a href="{{ route('site.pakketten') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 600; font-size: 14px; text-decoration: none; color: #2E2B48">Pakketten</a><a href="{{ route('site.creators') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 600; font-size: 14px; text-decoration: none; color: #2E2B48">Creators</a><a href="{{ route('site.academy') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 600; font-size: 14px; text-decoration: none; color: #2E2B48">Academy</a>
<a href="{{ route('login') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; text-decoration: none; color: #2E2B48; padding: 12px 20px; border-radius: 999px; border: 2px solid #2E2B48">Inloggen</a>
<a href="{{ route('site.kennismaken') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; text-decoration: none; color: #FCFBFE; background: #2E2B48; padding: 14px 22px; border-radius: 999px">Kennismaken</a></nav>
<button class="menu" type="button" data-toggle-menu aria-expanded="false" aria-label="Menu openen" style="width: 44px; height: 44px; border: 1px solid #2E2B48; border-radius: 22px; background: transparent; align-items: center; justify-content: center; cursor: pointer"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2E2B48" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M4 8h16M4 16h16"/></svg></button>
</div>
<div data-menu hidden>
<nav style="border-top: 1px solid #E3E0EC; padding: 8px 24px 20px; display: flex; flex-direction: column; background: #FFFFFF"><a href="{{ route('site.hoe-het-werkt') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 20px; text-decoration: none; color: #2E2B48; padding: 14px 0; border-bottom: 1px solid #E3E0EC">Hoe het werkt</a><a href="{{ route('site.pakketten') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 20px; text-decoration: none; color: #2E2B48; padding: 14px 0; border-bottom: 1px solid #E3E0EC">Pakketten</a><a href="{{ route('site.creators') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 20px; text-decoration: none; color: #2E2B48; padding: 14px 0; border-bottom: 1px solid #E3E0EC">Creators</a><a href="{{ route('site.academy') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 20px; text-decoration: none; color: #2E2B48; padding: 14px 0; border-bottom: 1px solid #E3E0EC">Academy</a><a href="{{ route('login') }}" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 20px; text-decoration: none; color: #2E2B48; padding: 14px 0; border-bottom: 1px solid #E3E0EC">Inloggen</a>
<a href="{{ route('site.kennismaken') }}" style="margin-top: 16px; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 16px; text-decoration: none; color: #FCFBFE; background: #2E2B48; padding: 16px; border-radius: 999px; text-align: center">Plan een kennismaking</a>
@if ($keuze)
<div style="margin-top: 16px; display: flex; justify-content: center"><div role="group" aria-label="Kies wie je bent" style="display: inline-flex; background: #EFEDF5; border-radius: 999px; padding: 4px">
<button type="button" data-set="o" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; border: 0; cursor: pointer; min-height: 44px; padding: 0 18px; border-radius: 999px; background: #2E2B48; color: #FCFBFE">Ondernemers</button>
<button type="button" data-set="c" style="font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; border: 0; cursor: pointer; min-height: 44px; padding: 0 18px; border-radius: 999px; background: transparent; color: #2E2B48">Creators</button></div></div>
@endif</nav></div>
</header>
<main>
@yield('content')
</main>
<footer style="border-top: 1px solid #E3E0EC; background: #FFFFFF"><div style="max-width: 1280px; margin: 0 auto; padding: 44px 24px 56px; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 28px">
<div style="display: flex; flex-direction: column; gap: 12px"><img src="/brand/mebranding-logo-aubergine.svg" width="627" height="100" alt="MEBRANDING" style="height: 22px; width: auto; display: block; align-self: flex-start"><p style="margin: 0; font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; color: #5E5A78">Echte creators. Echte verhalen. Meer klanten.</p></div><div style="display: flex; flex-direction: column; gap: 10px"><p style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; color: #2E2B48">Ondernemers</p><a href="{{ route('site.hoe-het-werkt') }}" style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; text-decoration: none; color: #5E5A78">Hoe het werkt</a><a href="{{ route('site.pakketten') }}" style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; text-decoration: none; color: #5E5A78">Pakketten</a><a href="{{ route('site.diamond') }}" style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; text-decoration: none; color: #5E5A78">Diamond</a><a href="{{ route('site.kennismaken') }}" style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; text-decoration: none; color: #5E5A78">Kennismaken</a></div><div style="display: flex; flex-direction: column; gap: 10px"><p style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; color: #2E2B48">Creators</p><a href="{{ route('site.creators') }}" style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; text-decoration: none; color: #5E5A78">Voor creators</a><a href="{{ route('site.aanmelden-creator') }}" style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; text-decoration: none; color: #5E5A78">Aanmelden</a><a href="{{ route('site.academy') }}" style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; text-decoration: none; color: #5E5A78">Academy</a></div><div style="display: flex; flex-direction: column; gap: 10px"><p style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px; color: #2E2B48">MEBRANDING</p><a href="{{ route('login') }}" style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; text-decoration: none; color: #5E5A78">Inloggen</a><a href="{{ route('site.privacy') }}" style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; text-decoration: none; color: #5E5A78">Privacy</a><a href="{{ route('site.cookies') }}" style="font-family: 'Noto Sans',Arial,sans-serif; font-size: 14px; text-decoration: none; color: #5E5A78">Cookies</a></div></div></footer>
</div>
</body>
</html>
