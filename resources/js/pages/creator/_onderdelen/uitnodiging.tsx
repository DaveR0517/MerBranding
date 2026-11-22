import { useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    FileSignature,
    Gift,
    MapPin,
} from 'lucide-react';
import { useState } from 'react';
import { datumTijd } from '@/lib/datum';
import { accepteren, weigeren } from '@/routes/creator';
import type { SamenwerkingVol } from '@/types/samenwerking';
import { Kaart, Uitklap } from '@/components/pagina';
import {
    AanZet,
    Fout,
    TitelKaart,
    groteKnop,
    rustigeKnop,
    veld,
} from './opmaak';

type Props = {
    samenwerking: SamenwerkingVol;
    uitnodiging: { id: number; expires_at: string };
    contract: { version: string; text: string };
};

/** Open uitnodiging: wat je doet en krijgt, het contract tekenen, of afslaan. */
export function Uitnodiging({ samenwerking: s, uitnodiging, contract }: Props) {
    const fouten = usePage().props.errors as Record<string, string>;

    return (
        <div className="space-y-4">
            <Kaart className="border-violet">
                <AanZet>
                    <span className="text-rood">
                        Reageer voor {datumTijd(uitnodiging.expires_at)}
                    </span>
                </AanZet>
                <p
                    className="flex items-center gap-2 font-heading text-xl font-extrabold text-violet md:text-2xl"
                    data-test="beloning"
                >
                    <Gift className="size-6 shrink-0" aria-hidden />
                    {s.reward}
                </p>
                {s.experience && (
                    <p className="mt-2 text-sm">
                        <span className="font-semibold">Bij je bezoek: </span>
                        {s.experience}
                    </p>
                )}
                <p className="mt-2 flex items-start gap-2 text-sm">
                    <MapPin
                        className="mt-0.5 size-4 shrink-0 text-violet"
                        aria-hidden
                    />
                    <span>
                        <strong>{s.location}</strong>, {s.location_address}
                    </span>
                </p>
                <p className="mt-2 text-sm text-muted-foreground">
                    {s.fee_cents > 0
                        ? 'Je vergoeding staat klaar zodra je post live staat en wordt uitbetaald in de betaalrun op vrijdag.'
                        : 'Je bezoek is gratis, betaald door de ondernemer.'}{' '}
                    Accepteren doe je door het contract hieronder te tekenen.
                </p>
            </Kaart>

            {s.alcohol && (
                <div
                    role="note"
                    className="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 md:p-4"
                >
                    <AlertTriangle
                        className="mt-0.5 size-5 shrink-0"
                        aria-hidden
                    />
                    <p>
                        <strong>Er komt alcohol in beeld.</strong> Je plaatst
                        deze video daarom alleen op Instagram, niet op TikTok.
                    </p>
                </div>
            )}

            <Uitklap titel="Wat je doet">
                <p className="whitespace-pre-line">{s.briefing}</p>
                {(s.period_from || s.period_to) && (
                    <p className="mt-2 text-sm text-muted-foreground">
                        Periode:{' '}
                        {s.period_from
                            ? new Date(s.period_from).toLocaleDateString(
                                  'nl-NL',
                                  { day: 'numeric', month: 'long' },
                              )
                            : 'nu'}
                        {' tot '}
                        {s.period_to
                            ? new Date(s.period_to).toLocaleDateString(
                                  'nl-NL',
                                  { day: 'numeric', month: 'long' },
                              )
                            : 'nader te bepalen'}
                    </p>
                )}
            </Uitklap>

            <Uitklap titel="Zo gaat het verder">
                <ol className="space-y-2 text-sm">
                    {[
                        'Je accepteert door het contract te tekenen.',
                        'Daarna kies je een moment voor je bezoek, dat staat direct vast.',
                        'Na je bezoek lever je de video aan. Het team controleert hem dezelfde werkdag.',
                        'Na goedkeuring plaats je hem op de afgesproken datum op je eigen account.',
                    ].map((t, i) => (
                        <li key={i} className="flex gap-3">
                            <span className="grid size-6 shrink-0 place-items-center rounded-full bg-violet/10 text-xs font-bold text-violet tabular-nums">
                                {i + 1}
                            </span>
                            {t}
                        </li>
                    ))}
                </ol>
            </Uitklap>

            <Fout tekst={fouten.uitnodiging} />
            <Tekenen
                samenwerking={s}
                uitnodiging={uitnodiging}
                contract={contract}
            />
            <Afslaan uitnodigingId={uitnodiging.id} />
        </div>
    );
}

