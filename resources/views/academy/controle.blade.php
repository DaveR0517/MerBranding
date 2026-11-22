@extends('site.layout')

@section('title', $certificaat ? 'Certificaat '.$certificaat->number : 'Certificaat niet gevonden')
@section('description', 'Controle van een certificaat van de MEBRANDING Academy.')

@section('content')
<section class="light" style="min-height:70vh;display:grid;place-items:center;padding:120px 16px 72px">
    <div style="width:100%;max-width:560px;background:#fff;border:1px solid var(--line);padding:clamp(20px,5vw,40px)">
        <p class="eb">Controle certificaat</p>
        @if ($certificaat)
            <h1 style="font-weight:900;font-size:clamp(26px,6vw,38px);line-height:1.05;margin:0 0 12px;color:var(--ink)">Dit certificaat is echt</h1>
            <p style="display:inline-block;background:#E5F3EA;color:var(--ok);font-weight:700;padding:6px 12px;margin:0 0 20px">Geldig, uitgereikt door MEBRANDING Academy</p>
            <dl style="margin:0;display:grid;grid-template-columns:minmax(0,9rem) minmax(0,1fr);gap:10px 16px;color:var(--ink)">
                <dt style="color:var(--mute)">Naam</dt><dd style="margin:0;font-weight:700">{{ $certificaat->user->name }}</dd>
                <dt style="color:var(--mute)">Nummer</dt><dd style="margin:0;font-weight:700;overflow-wrap:anywhere">{{ $certificaat->number }}</dd>
                <dt style="color:var(--mute)">Uitgereikt op</dt><dd style="margin:0">{{ $certificaat->issued_at->translatedFormat('j F Y') }}</dd>
                <dt style="color:var(--mute)">Cursusdag</dt><dd style="margin:0">{{ $certificaat->enrollment->courseDay->title }}, {{ $certificaat->enrollment->courseDay->location_name }}, {{ $certificaat->enrollment->courseDay->city }}</dd>
            </dl>
        @else
            <h1 style="font-weight:900;font-size:clamp(26px,6vw,38px);line-height:1.05;margin:0 0 12px;color:var(--ink)">Certificaat niet gevonden</h1>
            <p style="margin:0;line-height:1.6;color:var(--mute)">Bij deze controlelink hoort geen certificaat. Controleer of je de hele link hebt overgenomen, of mail ons op <a href="mailto:{{ config('mebranding.team_email') }}" style="color:var(--vio)">{{ config('mebranding.team_email') }}</a>.</p>
        @endif
    </div>
</section>
@endsection
