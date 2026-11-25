import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Archive,
    ArrowLeft,
    ChevronRight,
    ImageOff,
    Inbox,
    Lock,
    Mail as MailIcon,
    Paperclip,
    Search,
    ShieldCheck,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Kaart, Leeg, Pagina, PaginaKop } from '@/components/pagina';
import { datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import * as kanalen from '@/routes/team/kanalen';
import * as mailActies from '@/routes/team/kanalen/mail';

type Postvak = {
    postvak: string;
    soort: 'eigen' | 'gedeeld' | 'persoonlijk';
    eigenaar: string | null;
    akkoord_op: string | null;
    zichtbaar: boolean;
    kan_akkoord: boolean;
};
type Persoon = { naam: string; email: string };
type Gesprek = {
    id: string;
    onderwerp: string;
    van: Persoon;
    datum: string | null;
    fragment: string;
    aantal: number;
    ongelezen: boolean;
};
type Bericht = {
    id: string;
    van: Persoon;
    aan: string;
    cc: string;
    datum: string | null;
    html: string | null;
    tekst: string | null;
    bijlagen: { naam: string; grootte: number; id: string; type: string }[];
};

type Props = {
    ingesteld: boolean;
    postvakken: Postvak[];
    gekozen: string | null;
    zoek: string | null;
    inbox: { gesprekken: Gesprek[]; volgende: string | null };
    gesprek: { id: string; onderwerp: string; berichten: Bericht[] } | null;
    fout: string | null;
};

const SOORT: Record<Postvak['soort'], string> = {
    eigen: 'Jouw postvak',
    gedeeld: 'Gedeeld',
    persoonlijk: 'Persoonlijk',
};

/** Dagelijks, Mail: de Gmail-postvakken van MEBRANDING. Mail wordt live bij Google opgehaald, niet bewaard. */
export default function MailPagina({
    ingesteld,
    postvakken,
    gekozen,
    zoek,
    inbox,
    gesprek,
    fout,
}: Props) {
    const eigen = postvakken.filter((p) => p.kan_akkoord);
    const params = (extra: Record<string, string | null | undefined>) =>
        Object.fromEntries(
            Object.entries({ postvak: gekozen, zoek, ...extra }).filter(
                ([, v]) => v,
            ),
        ) as Record<string, string>;

    return (
        <>
            <Head title="Mail" />
            <Pagina>
                <PaginaKop
                    boven="Dagelijks"
                    titel="Mail"
                    omschrijving="De Gmail-postvakken van MEBRANDING. Mail wordt live bij Google opgehaald en niet in het portaal bewaard."
                />

                {!ingesteld && <NietIngesteld />}
                {eigen.map((p) => (
                    <Akkoord key={p.postvak} p={p} />
                ))}

                {ingesteld && (
                    <>
                        <nav
                            className="flex flex-wrap gap-2"
                            aria-label="Postvakken"
                        >
                            {postvakken.map((p) =>
                                p.zichtbaar ? (
                                    <Link
                                        key={p.postvak}
                                        href={kanalen.mail.url({
                                            query: { postvak: p.postvak },
                                        })}
                                        aria-current={
                                            p.postvak === gekozen
                                                ? 'page'
                                                : undefined
                                        }
                                        className={cn(
                                            'inline-flex min-h-10 items-center gap-2 rounded-full border px-3 text-sm font-semibold md:min-h-9',
                                            p.postvak === gekozen
                                                ? 'border-aubergine bg-aubergine text-white'
                                                : 'bg-card hover:border-violet',
                                        )}
                                    >
                                        <MailIcon
                                            className="size-4"
                                            aria-hidden
                                        />
                                        {p.postvak}
                                    </Link>
                                ) : (
                                    <span
                                        key={p.postvak}
                                        className="inline-flex min-h-10 items-center gap-2 rounded-full border border-dashed px-3 text-sm text-muted-foreground md:min-h-9"
                                        title="Nog geen akkoord"
                                    >
                                        <Lock className="size-4" aria-hidden />
                                        {p.postvak}: wacht op akkoord van{' '}
                                        {p.eigenaar ?? 'de eigenaar'}
                                    </span>
                                ),
                            )}
                        </nav>

                        {fout && (
                            <p
                                role="alert"
                                className="rounded-xl bg-rood/10 p-4 text-sm text-rood"
                            >
                                {fout}
                            </p>
                        )}

                        {gekozen && !fout && (
                            <div className="grid gap-4 @4xl:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
                                <div
                                    className={cn(
                                        'space-y-3',
                                        gesprek && 'hidden @4xl:block',
                                    )}
                                >
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            const q = new FormData(
                                                e.currentTarget,
                                            ).get('zoek') as string;
                                            router.get(
                                                kanalen.mail.url(),
                                                {
                                                    postvak: gekozen,
                                                    ...(q ? { zoek: q } : {}),
                                                },
                                                { preserveState: true },
                                            );
                                        }}
                                        className="relative"
                                    >
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden
                                        />
                                        <label
                                            htmlFor="zoek-mail"
                                            className="sr-only"
                                        >
                                            Zoeken in {gekozen}
                                        </label>
                                        <input
                                            id="zoek-mail"
                                            name="zoek"
                                            type="search"
                                            defaultValue={zoek ?? ''}
                                            placeholder="Zoeken, zoals in Gmail"
                                            className="h-10 w-full rounded-lg border bg-card pr-3 pl-9 text-sm"
                                        />
                                    </form>
                                    <Kaart className="p-0">
                                        {inbox.gesprekken.length === 0 ? (
                                            <div className="p-4">
                                                <Leeg
                                                    titel={
                                                        zoek
                                                            ? 'Niets gevonden.'
                                                            : 'De inbox is leeg.'
                                                    }
                                                />
                                            </div>
                                        ) : (
                                            <ul
                                                className="divide-y"
                                                data-test="mail-lijst"
                                            >
                                                {inbox.gesprekken.map((g) => (
                                                    <li key={g.id}>
                                                        <Link
                                                            href={kanalen.mail.url(
                                                                {
                                                                    query: params(
                                                                        {
                                                                            gesprek:
                                                                                g.id,
                                                                        },
                                                                    ),
                                                                },
                                                            )}
                                                            preserveScroll
                                                            className={cn(
                                                                'block px-4 py-3 hover:bg-muted',
                                                                gesprek?.id ===
                                                                    g.id &&
                                                                    'bg-violet/5',
                                                            )}
                                                        >
                                                            <span className="flex items-baseline justify-between gap-2">
                                                                <span
                                                                    className={cn(
                                                                        'truncate text-sm',
                                                                        g.ongelezen
                                                                            ? 'font-bold'
                                                                            : 'font-medium',
                                                                    )}
                                                                >
                                                                    {g.van.naam}
                                                                    {g.aantal >
                                                                        1 && (
                                                                        <span className="ml-1 text-xs text-muted-foreground">
                                                                            {
                                                                                g.aantal
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </span>
                                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                                    {g.datum
                                                                        ? datumTijd(
                                                                              g.datum,
                                                                          )
                                                                        : ''}
                                                                </span>
                                                            </span>
                                                            <span
                                                                className={cn(
                                                                    'block truncate text-sm',
                                                                    g.ongelezen &&
                                                                        'font-semibold',
                                                                )}
                                                            >
                                                                {g.ongelezen && (
                                                                    <span
                                                                        className="mr-1.5 inline-block size-2 rounded-full bg-violet align-middle"
                                                                        aria-label="ongelezen"
                                                                    />
                                                                )}
                                                                {g.onderwerp}
                                                            </span>
                                                            <span className="block truncate text-xs text-muted-foreground">
                                                                {g.fragment}
                                                            </span>
                                                        </Link>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        {inbox.volgende && (
                                            <Link
                                                href={kanalen.mail.url({
                                                    query: params({
                                                        pagina: inbox.volgende,
                                                    }),
                                                })}
                                                className="flex items-center justify-center gap-1 border-t py-2.5 text-sm font-semibold text-violet"
                                            >
                                                Oudere mail{' '}
                                                <ChevronRight
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            </Link>
                                        )}
                                    </Kaart>
                                </div>

                                <div
                                    className={cn(
                                        !gesprek && 'hidden @4xl:block',
                                    )}
                                >
                                    {gesprek ? (
                                        <GesprekWeergave
                                            postvak={gekozen}
                                            gesprek={gesprek}
                                            terug={kanalen.mail.url({
                                                query: params({}),
                                            })}
                                        />
                                    ) : (
                                        <Kaart className="grid min-h-64 place-items-center text-center text-sm text-muted-foreground">
                                            <span>
                                                <Inbox
                                                    className="mx-auto mb-2 size-8"
                                                    aria-hidden
                                                />
                                                Kies een mail om te lezen.
                                            </span>
                                        </Kaart>
                                    )}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </Pagina>
        </>
    );
}

function NietIngesteld() {
    return (
        <Kaart>
            <h2 className="font-heading text-lg font-bold">
                Gmail is nog niet gekoppeld
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                De Workspace-beheerder geeft het portaal eenmalig toegang
                (service-account met domeinbrede delegatie) en zet de sleutel in
                Forge. Daarna verschijnen de postvakken hier.
            </p>
        </Kaart>
    );
}

function Akkoord({ p }: { p: Postvak }) {
    const verstuur = (akkoord: boolean) =>
        router.post(
            mailActies.akkoord.url(p.postvak),
            { akkoord },
            { preserveScroll: true },
        );

    return (
        <Kaart
            className={cn(
                'flex flex-wrap items-center justify-between gap-3',
                !p.akkoord_op && 'border-violet/40 bg-violet/5',
            )}
            data-test="postvak-akkoord"
        >
            <div className="flex min-w-48 flex-1 gap-3">
                <ShieldCheck
                    className="mt-0.5 size-5 shrink-0 text-violet"
                    aria-hidden
                />
                <div className="text-sm">
                    <p className="font-semibold">Jouw postvak {p.postvak}</p>
                    <p className="text-muted-foreground">
                        {p.akkoord_op
                            ? `Je gaf op ${datumTijd(p.akkoord_op)} akkoord: de eigenaren van MEBRANDING (Dave en Taipan) kunnen dit postvak in het portaal lezen en beantwoorden. Je kunt dit altijd intrekken.`
                            : 'Mogen de eigenaren van MEBRANDING (Dave en Taipan) dit postvak in het portaal lezen en beantwoorden? Zonder jouw akkoord zie alleen jij het.'}
                    </p>
                </div>
            </div>
            {p.akkoord_op ? (
                <button
                    type="button"
                    onClick={() => verstuur(false)}
                    className="min-h-10 rounded-full border px-4 text-sm font-semibold hover:border-rood hover:text-rood"
                >
                    Akkoord intrekken
                </button>
            ) : (
                <button
                    type="button"
                    onClick={() => verstuur(true)}
                    className="min-h-10 rounded-full bg-violet px-4 text-sm font-semibold text-white hover:bg-violet/90"
                >
                    Akkoord geven
                </button>
            )}
        </Kaart>
    );
}

function GesprekWeergave({
    postvak,
    gesprek,
    terug,
}: {
    postvak: string;
    gesprek: NonNullable<Props['gesprek']>;
    terug: string;
}) {
    const [beelden, setBeelden] = useState(false);
    const form = useForm({ tekst: '' });
    const laatste = gesprek.berichten.at(-1);

    return (
        <Kaart className="space-y-4" data-test="mail-gesprek">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <Link
                        href={terug}
                        className="mb-1 inline-flex items-center gap-1 text-sm font-semibold text-violet @4xl:hidden"
                    >
                        <ArrowLeft className="size-4" aria-hidden /> Inbox
                    </Link>
                    <h2 className="font-heading text-lg font-bold break-words">
                        {gesprek.onderwerp}
                    </h2>
                </div>
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => setBeelden((b) => !b)}
                        className="inline-flex min-h-9 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold"
                        aria-pressed={beelden}
                    >
                        <ImageOff className="size-4" aria-hidden />{' '}
                        {beelden
                            ? 'Afbeeldingen verbergen'
                            : 'Afbeeldingen tonen'}
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            router.post(
                                mailActies.archiveren.url([
                                    postvak,
                                    gesprek.id,
                                ]),
                            )
                        }
                        className="inline-flex min-h-9 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold hover:border-violet"
                    >
                        <Archive className="size-4" aria-hidden /> Archiveren
                    </button>
                </div>
            </div>

            {gesprek.berichten.map((b) => (
                <article key={b.id} className="rounded-xl border p-3">
                    <header className="mb-2 flex flex-wrap items-baseline justify-between gap-x-3 text-sm">
                        <span>
                            <b>{b.van.naam}</b>{' '}
                            <span className="text-muted-foreground">
                                &lt;{b.van.email}&gt;
                            </span>
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {b.datum ? datumTijd(b.datum) : ''}
                        </span>
                        <span className="w-full truncate text-xs text-muted-foreground">
                            Aan: {b.aan}
                            {b.cc ? ` · Cc: ${b.cc}` : ''}
                        </span>
                    </header>
                    {b.html ? (
                        <Inhoud html={b.html} beelden={beelden} />
                    ) : (
                        <p className="text-sm whitespace-pre-wrap">{b.tekst}</p>
                    )}
                    {b.bijlagen.length > 0 && (
                        <ul className="mt-2 flex flex-wrap gap-2">
                            {b.bijlagen.map((x) => (
                                <li key={x.id}>
                                    <a
                                        href={mailActies.bijlage.url(
                                            [postvak, b.id, x.id],
                                            { query: { naam: x.naam } },
                                        )}
                                        className="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold hover:border-violet"
                                    >
                                        <Paperclip
                                            className="size-3.5"
                                            aria-hidden
                                        />{' '}
                                        {x.naam} (
                                        {Math.max(
                                            1,
                                            Math.round(x.grootte / 1024),
                                        )}{' '}
                                        kB)
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </article>
            ))}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(mailActies.antwoord.url([postvak, gesprek.id]), {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
                className="space-y-2"
            >
                <label htmlFor="antwoord" className="text-sm font-semibold">
                    Antwoord aan {laatste?.van.naam}, vanaf {postvak}
                </label>
                <textarea
                    id="antwoord"
                    value={form.data.tekst}
                    onChange={(e) => form.setData('tekst', e.target.value)}
                    rows={5}
                    className="w-full rounded-lg border bg-background p-3 text-sm"
                    aria-invalid={!!form.errors.tekst}
                />
                {form.errors.tekst && (
                    <p className="text-sm text-rood">{form.errors.tekst}</p>
                )}
                <button
                    type="submit"
                    disabled={form.processing || !form.data.tekst.trim()}
                    className="min-h-10 rounded-full bg-violet px-5 text-sm font-semibold text-white disabled:opacity-50"
                >
                    Versturen
                </button>
            </form>
        </Kaart>
    );
}

/**
 * HTML van een mail in een afgeschermd venster: geen scripts, geen formulieren, links openen in een nieuw tabblad.
 * Afbeeldingen van buiten (vaak volgpixels) pas na een klik op Afbeeldingen tonen.
 */
function Inhoud({ html, beelden }: { html: string; beelden: boolean }) {
    const ref = useRef<HTMLIFrameElement>(null);
    const [hoogte, setHoogte] = useState(200);
    const csp = `default-src 'none'; style-src 'unsafe-inline'; font-src data:; img-src data: cid:${beelden ? ' https: http:' : ''}`;
    const doc = `<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${csp}"><base target="_blank"><style>body{margin:0;font:14px/1.5 system-ui,sans-serif;color:#2E2B48;word-break:break-word}img{max-width:100%;height:auto}</style></head><body>${html}</body></html>`;

    useEffect(() => {
        const iframe = ref.current;
        const meet = () =>
            setHoogte(
                Math.min(
                    4000,
                    (iframe?.contentDocument?.documentElement.scrollHeight ??
                        200) + 8,
                ),
            );
        iframe?.addEventListener('load', meet);

        return () => iframe?.removeEventListener('load', meet);
    }, [doc]);

    return (
        <iframe
            ref={ref}
            title="Inhoud van de mail"
            srcDoc={doc}
            sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
            className="w-full rounded-md bg-white"
            style={{ height: hoogte }}
        />
    );
}
