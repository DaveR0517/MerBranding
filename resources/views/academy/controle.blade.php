@extends('site.layout')

@section('title', $certificaat ? 'Certificaat '.$certificaat->number : 'Certificaat niet gevonden')
@section('description', 'Controle van een certificaat van de MEBRANDING Academy.')

@section('content')
<section style="max-width: 1280px; margin: 0 auto; padding-left: 24px; padding-right: 24px; box-sizing: border-box; padding-top: clamp(56px, 7vw, 110px); padding-bottom: clamp(64px, 8vw, 110px); display: flex; justify-content: center">
<div style="width: min(100%, 560px); display: flex; flex-direction: column; gap: 18px">
<p style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 13px; letter-spacing: 1.5px; text-transform: uppercase; color: #614ED9">Controle certificaat</p>
@if ($certificaat)
<h1 style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 900; font-size: clamp(32px, 5vw, 52px); line-height: 1; letter-spacing: -1.5px; color: #2E2B48">Dit certificaat is echt<span style="background: linear-gradient(90deg,#D9303E,#614ED9); -webkit-background-clip: text; background-clip: text; color: transparent">.</span></h1>
<p style="margin: 0; align-self: flex-start; padding: 8px 14px; border-radius: 999px; background: #E5F3EA; color: #1E6B3A; font-family: Montserrat,Arial,sans-serif; font-weight: 700; font-size: 14px">Geldig, uitgereikt door MEBRANDING Academy</p>
<dl style="margin: 8px 0 0; padding-top: 20px; border-top: 1px solid #E3E0EC; display: grid; grid-template-columns: minmax(0, 9rem) minmax(0, 1fr); gap: 12px 16px; font-family: 'Noto Sans',Arial,sans-serif; font-size: 16px; line-height: 1.5; color: #2A2840">
<dt style="color: #5E5A78">Naam</dt><dd style="margin: 0; font-weight: 700; overflow-wrap: anywhere">{{ $certificaat->user->name }}</dd>
<dt style="color: #5E5A78">Nummer</dt><dd style="margin: 0; font-weight: 700; overflow-wrap: anywhere">{{ $certificaat->number }}</dd>
<dt style="color: #5E5A78">Uitgereikt op</dt><dd style="margin: 0">{{ $certificaat->issued_at->translatedFormat('j F Y') }}</dd>
<dt style="color: #5E5A78">Cursusdag</dt><dd style="margin: 0">{{ $certificaat->enrollment->courseDay->title }}, {{ $certificaat->enrollment->courseDay->location_name }}, {{ $certificaat->enrollment->courseDay->city }}</dd>
</dl>
@else
<h1 style="margin: 0; font-family: Montserrat,Arial,sans-serif; font-weight: 900; font-size: clamp(32px, 5vw, 52px); line-height: 1; letter-spacing: -1.5px; color: #2E2B48">Certificaat niet gevonden<span style="background: linear-gradient(90deg,#D9303E,#614ED9); -webkit-background-clip: text; background-clip: text; color: transparent">.</span></h1>
<p style="margin: 0; font-family: 'Noto Sans',Arial,sans-serif; font-size: 16px; line-height: 1.7; color: #5E5A78">Bij deze controlelink hoort geen certificaat. Controleer of je de hele link hebt overgenomen, of mail ons op <a href="mailto:{{ config('mebranding.team_email') }}" style="color: #614ED9">{{ config('mebranding.team_email') }}</a>.</p>
@endif
</div>
</section>
@endsection