function Tekenen({ uitnodiging, contract }: Props) {
    const form = useForm({ signed_name: '', akkoord: false });

    return (
        <TitelKaart
            aanZet
            titel={
                <span className="flex items-center gap-2">
                    <FileSignature className="size-5 text-violet" aria-hidden />
                    Contract tekenen
                </span>
            }
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(accepteren.url(uitnodiging.id), {
                        preserveScroll: true,
                    });
                }}
                className="space-y-4"
            >
                <div
                    className="max-h-56 overflow-y-auto rounded-xl bg-muted p-3 text-sm leading-relaxed"
                    tabIndex={0}
                    aria-label="Contracttekst"
                >
                    <p className="mb-1 text-xs font-semibold text-muted-foreground">
                        Versie {contract.version}
                    </p>
                    <p className="whitespace-pre-line">{contract.text}</p>
                </div>

                <div>
                    <label
                        htmlFor="signed_name"
                        className="mb-1 block text-sm font-semibold"
                    >
                        Je volledige naam
                    </label>
                    <input
                        id="signed_name"
                        name="signed_name"
                        autoComplete="name"
                        required
                        value={form.data.signed_name}
                        onChange={(e) =>
                            form.setData('signed_name', e.target.value)
                        }
                        className={`${veld} font-heading text-lg italic`}
                        aria-invalid={!!form.errors.signed_name}
                    />
                    <p className="mt-1 text-xs text-muted-foreground">
                        Typen is tekenen. We bewaren je naam, het tijdstip en de
                        versie van het contract.
                    </p>
                </div>
                <Fout tekst={form.errors.signed_name} />

                <label className="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-border p-3 has-[:checked]:border-violet has-[:checked]:bg-violet/5">
                    <input
                        type="checkbox"
                        name="akkoord"
                        required
                        checked={form.data.akkoord}
                        onChange={(e) =>
                            form.setData('akkoord', e.target.checked)
                        }
                        className="mt-0.5 size-5 shrink-0 accent-violet"
                    />
                    <span className="text-sm">
                        Ik heb het contract gelezen en ga akkoord.
                    </span>
                </label>
                <Fout tekst={form.errors.akkoord} />

                <button
                    type="submit"
                    className={groteKnop}
                    disabled={form.processing}
                >
                    <Check className="size-5" aria-hidden />
                    Teken en accepteer
                </button>
            </form>
        </TitelKaart>
    );
}

function Afslaan({ uitnodigingId }: { uitnodigingId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    if (!open) {
        return (
            <button
                type="button"
                className={rustigeKnop}
                onClick={() => setOpen(true)}
            >
                Afslaan
            </button>
        );
    }

    return (
        <TitelKaart titel="Uitnodiging afslaan">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(weigeren.url(uitnodigingId));
                }}
                className="space-y-3"
            >
                <p className="text-sm">
                    Afslaan heeft geen gevolg voor je score.
                </p>
                <div>
                    <label
                        htmlFor="reason"
                        className="mb-1 block text-sm font-semibold"
                    >
                        Reden (mag je leeg laten)
                    </label>
                    <textarea
                        id="reason"
                        rows={3}
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                        className={veld}
                        maxLength={500}
                    />
                </div>
                <Fout tekst={form.errors.reason} />
                <div className="grid gap-2 sm:grid-cols-2">
                    <button
                        type="button"
                        className={rustigeKnop}
                        onClick={() => setOpen(false)}
                    >
                        Toch niet
                    </button>
                    <button
                        type="submit"
                        className={`${rustigeKnop} border-rood/40 text-rood hover:border-rood`}
                        disabled={form.processing}
                    >
                        Afslaan bevestigen
                    </button>
                </div>
            </form>
        </TitelKaart>
    );
}
