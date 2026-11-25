{{-- Mail van een berichtsjabloon. De vorm (standaard, bevestiging, welkom, geld, beveiliging, samenvatting) bepaalt label en blokken. --}}
@php
    $labels = [
        'bevestiging' => ['Bevestiging', '#614ED9'],
        'welkom' => ['Welkom', '#614ED9'],
        'geld' => ['Betaling', '#614ED9'],
        'beveiliging' => ['Beveiliging', '#D9303E'],
        'samenvatting' => ['Dagoverzicht', '#614ED9'],
    ];
    [$label, $labelKleur] = $labels[$vorm ?? 'standaard'] ?? [null, null];
@endphp
@extends('mail.layout', ['label' => $label, 'labelKleur' => $labelKleur])

@section('alineas')
@if (! empty($bedrag))
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 18px;background-color:#F3F1FC;border-radius:6px;">
  <tr><td style="padding:18px 20px;">
    <p style="margin:0;font-size:12px;line-height:16px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#6B6880;">Bedrag</p>
    <p style="margin:4px 0 0;font-family:'Montserrat','Arial Black',Arial,sans-serif;font-weight:900;font-size:30px;line-height:36px;color:#22203A;">{{ $bedrag }}</p>
  </td></tr>
</table>
@endif
@foreach ($alineas as $alinea)
<p style="margin:0 0 14px;">{{ $alinea }}</p>
@endforeach
@if (! empty($gegevens))
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 18px;border:1px solid #E2E1EA;border-radius:6px;">
  @foreach ($gegevens as [$naam, $waarde])
  <tr>
    <td width="34%" valign="top" style="padding:11px 0 11px 16px;{{ $loop->first ? '' : 'border-top:1px solid #E2E1EA;' }}font-size:13px;line-height:20px;color:#6B6880;">{{ $naam }}</td>
    <td valign="top" style="padding:11px 16px 11px 8px;{{ $loop->first ? '' : 'border-top:1px solid #E2E1EA;' }}font-size:15px;line-height:20px;font-weight:700;color:#22203A;">{{ $waarde }}</td>
  </tr>
  @endforeach
</table>
@endif
@if (! empty($stappen))
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:2px 0 14px;">
  @foreach ($stappen as $stap)
  <tr>
    <td width="40" valign="top" style="padding:0 0 12px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td width="28" height="28" align="center" valign="middle" bgcolor="#614ED9" style="width:28px;height:28px;border-radius:14px;background-color:#614ED9;color:#FFFFFF;font-size:14px;font-weight:700;line-height:28px;">{{ $loop->iteration }}</td></tr></table>
    </td>
    <td valign="top" style="padding:3px 0 12px;font-size:15px;line-height:22px;color:#22203A;">{{ $stap }}</td>
  </tr>
  @endforeach
</table>
@endif
@if (! empty($regels))
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 18px;border-top:1px solid #E2E1EA;">
  @foreach ($regels as $regel)
  <tr><td style="padding:12px 0;border-bottom:1px solid #E2E1EA;">
    <a href="{{ $regel[2] ?? '#' }}" style="font-size:15px;line-height:21px;font-weight:700;color:#22203A;text-decoration:none;">{{ $regel[0] }}</a>
    @if (! empty($regel[1]))<br><span style="font-size:13px;line-height:20px;color:#6B6880;">{{ $regel[1] }}</span>@endif
  </td></tr>
  @endforeach
</table>
@endif
@if (($vorm ?? null) === 'beveiliging')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 14px;background-color:#FCEEEF;border-radius:6px;">
  <tr><td style="padding:12px 16px;font-size:13px;line-height:20px;color:#8E1F29;">Deel deze mail en de link nooit met anderen. MEBRANDING vraagt je nooit om je wachtwoord.</td></tr>
</table>
@endif
@endsection

@if (! empty($naschrift))
@section('na'){{ $naschrift }}@endsection
@endif
