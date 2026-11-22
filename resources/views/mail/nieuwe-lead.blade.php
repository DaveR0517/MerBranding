@extends('mail.layout', [
    'onderwerp' => $lead->isOndernemer() ? 'Nieuwe kennismaking ondernemer' : 'Nieuwe aanmelding creator',
    'preheader' => $lead->name.($lead->company ? ', '.$lead->company : ''),
    'aanhef' => 'Er is een nieuwe aanmelding binnengekomen via de website.',
    'voettekst' => 'Interne melding van mebranding.nl. Beantwoorden gaat direct naar de aanvrager.',
])

@section('alineas')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;font-size:14px;line-height:21px;">
@foreach (array_filter([
    'Naam' => $lead->name,
    'E-mail' => $lead->email,
    'Telefoon' => $lead->phone,
    'Bedrijf' => $lead->company,
    'Plaats' => $lead->city,
    'Bericht' => $lead->message,
]) + collect($lead->data ?? [])->mapWithKeys(fn ($v, $k) => [ucfirst(str_replace(['-', '_'], ' ', $k)) => is_array($v) ? implode(', ', $v) : $v])->filter(fn ($v) => $v !== null && $v !== '')->all() as $label => $waarde)
<tr>
<td valign="top" style="padding:6px 12px 6px 0;color:#6B6880;width:130px;border-bottom:1px solid #EEEDF3;">{{ $label }}</td>
<td valign="top" style="padding:6px 0;border-bottom:1px solid #EEEDF3;">{!! nl2br(e($waarde)) !!}</td>
</tr>
@endforeach
</table>
<p style="margin:0 0 14px;color:#6B6880;font-size:13px;">Ontvangen op {{ $lead->created_at->timezone('Europe/Amsterdam')->format('d-m-Y H:i') }}. Akkoord privacy: versie {{ $lead->consent_version }}.</p>
@endsection
