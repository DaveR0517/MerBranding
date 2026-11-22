import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import {
    CalendarCheck,
    Check,
    Copy,
    Download,
    ExternalLink,
    MapPin,
    Star,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Kaart, Pagina, PaginaKop, Uitklap } from '@/components/pagina';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { Tijdlijn } from '@/components/samenwerking/tijdlijn';
import { Snelkoppelingen } from '@/components/samenwerking/snelkoppelingen';
import {
    FeedbackLijst,
    VideoSpeler,
} from '@/components/samenwerking/video-speler';
import type { VideoSpelerHandle } from '@/components/samenwerking/video-speler';
import { datum, datumTijd, euro } from '@/lib/datum';
import {
    inchecken,
    klaar,
    klus as klusRoute,
    live,
    overzicht,
} from '@/routes/creator';
import type { SamenwerkingVol } from '@/types/samenwerking';
import {
    AanZet,
    Fout,
    TitelKaart,
    Uitkomst,
    groteKnop,
    rustigeKnop,
    veld,
} from './_onderdelen/opmaak';
import { TijdvakKiezer } from './_onderdelen/tijdvak-kiezer';
import { Uitnodiging } from './_onderdelen/uitnodiging';
import { UploadFormulier } from './_onderdelen/upload';

type Props = {
    collaboration: SamenwerkingVol;
    invitation: { id: number; expires_at: string } | null;
    contract: { version: string; text: string };
    video_max_mb: number;
};

export default function Klus({
    collaboration: s,
    invitation,
    contract,
    video_max_mb,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Mijn klussen', href: overzicht() },
            { title: s.company, href: klusRoute(s.id) },
        ],
    });

    return (
        <>
            <Head title={`${s.company}: ${s.title}`} />
            <Pagina smal>
                <PaginaKop
                    boven={
                        invitation ? 'Nieuwe uitnodiging' : `Klus ${s.number}`
                    }
                    titel={s.company}
                    omschrijving={`${s.title}, ${s.location}`}
                    extra={<StatusBadge status={s.status} />}
                />

                {invitation ? (
                    <Uitnodiging
                        samenwerking={s}
                        uitnodiging={invitation}
                        contract={contract}
                    />
                ) : (
                    <>
                        <Stap samenwerking={s} maxMb={video_max_mb} />
                        <Snelkoppelingen
                            omgeving="creator"
                            id={s.id}
                            status={s.status.value}
                        />
                    </>
                )}

                <div className="space-y-3">
                    {s.status.number !== null && (
                        <Uitklap
                            titel="Voortgang"
                            rechts={
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    Stap {s.status.number} van {s.steps.length}
                                </span>
                            }
                        >
                            <TijdlijnInBeeld samenwerking={s} />
                        </Uitklap>
                    )}
                    {!invitation && s.briefing && (
                        <Uitklap titel="Briefing en vestiging">
                            <div className="space-y-2">
                                <p className="whitespace-pre-line">
                                    {s.briefing}
                                </p>
                                {s.experience && (
                                    <p className="text-sm">
                                        <span className="font-semibold">
                                            Bij je bezoek:{' '}
                                        </span>
                                        {s.experience}
                                    </p>
                                )}
                                <p className="text-sm text-muted-foreground">
                                    {s.location}, {s.location_address}
                                </p>
                                <p className="text-sm">
                                    <span className="font-semibold">
                                        Je krijgt:{' '}
                                    </span>
                                    {s.reward}
                                </p>
                            </div>
                        </Uitklap>
                    )}
                </div>
            </Pagina>
        </>
    );
}

