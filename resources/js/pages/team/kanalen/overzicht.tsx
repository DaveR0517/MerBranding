import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Check, Copy, MessageCircle, RefreshCw, Unplug, X } from 'lucide-react';
import { useState } from 'react';
import { Etiket } from '@/components/content/labels';
import { getal, KanaalStatus, KanalenTabs, PLATFORMS, Staafjes } from '@/components/kanalen/onderdelen';
import type { PlatformSleutel } from '@/components/kanalen/onderdelen';
import { Kaart, KaartRaster, Pagina, PaginaKop, Sectie, Uitklap } from '@/components/pagina';
import { Button } from '@/components/ui/button';
import { datumTijd } from '@/lib/datum';
import kanalen from '@/routes/team/kanalen';

type Kanaal = {
    platform: PlatformSleutel;
    naam: string;
    ingesteld: boolean;
    status: 'niet_gekoppeld' | 'gekoppeld' | 'verloopt' | 'verlopen' | 'fout';
    handle: string | null;
    profielnaam: string | null;
    foto: string | null;
    verloopt_op: string | null;
    laatste_sync: string | null;
    fout: string | null;
    ontbrekende_rechten: string[];
    volgers: number | null;
    volgers_groei: number | null;
    weergaven_28: number | null;
    bereik_gisteren: number | null;
    likes_totaal: number | null;
    posts: number;
    reeks: { datum: string; waarde: number }[];
    berichten: boolean;
};

type Checklist = {
    redirect: string;
    webhook: string;
    webhook_actief: boolean;
    scopes: string[];
    env: { naam: string; ingevuld: boolean }[];
};

type Props = { kanalen: Kanaal[]; checklist: Record<PlatformSleutel, Checklist>; ongelezen: number; gmail: { ingesteld: boolean; postvakken: string[] } };

/** Wat Dave per platform invult bij de ontwikkelaarsapp (stappenplan: docs/koppelen-meta-en-tiktok.md). */
const STAPPEN: Record<PlatformSleutel, string[]> = {
    instagram: [
        'Meta for Developers: maak een app van het type Bedrijf en voeg het product Instagram toe (API met Instagram Login).',
        'Zet het adres hieronder bij "OAuth redirect URI" (Instagram Login voor bedrijven).',
        'Webhooks: callback-adres en verify token hieronder, abonneer op messages en comments.',
        'Vraag App Review aan voor berichten, reacties, inzichten en plaatsen. Tot die tijd werkt het alleen voor testgebruikers.',
        'Zet de app-ID, het app-geheim en het verify token als env-variabelen in Forge.',
    ],
    tiktok: [
        'TikTok for Developers: maak een app en voeg Login Kit en Display API toe (Content Posting API later).',
        'Zet het adres hieronder als redirect-URI bij Login Kit (Web).',
        'Vraag de rechten hieronder aan en dien de app in voor review. Tot dan alleen met de sandbox en testgebruikers.',
        'Zet de client key en het client secret als env-variabelen in Forge.',
    ],
};

function Kopieer({ waarde }: { waarde: string }) {
    const [gekopieerd, setGekopieerd] = useState(false);

    return (
        <div className="flex items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2">
            <code className="min-w-0 flex-1 text-xs break-all">{waarde}</code>
            <button
                type="button"
                className="inline-flex size-10 shrink-0 items-center justify-center rounded-md hover:bg-muted md:size-8"
                aria-label="Kopiëren"
                onClick={() => navigator.clipboard?.writeText(waarde).then(() => setGekopieerd(true))}
            >
                {gekopieerd ? <Check className="size-4 text-emerald-600" aria-hidden /> : <Copy className="size-4" aria-hidden />}
            </button>
        </div>
    );
}

