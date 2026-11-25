@php($siteUrl = 'https://'.config('mebranding.domains.site'))
<!DOCTYPE html>
<html lang="nl" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ $onderwerp }}</title>
{{-- MEBRANDING e-mailopmaak, gebaseerd op brand/email/email-sjabloon.html (concept 18-11-2026). --}}
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<style>table,td,div,p,a{font-family:Arial,Helvetica,sans-serif !important;}</style>
<![endif]-->
<style>
  body{margin:0;padding:0;width:100% !important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}
  table{border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;}
  img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;display:block;}
  a{color:#4B39C7;}
  @media screen and (max-width:620px){
    .mb-wrap{width:100% !important;}
    .mb-pad{padding-left:20px !important;padding-right:20px !important;}
    .mb-h1{font-size:20px !important;line-height:26px !important;}
    .mb-btn a{display:block !important;}
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:#F5F5F7;">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#F5F5F7;">{{ $preheader ?? '' }}&#8204;&nbsp;&#8204;&nbsp;&#8204;&nbsp;&#8204;&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F5F5F7;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
      <table role="presentation" class="mb-wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background-color:#FFFFFF;border:1px solid #E2E1EA;">
        <!-- kop: logo op aubergine -->
        <tr>
          <td class="mb-pad" bgcolor="#3A3659" style="background-color:#3A3659;padding:20px 32px;">
            <a href="{{ $siteUrl }}" style="text-decoration:none;"><img src="{{ $siteUrl }}/brand/email-logo-wit.png" width="200" height="32" alt="MEBRANDING" style="width:200px;height:32px;color:#FFFFFF;font-family:'Montserrat','Arial Black',Arial,sans-serif;font-weight:900;font-size:18px;"></a>
          </td>
        </tr>
        <!-- merkstreep: rood naar violet (Outlook toont twee vlakken) -->
        <tr>
          <td style="padding:0;font-size:0;line-height:0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
              <td width="50%" height="4" bgcolor="#D9303E" style="background-color:#D9303E;background-image:linear-gradient(90deg,#D9303E,#614ED9);height:4px;font-size:0;line-height:0;">&nbsp;</td>
              <td width="50%" height="4" bgcolor="#614ED9" style="background-color:#614ED9;background-image:linear-gradient(90deg,#9A3F8C,#614ED9);height:4px;font-size:0;line-height:0;">&nbsp;</td>
            </tr></table>
          </td>
        </tr>
        <!-- inhoud -->
        <tr>
          <td class="mb-pad" style="padding:32px 32px 8px;font-family:'Noto Sans',Arial,Helvetica,sans-serif;font-size:15px;line-height:24px;color:#22203A;">
            @if (! empty($label))<p style="margin:0 0 8px;font-family:'Noto Sans',Arial,Helvetica,sans-serif;font-size:12px;line-height:16px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:{{ $labelKleur ?? '#614ED9' }};">{{ $label }}</p>@endif
            <h1 class="mb-h1" style="margin:0 0 16px;font-family:'Montserrat','Arial Black',Arial,sans-serif;font-weight:900;font-size:22px;line-height:28px;color:#22203A;">{{ $onderwerp }}</h1>
            <p style="margin:0 0 14px;">{{ $aanhef }}</p>
            @yield('alineas')
          </td>
        </tr>
        @isset($knopLink)
        <!-- hoofdknop: VML voor Outlook, gewone link voor de rest -->
        <tr>
          <td class="mb-pad mb-btn" style="padding:8px 32px 28px;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $knopLink }}" style="height:46px;v-text-anchor:middle;width:260px;" arcsize="9%" stroke="f" fillcolor="#614ED9">
              <w:anchorlock/><center style="color:#FFFFFF;font-family:Arial,sans-serif;font-size:15px;font-weight:bold;">{{ $knopTekst }}</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <a href="{{ $knopLink }}" style="display:inline-block;background-color:#614ED9;color:#FFFFFF;font-family:'Noto Sans',Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;line-height:46px;padding:0 24px;border-radius:4px;text-decoration:none;mso-hide:all;">{{ $knopTekst }}</a>
            <!--<![endif]-->
          </td>
        </tr>
        @endisset
        @hasSection('na')
        <tr>
          <td class="mb-pad" style="padding:0 32px 20px;font-family:'Noto Sans',Arial,Helvetica,sans-serif;font-size:13px;line-height:21px;color:#6B6880;">@yield('na')</td>
        </tr>
        @endif
        <!-- afsluiting -->
        <tr>
          <td class="mb-pad" style="padding:0 32px 28px;font-family:'Noto Sans',Arial,Helvetica,sans-serif;font-size:15px;line-height:24px;color:#22203A;">Groet,<br>Team MEBRANDING</td>
        </tr>
        <!-- voet -->
        <tr>
          <td class="mb-pad" bgcolor="#F5F5F7" style="background-color:#F5F5F7;border-top:1px solid #E2E1EA;padding:18px 32px 22px;font-family:'Noto Sans',Arial,Helvetica,sans-serif;font-size:12px;line-height:19px;color:#6B6880;">
            {{ $voettekst ?? 'Je krijgt deze e-mail omdat je een formulier invulde op mebranding.nl.' }}<br>
            MEBRANDING &nbsp;·&nbsp; <a href="{{ $siteUrl }}" style="color:#4B39C7;">mebranding.nl</a>
          </td>
        </tr>
      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body>
</html>