/** Precies de volgende stap voor de huidige status. */
function Stap({
    samenwerking: s,
    maxMb,
}: {
    samenwerking: SamenwerkingVol;
    maxMb: number;
}) {
    switch (s.status.value) {
        case 'creator_gevonden':
            return (
                <TitelKaart titel="Kies je moment" aanZet>
                    <AanZet>Jij bent aan zet</AanZet>
                    <p className="mb-4 text-sm">
                        Je hebt getekend, top. Kies een moment binnen de tijden
                        van {s.location}.
                    </p>
                    <TijdvakKiezer samenwerking={s} />
                </TitelKaart>
            );
        case 'bezoek_gepland':
            return <BezoekGepland samenwerking={s} />;
        case 'bezoek_geweest':
            return <KlaarMetFilmen samenwerking={s} />;
        case 'content_maken':
        case 'feedback':
            return <ContentMaken samenwerking={s} maxMb={maxMb} />;
        case 'ter_controle':
            return (
                <TitelKaart
                    titel="Je video wordt gecontroleerd, vandaag nog"
                    aanZet
                >
                    <p className="mb-4 text-sm text-muted-foreground">
                        Je krijgt een melding zodra het team klaar is. Je hoeft
                        nu niets te doen.
                    </p>
                    {laatste(s) && (
                        <div className={smalleVideo}>
                            <VideoSpeler versie={laatste(s)!} />
                        </div>
                    )}
                    {laatste(s) && (
                        <p className="mt-3 rounded-xl bg-muted p-3 text-sm whitespace-pre-line">
                            {laatste(s)!.caption}
                        </p>
                    )}
                </TitelKaart>
            );
        case 'goedgekeurd':
            return <Goedgekeurd samenwerking={s} />;
        case 'live':
            return <Live samenwerking={s} />;
        case 'afgerond':
            return (
                <TitelKaart titel="Afgerond" aanZet>
                    <p className="mb-3">
                        De meetperiode is voorbij. Dank je wel voor je video!
                    </p>
                    {s.review ? (
                        <div className="rounded-xl bg-muted p-4">
                            <p className="text-sm font-semibold">
                                Beoordeling van {s.company}
                            </p>
                            <p
                                className="my-1 flex gap-0.5"
                                aria-label={`${s.review.rating} van 5 sterren`}
                            >
                                {[1, 2, 3, 4, 5].map((n) => (
                                    <Star
                                        key={n}
                                        aria-hidden
                                        className={`size-6 ${n <= s.review!.rating ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/40'}`}
                                    />
                                ))}
                            </p>
                            {s.review.body && (
                                <p className="whitespace-pre-line">
                                    {s.review.body}
                                </p>
                            )}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {s.company} heeft nog geen beoordeling gegeven.
                        </p>
                    )}
                    <Vergoeding samenwerking={s} />
                </TitelKaart>
            );
        case 'geannuleerd':
            return (
                <TitelKaart titel="Deze klus gaat niet door">
                    <p>
                        De samenwerking is geannuleerd. Je hoeft niets meer te
                        doen.
                    </p>
                    {s.cancel_reason && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Reden: {s.cancel_reason}
                        </p>
                    )}
                </TitelKaart>
            );
        case 'niet_verschenen':
            return (
                <TitelKaart titel="Niet verschenen">
                    <p>
                        Je was er niet op het afgesproken moment, dus deze klus
                        is gestopt. Klopt dat niet? Neem contact op met het
                        team.
                    </p>
                    {s.cancel_reason && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            {s.cancel_reason}
                        </p>
                    )}
                </TitelKaart>
            );
        default:
            return (
                <Kaart>
                    <p className="text-muted-foreground">
                        Het team is hiermee bezig. Je krijgt een melding als je
                        aan zet bent.
                    </p>
                </Kaart>
            );
    }
}

/** Staande video niet schermvullend op de telefoon, zodat de knoppen eronder in zicht blijven. */
const smalleVideo = 'mx-auto w-full max-w-[260px]';