function ChecklistInhoud({ platform, c }: { platform: PlatformSleutel; c: Checklist }) {
    return (
        <div className="space-y-4 text-sm">
            <ol className="list-decimal space-y-1 pl-5 text-muted-foreground">
                {STAPPEN[platform].map((s) => (
                    <li key={s}>{s}</li>
                ))}
            </ol>
            <div className="space-y-1">
                <p className="font-semibold">Redirect-URI</p>
                <Kopieer waarde={c.redirect} />
            </div>
            <div className="space-y-1">
                <p className="font-semibold">Webhook (callback-adres)</p>
                <Kopieer waarde={c.webhook} />
                {!c.webhook_actief && <p className="text-xs text-rood">Dit adres werkt pas als routes/portaal-webhooks.php geladen wordt.</p>}
            </div>
            <div className="space-y-1">
                <p className="font-semibold">Rechten (scopes)</p>
                <div className="flex flex-wrap gap-1">
                    {c.scopes.map((s) => (
                        <Etiket key={s} kleur="grijs">
                            {s}
                        </Etiket>
                    ))}
                </div>
            </div>
            <div className="space-y-1">
                <p className="font-semibold">Env-variabelen in Forge</p>
                <ul className="space-y-1">
                    {c.env.map((e) => (
                        <li key={e.naam} className="flex items-center gap-2">
                            {e.ingevuld ? <Check className="size-4 text-emerald-600" aria-label="Ingevuld" /> : <X className="size-4 text-rood" aria-label="Ontbreekt" />}
                            <code className="text-xs">{e.naam}</code>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

function KanaalKaart({ k, checklist }: { k: Kanaal; checklist: Checklist }) {
    const Icoon = PLATFORMS[k.platform].icoon;
    const gekoppeld = k.status !== 'niet_gekoppeld';
    const [bezig, setBezig] = useState(false);
    const post = (url: string) => router.post(url, {}, { preserveScroll: true, onStart: () => setBezig(true), onFinish: () => setBezig(false) });

    return (
        <Kaart className="space-y-4">
            <div className="flex items-start gap-3">
                {k.foto ? (
                    <img src={k.foto} alt="" className="size-11 shrink-0 rounded-full object-cover" />
                ) : (
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-violet/10 text-violet">
                        <Icoon className="size-5" aria-hidden />
                    </span>
                )}
                <div className="min-w-0 flex-1">
                    <p className="font-heading text-lg font-bold">{k.naam}</p>
                    <p className="truncate text-sm text-muted-foreground">{k.handle ?? 'Nog geen account gekoppeld'}</p>
                </div>
                <KanaalStatus status={k.status} />
            </div>

            {gekoppeld ? (
                <>
                    <dl className="grid grid-cols-3 gap-2 text-center">
                        <div className="rounded-lg bg-muted/50 p-2">
                            <dt className="text-xs text-muted-foreground">Volgers</dt>
                            <dd className="font-heading text-lg font-extrabold tabular-nums md:text-xl">{getal(k.volgers)}</dd>
                            {k.volgers_groei !== null && (
                                <dd className="text-xs text-muted-foreground tabular-nums">
                                    {k.volgers_groei >= 0 ? '+' : ''}
                                    {k.volgers_groei.toLocaleString('nl-NL')}
                                </dd>
                            )}
                        </div>
                        <div className="rounded-lg bg-muted/50 p-2">
                            <dt className="text-xs text-muted-foreground">Weergaven</dt>
                            <dd className="font-heading text-lg font-extrabold tabular-nums md:text-xl">{getal(k.weergaven_28)}</dd>
                            <dd className="text-xs text-muted-foreground">28 dagen</dd>
                        </div>
                        <div className="rounded-lg bg-muted/50 p-2">
                            <dt className="text-xs text-muted-foreground">{k.platform === 'instagram' ? 'Bereik' : 'Likes'}</dt>
                            <dd className="font-heading text-lg font-extrabold tabular-nums md:text-xl">{getal(k.platform === 'instagram' ? k.bereik_gisteren : k.likes_totaal)}</dd>
                            <dd className="text-xs text-muted-foreground">{k.platform === 'instagram' ? 'gisteren' : 'totaal'}</dd>
                        </div>
                    </dl>
                    <Staafjes reeks={k.reeks} label="Weergaven per dag" />

                    {k.fout && (
                        <p className="flex gap-2 rounded-lg bg-rood/10 p-2 text-sm text-rood">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden /> {k.fout}
                        </p>
                    )}
                    {k.ontbrekende_rechten.length > 0 && (
                        <p className="flex gap-2 rounded-lg bg-amber-100 p-2 text-sm text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden /> Niet alle rechten zijn gegeven ({k.ontbrekende_rechten.join(', ')}). Koppel opnieuw en vink alles aan.
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        {k.laatste_sync ? `Bijgewerkt ${datumTijd(k.laatste_sync)}` : 'Nog niet opgehaald'}
                        {k.verloopt_op && `, koppeling geldig tot ${new Date(k.verloopt_op).toLocaleDateString('nl-NL', { day: 'numeric', month: 'long', year: 'numeric' })} (wordt vanzelf verlengd)`}
                        {k.platform === 'instagram' && '. Cijfers van Meta kunnen tot 48 uur achterlopen.'}
                    </p>
                    {!k.berichten && (
                        <p className="flex gap-2 text-sm text-muted-foreground">
                            <MessageCircle className="mt-0.5 size-4 shrink-0" aria-hidden /> TikTok heeft geen API voor privéberichten. Berichten lees en beantwoord je in de TikTok-app.
                        </p>
                    )}
                    <div className="flex flex-wrap gap-2 *:max-md:h-10 *:max-md:flex-1">
                        {k.status !== 'fout' && k.status !== 'verlopen' && (
                            <Button variant="outline" disabled={bezig} onClick={() => post(kanalen.verversen.url(k.platform))}>
                                <RefreshCw className="size-4" aria-hidden /> Nu verversen
                            </Button>
                        )}
                        <Button variant="outline" disabled={bezig || !k.ingesteld} onClick={() => post(kanalen.koppelen.url(k.platform))}>
                            Opnieuw koppelen
                        </Button>
                        <Button
                            variant="ghost"
                            disabled={bezig}
                            onClick={() => confirm(`${k.naam} ontkoppelen? Eerder opgehaalde posts en berichten blijven staan.`) && post(kanalen.ontkoppelen.url(k.platform))}
                        >
                            <Unplug className="size-4" aria-hidden /> Ontkoppelen
                        </Button>
                    </div>
                </>
            ) : k.ingesteld ? (
                <div className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                        Log in met het {k.naam}-account van MEBRANDING en geef alle gevraagde rechten. Daarna halen we elk uur de cijfers op.
                        {k.platform === 'instagram' && ' Het account moet een professioneel account zijn (bedrijf of creator).'}
                    </p>
                    <Button className="h-10 w-full bg-violet text-white hover:bg-violet/90 md:w-auto" disabled={bezig} onClick={() => post(kanalen.koppelen.url(k.platform))}>
                        {k.naam} koppelen
                    </Button>
                </div>
            ) : (
                <div className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                        Nog niet gekoppeld: de sleutels van de {k.platform === 'instagram' ? 'Meta' : 'TikTok'}-app staan nog niet in de instellingen. Zodra ze er zijn, verschijnt hier de knop Koppelen.
                    </p>
                    <Uitklap titel="Checklist om te koppelen" open>
                        <ChecklistInhoud platform={k.platform} c={checklist} />
                    </Uitklap>
                </div>
            )}
        </Kaart>
    );
}

/** Eigen kanalen: het Instagram- en TikTok-account van MEBRANDING zelf. */
export default function Overzicht({ kanalen: lijst, checklist, ongelezen, gmail }: Props) {
    const ingesteld = lijst.filter((k) => k.ingesteld);

    return (
        <>
            <Head title="Eigen kanalen" />
            <Pagina>
                <PaginaKop boven="MeStudio" titel="Eigen kanalen" omschrijving="Het Instagram- en TikTok-account van MEBRANDING: volgers, weergaven, posts en berichten op één plek." />
                <KanalenTabs actief="overzicht" ongelezen={ongelezen} />

                <KaartRaster kolommen={2}>
                    {lijst.map((k) => (
                        <KanaalKaart key={k.platform} k={k} checklist={checklist[k.platform]} />
                    ))}
                </KaartRaster>

                <GmailKaart gmail={gmail} />

                {ingesteld.length > 0 && (
                    <Sectie titel="Instellingen bij Meta en TikTok" omschrijving="De adressen die in de ontwikkelaarsapps horen. Handig bij opnieuw instellen of een nieuw domein.">
                        <KaartRaster kolommen={2}>
                            {ingesteld.map((k) => (
                                <Uitklap key={k.platform} titel={`Checklist ${k.naam}`}>
                                    <ChecklistInhoud platform={k.platform} c={checklist[k.platform]} />
                                </Uitklap>
                            ))}
                        </KaartRaster>
                    </Sectie>
                )}
            </Pagina>
        </>
    );
}

/** Gmail van MEBRANDING (Google Workspace): status van de koppeling en de postvakken; lezen gebeurt onder Mail. */
function GmailKaart({ gmail }: { gmail: Props['gmail'] }) {
    return (
        <Kaart className="flex flex-wrap items-center justify-between gap-3" data-test="gmail-kaart">
            <div className="min-w-48 flex-1">
                <p className="flex items-center gap-2 font-heading font-bold">
                    Gmail (Google Workspace)
                    <span className={gmail.ingesteld ? 'rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800' : 'rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground'}>
                        {gmail.ingesteld ? 'Gekoppeld' : 'Nog niet gekoppeld'}
                    </span>
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {gmail.postvakken.length > 0 ? gmail.postvakken.join(', ') : 'Nog geen postvakken ingesteld (GMAIL_POSTVAKKEN).'}
                    {!gmail.ingesteld && ' De Workspace-beheerder geeft het portaal eenmalig toegang; zie de stappen in docs/koppelen-gmail.md.'}
                </p>
            </div>
            {gmail.postvakken.length > 0 && (
                <Link href={kanalen.mail.url()} className="min-h-10 rounded-full border px-4 py-2 text-sm font-semibold hover:border-violet">
                    Naar Mail
                </Link>
            )}
        </Kaart>
    );
}
