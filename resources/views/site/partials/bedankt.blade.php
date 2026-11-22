{{-- Bedankmelding na versturen. $volgende: lijst [titel, uitleg] met wat er nu gebeurt. --}}
@if (session('aangemeld'))
    <div class="ok" role="status" tabindex="-1" id="bedankt">
        <h3><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1E6B3A" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12.5l5 5L20 6.5"/></svg>Dank je!</h3>
        <p>We hebben je aanvraag ontvangen. Je krijgt een bevestiging per e-mail.</p>
        @isset($volgende)
            <ol class="next">
                @foreach ($volgende as $i => [$titel, $uitleg])
                    <li><span aria-hidden="true">{{ $i + 1 }}</span><div><b>{{ $titel }}</b><small>{{ $uitleg }}</small></div></li>
                @endforeach
            </ol>
        @endisset
    </div>
@endif