/** De gedeelde tijdlijn, met de huidige stap in beeld geschoven. */
function TijdlijnInBeeld({
    samenwerking: s,
}: {
    samenwerking: SamenwerkingVol;
}) {
    const doos = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const lijst = doos.current?.querySelector('ol');
        const huidig = lijst?.querySelector<HTMLElement>(
            '[aria-current="step"]',
        );

        if (lijst && huidig) {
            lijst.scrollLeft =
                huidig.offsetLeft -
                lijst.offsetLeft -
                (lijst.clientWidth - huidig.offsetWidth) / 2;
        }
    }, [s.status.value]);

    return (
        <div ref={doos}>
            <Tijdlijn stappen={s.steps} />
        </div>
    );
}

function laatste(s: SamenwerkingVol) {
    return s.versions.at(-1) ?? null;
}

function AdresRegel({ s }: { s: SamenwerkingVol }) {
    return (
        <p className="flex items-start gap-2">
            <MapPin
                className="mt-0.5 size-5 shrink-0 text-violet"
                aria-hidden
            />
            <span>
                <strong>{s.location}</strong>
                <br />
                {s.location_address}
            </span>
        </p>
    );
}

function BezoekGepland({ samenwerking: s }: { samenwerking: SamenwerkingVol }) {
    const form = useForm({});
    const fouten = form.errors as Record<string, string | undefined>;
    const bezoek = s.visit_at ? new Date(s.visit_at) : null;

    return (
        <TitelKaart titel="Je bezoek staat vast" aanZet>
            <AanZet>Jij bent aan zet, op de dag zelf</AanZet>
            {bezoek && (
                <div className="my-3 flex items-center gap-3">
                    <div className="grid w-16 shrink-0 place-items-center rounded-xl bg-violet/10 py-2 text-violet">
                        <span className="text-xs font-bold uppercase">
                            {bezoek.toLocaleDateString('nl-NL', {
                                timeZone: 'Europe/Amsterdam',
                                month: 'short',
                            })}
                        </span>
                        <span className="font-heading text-2xl leading-none font-extrabold">
                            {bezoek.toLocaleDateString('nl-NL', {
                                timeZone: 'Europe/Amsterdam',
                                day: 'numeric',
                            })}
                        </span>
                    </div>
                    <p className="font-heading text-lg font-extrabold">
                        {datumTijd(s.visit_at)}
                    </p>
                </div>
            )}
            <AdresRegel s={s} />
            <p className="mt-3 rounded-xl bg-muted p-3 text-sm">
                Tik op de dag zelf op Ik ben er als je binnen bent. Dan weet{' '}
                {s.company} dat je er bent.
            </p>
            <div className="mt-4 space-y-3">
                <Fout tekst={fouten.checkin} />
                <button
                    type="button"
                    className={groteKnop}
                    disabled={form.processing}
                    onClick={() =>
                        form.post(inchecken.url(s.id), { preserveScroll: true })
                    }
                >
                    <CalendarCheck className="size-5" aria-hidden />
                    Ik ben er
                </button>
                <a
                    className={rustigeKnop}
                    href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${s.company}, ${s.location_address}`)}`}
                    target="_blank"
                    rel="noreferrer"
                >
                    Route naar {s.location}
                </a>
            </div>
        </TitelKaart>
    );
}

function KlaarMetFilmen({
    samenwerking: s,
}: {
    samenwerking: SamenwerkingVol;
}) {
    const form = useForm({});

    return (
        <TitelKaart titel="Je bent binnen, veel plezier" aanZet>
            <AanZet>Jij bent aan zet, aan het filmen</AanZet>
            <p className="text-sm">
                {s.visited_at && <>Ingecheckt op {datumTijd(s.visited_at)}. </>}
                Film volgens de briefing hieronder. Klaar? Tik dan op Klaar met
                filmen, dan start je deadline voor de video.
            </p>
            <button
                type="button"
                className={`${groteKnop} mt-4`}
                disabled={form.processing}
                onClick={() =>
                    form.post(klaar.url(s.id), { preserveScroll: true })
                }
            >
                <Check className="size-5" aria-hidden />
                Klaar met filmen
            </button>
        </TitelKaart>
    );
}

function ContentMaken({
    samenwerking: s,
    maxMb,
}: {
    samenwerking: SamenwerkingVol;
    maxMb: number;
}) {
    const speler = useRef<VideoSpelerHandle>(null);
    const vorige = s.status.value === 'feedback' ? laatste(s) : null;

    return (
        <>
            {vorige && (
                <TitelKaart
                    titel={`Feedback op versie ${vorige.version}`}
                    aanZet
                >
                    <p className="mb-3 text-sm">
                        Tik op een tijdcode om naar dat moment in je video te
                        gaan.
                    </p>
                    <FeedbackLijst
                        punten={vorige.feedback}
                        onKlik={(t) => speler.current?.naar(t)}
                    />
                    <div className={`mt-4 ${smalleVideo}`}>
                        <VideoSpeler versie={vorige} ref={speler} />
                    </div>
                </TitelKaart>
            )}
            <TitelKaart
                titel={vorige ? 'Lever je nieuwe versie aan' : 'Maak je video'}
                aanZet={!vorige}
            >
                <AanZet>Jij bent aan zet</AanZet>
                {s.content_deadline_at && (
                    <p className="mb-4 rounded-xl bg-violet/10 px-3 py-2 font-semibold text-violet">
                        Deadline: {datumTijd(s.content_deadline_at)}
                    </p>
                )}
                <UploadFormulier samenwerking={s} maxMb={maxMb} />
            </TitelKaart>
        </>
    );
}

function Goedgekeurd({ samenwerking: s }: { samenwerking: SamenwerkingVol }) {
    const versie =
        [...s.versions].reverse().find((v) => v.outcome === 'goedgekeurd') ??
        laatste(s);
    const [gekopieerd, setGekopieerd] = useState(false);
    const form = useForm({
        platform: 'instagram' as 'instagram' | 'tiktok',
        url: '',
    });

    async function kopieer() {
        if (!versie) {
            return;
        }

        let gelukt = false;

        try {
            await navigator.clipboard.writeText(versie.caption);
            gelukt = true;
        } catch {
            // Zonder https of toestemming: via een tijdelijk tekstveld.
            const tijdelijk = document.createElement('textarea');
            tijdelijk.value = versie.caption;
            tijdelijk.setAttribute('readonly', '');
            tijdelijk.style.position = 'fixed';
            tijdelijk.style.opacity = '0';
            document.body.appendChild(tijdelijk);
            tijdelijk.select();
            gelukt = document.execCommand('copy');
            tijdelijk.remove();
        }

        setGekopieerd(gelukt);

        if (gelukt) {
            setTimeout(() => setGekopieerd(false), 3000);
        }
    }

    return (
        <>
            <TitelKaart titel="Goedgekeurd, plaats je video" aanZet>
                <AanZet>Jij bent aan zet</AanZet>
                <p className="rounded-xl bg-violet/10 px-3 py-2 font-semibold text-violet">
                    Plaatsen op {datum(s.publish_on)}
                </p>
                {versie && (
                    <div className="mt-4 space-y-4">
                        <div className={smalleVideo}>
                            <VideoSpeler
                                versie={{ ...versie, download_url: null }}
                            />
                        </div>
                        {versie.download_url && (
                            <a
                                href={versie.download_url}
                                className={rustigeKnop}
                                download
                            >
                                <Download className="size-5" aria-hidden />
                                Video downloaden
                            </a>
                        )}
                        <div>
                            <p className="mb-1 text-sm font-semibold">
                                Goedgekeurd bijschrift
                            </p>
                            <p
                                className="rounded-xl bg-muted p-3 text-sm whitespace-pre-line"
                                data-test="bijschrift"
                            >
                                {versie.caption}
                            </p>
                            <button
                                type="button"
                                onClick={kopieer}
                                className={`${rustigeKnop} mt-2`}
                            >
                                {gekopieerd ? (
                                    <Check className="size-5" aria-hidden />
                                ) : (
                                    <Copy className="size-5" aria-hidden />
                                )}
                                {gekopieerd
                                    ? 'Gekopieerd'
                                    : 'Bijschrift kopiëren'}
                            </button>
                        </div>
                    </div>
                )}
                <p className="mt-4 text-sm text-muted-foreground">
                    Zet bij het plaatsen het label Betaalde samenwerking aan
                    (Instagram) of de schakelaar commerciële content (TikTok).
                    {s.alcohol &&
                        ' Er komt alcohol in beeld: plaats deze video alleen op Instagram.'}
                </p>
            </TitelKaart>

            <TitelKaart titel="Ik heb geplaatst">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(live.url(s.id), { preserveScroll: true });
                    }}
                    className="space-y-4"
                >
                    <fieldset>
                        <legend className="mb-2 text-sm font-semibold">
                            Waar staat je post?
                        </legend>
                        <div className="grid grid-cols-2 gap-2">
                            {(s.alcohol
                                ? (['instagram'] as const)
                                : (['instagram', 'tiktok'] as const)
                            ).map((p) => (
                                <label
                                    key={p}
                                    className="flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-border font-semibold has-[:checked]:border-violet has-[:checked]:bg-violet has-[:checked]:text-white"
                                >
                                    <input
                                        type="radio"
                                        name="platform"
                                        value={p}
                                        checked={form.data.platform === p}
                                        onChange={() =>
                                            form.setData('platform', p)
                                        }
                                        className="sr-only"
                                    />
                                    {p === 'instagram' ? 'Instagram' : 'TikTok'}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <Fout tekst={form.errors.platform} />
                    <div>
                        <label
                            htmlFor="url"
                            className="mb-1 block text-sm font-semibold"
                        >
                            Link van je post
                        </label>
                        <input
                            id="url"
                            type="url"
                            inputMode="url"
                            required
                            placeholder={
                                form.data.platform === 'instagram'
                                    ? 'https://www.instagram.com/p/...'
                                    : 'https://www.tiktok.com/@.../video/...'
                            }
                            value={form.data.url}
                            onChange={(e) =>
                                form.setData('url', e.target.value)
                            }
                            className={veld}
                        />
                    </div>
                    <Fout tekst={form.errors.url} />
                    <button
                        type="submit"
                        className={groteKnop}
                        disabled={form.processing}
                    >
                        Ik heb geplaatst
                    </button>
                </form>
            </TitelKaart>
        </>
    );
}

function Live({ samenwerking: s }: { samenwerking: SamenwerkingVol }) {
    const post = s.publications.at(-1);

    return (
        <TitelKaart titel="Je post staat live" aanZet>
            {post && (
                <a
                    href={post.url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-2 font-semibold break-all text-violet underline"
                >
                    <ExternalLink className="size-4 shrink-0" aria-hidden />
                    {post.url}
                </a>
            )}
            <p className="mt-3 text-sm">
                Laat je post staan. We meten tot{' '}
                <strong>{datum(s.measure_until)}</strong>.
            </p>
            <Vergoeding samenwerking={s} />
        </TitelKaart>
    );
}

/** Wat de creator krijgt. Zonder vergoeding nooit een geldbedrag. */
function Vergoeding({ samenwerking: s }: { samenwerking: SamenwerkingVol }) {
    if (s.payout) {
        return (
            <div className="mt-4">
                <Uitkomst goed>
                    <span className="block font-heading text-xl font-extrabold">
                        {euro(s.payout.amount_cents)}
                    </span>
                    {s.payout.status === 'uitbetaald'
                        ? `Uitbetaald in de betaalrun van ${datum(s.payout.run_on)}.`
                        : `Staat klaar. Uitbetaling in de betaalrun van ${datum(s.payout.run_on)}.`}
                </Uitkomst>
            </div>
        );
    }

    return (
        <p className="mt-4 rounded-xl bg-muted px-3 py-2 text-sm">
            Voor deze klus: <strong>{s.reward}</strong>.
        </p>
    );
}
