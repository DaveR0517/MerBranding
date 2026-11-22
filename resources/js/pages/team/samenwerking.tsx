import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { Tijdlijn } from '@/components/samenwerking/tijdlijn';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { datum, datumTijd, euro } from '@/lib/datum';
import * as team from '@/routes/team';
import type { SamenwerkingVol } from '@/types/samenwerking';
import { Fouten, Label, Paneel, gebeurtenis, invoer } from './_onderdelen/basis';
import { Contentcontrole } from './_onderdelen/controle';
import { Matchlijst, Uitnodigingen } from './_onderdelen/koppelen';
import type { Match } from './_onderdelen/koppelen';

type Rechten = { koppelen: boolean; controleren: boolean; plannen: boolean };
type Tab = 'koppelen' | 'controle' | 'gegevens' | 'logboek';

const annuleerbaar = ['nieuw', 'creator_zoeken', 'creator_gevonden', 'bezoek_gepland'];

function Rij({ naam, children }: { naam: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-0.5 py-2 sm:grid-cols-[180px_1fr] sm:gap-4">
            <dt className="text-sm text-muted-foreground">{naam}</dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}

function Gegevens({ c }: { c: SamenwerkingVol }) {
    const leeg = <span className="text-muted-foreground">Nog niet</span>;

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Paneel titel="Briefing">
                <p className="text-sm whitespace-pre-line">{c.briefing}</p>
                {c.experience && (
                    <>
                        <h3 className="mt-4 text-sm font-semibold">Wat de creator beleeft</h3>
                        <p className="mt-1 text-sm whitespace-pre-line text-muted-foreground">{c.experience}</p>
                    </>
                )}
            </Paneel>
            <Paneel titel="Afspraken">
                <dl className="divide-y">
                    <Rij naam="Vestiging">
                        {c.location}
                        {c.location_address && <span className="block text-muted-foreground">{c.location_address}</span>}
                    </Rij>
                    <Rij naam="Gewenste periode">
                        {c.period_from ? `${datum(c.period_from)} t/m ${datum(c.period_to)}` : 'Geen voorkeur'}
                    </Rij>
                    <Rij naam="Bezoektijden">
                        {c.visit_windows.length > 0 ? c.visit_windows.map((w) => w.label).join(', ') : 'Niet ingesteld'}
                    </Rij>
                    <Rij naam="Bezoek">{c.visit_at ? datumTijd(c.visit_at) : leeg}</Rij>
                    <Rij naam="Ingecheckt">{c.visited_at ? datumTijd(c.visited_at) : leeg}</Rij>
                    <Rij naam="Deadline content">{c.content_deadline_at ? datumTijd(c.content_deadline_at) : leeg}</Rij>
                    <Rij naam="Alcohol in beeld">{c.alcohol ? 'Ja, creator 25 jaar of ouder' : 'Nee'}</Rij>
                    <Rij naam="Creator">
                        {c.creator ?? leeg}
                        {c.creator_level && <span className="text-muted-foreground"> · niveau {c.creator_level}</span>}
                    </Rij>
                    <Rij naam="Punten">{c.points ?? 0}</Rij>
                    <Rij naam="Vergoeding creator">
                        {c.fee_cents > 0 ? euro(c.fee_cents) : 'Geen geld'}
                        <span className="block text-muted-foreground">{c.reward}</span>
                    </Rij>
                    <Rij naam="Publicatiedatum">{c.publish_on ? datum(c.publish_on) : leeg}</Rij>
                    {c.cancel_reason && <Rij naam="Reden annulering">{c.cancel_reason}</Rij>}
                </dl>
            </Paneel>
            <Paneel titel="Publicaties">
                {c.publications.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Nog niet online.</p>
                ) : (
                    <ul className="space-y-1.5 text-sm">
                        {c.publications.map((p) => (
                            <li key={p.url}>
                                <span className="font-semibold capitalize">{p.platform}</span>{' '}
                                <a href={p.url} target="_blank" rel="noreferrer" className="break-all text-violet underline">
                                    {p.url}
                                </a>
                                <span className="block text-muted-foreground">Geplaatst {datumTijd(p.posted_at)}</span>
                            </li>
                        ))}
                    </ul>
                )}
                {c.live_at && (
                    <p className="mt-2 text-sm text-muted-foreground">
                        Live sinds {datumTijd(c.live_at)}
                        {c.measure_until && `, meten tot ${datum(c.measure_until)}`}
                    </p>
                )}
            </Paneel>
            <Paneel titel="Beoordeling en uitbetaling">
                <dl className="divide-y">
                    <Rij naam="Beoordeling ondernemer">
                        {c.review ? (
                            <>
                                <span className="font-semibold">{c.review.rating} van 5</span>
                                {c.review.body && <span className="block text-muted-foreground">{c.review.body}</span>}
                            </>
                        ) : (
                            leeg
                        )}
                    </Rij>
                    <Rij naam="Uitbetaling">
                        {c.payout ? (
                            <>
                                {euro(c.payout.amount_cents)} <Label kleur={c.payout.status === 'betaald' ? 'groen' : 'amber'}>{c.payout.status}</Label>
                                <span className="block text-muted-foreground">Betaalrun {datum(c.payout.run_on)}</span>
                            </>
                        ) : (
                            <span className="text-muted-foreground">{c.fee_cents > 0 ? 'Ontstaat zodra de video live staat' : 'Geen, gratis bezoek'}</span>
                        )}
                    </Rij>
                </dl>
            </Paneel>
        </div>
    );
}

function Logboek({ c }: { c: SamenwerkingVol }) {
    const log = [...(c.log ?? [])].reverse();
    const boekingen = c.ledger ?? [];

    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
            <Paneel titel="Logboek" sub="Wie deed wat, nieuwste eerst.">
                {log.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Nog niets vastgelegd.</p>
                ) : (
                    <ol className="space-y-3" data-test="logboek">
                        {log.map((l, i) => (
                            <li key={i} className="border-l-2 border-violet/30 pl-3 text-sm">
                                <p className="font-semibold">
                                    {l.event === 'status' && l.to ? (
                                        <>
                                            {l.from ?? 'Start'} <span className="text-muted-foreground">naar</span> {l.to}
                                        </>
                                    ) : (
                                        gebeurtenis(l.event)
                                    )}
                                    {l.event === 'uitgenodigd' && typeof l.data?.creator === 'string' && `: ${l.data.creator}`}
                                </p>
                                {l.reason && <p className="text-muted-foreground">{l.reason}</p>}
                                <p className="text-xs text-muted-foreground">
                                    {l.user} · {datumTijd(l.at)}
                                </p>
                            </li>
                        ))}
                    </ol>
                )}
            </Paneel>
            <Paneel titel="Tegoedboekingen" sub="Punten van het bedrijf voor deze samenwerking.">
                {boekingen.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Geen boekingen.</p>
                ) : (
                    <ul className="divide-y text-sm">
                        {boekingen.map((b, i) => (
                            <li key={i} className="flex items-start justify-between gap-3 py-2">
                                <span>
                                    <span className="font-semibold capitalize">{b.kind}</span>
                                    <span className="block text-muted-foreground">{b.reason}</span>
                                    <span className="block text-xs text-muted-foreground">{datumTijd(b.at)}</span>
                                </span>
                                <span className="font-mono font-semibold">{b.points}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </Paneel>
        </div>
    );
}

function Annuleren({ c }: { c: SamenwerkingVol }) {
    const [open, setOpen] = useState(false);
    const [reden, setReden] = useState('');
    const [bezig, setBezig] = useState(false);

    return (
        <>
            <Button variant="outline" size="sm" className="border-white/30 bg-transparent text-white hover:bg-white/10 hover:text-white" onClick={() => setOpen(true)}>
                Annuleren
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Samenwerking annuleren</DialogTitle>
                        <DialogDescription>
                            De ondernemer krijgt de {c.points ?? 0} punten terug. Open uitnodigingen worden ingetrokken en de ondernemer en
                            de creator krijgen een melding met de reden.
                        </DialogDescription>
                    </DialogHeader>
                    <label htmlFor="reden" className="text-sm font-semibold">
                        Reden
                    </label>
                    <textarea
                        id="reden"
                        rows={3}
                        value={reden}
                        onChange={(e) => setReden(e.target.value)}
                        className={`${invoer} h-auto py-2`}
                        placeholder="Bijvoorbeeld: de zaak is tijdelijk gesloten."
                    />
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Terug
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={!reden.trim() || bezig}
                            onClick={() =>
                                router.post(
                                    team.annuleren.url(c.id),
                                    { reason: reden },
                                    { preserveScroll: true, onStart: () => setBezig(true), onFinish: () => setBezig(false), onSuccess: () => setOpen(false) },
                                )
                            }
                        >
                            Samenwerking annuleren
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function NietVerschenen({ c }: { c: SamenwerkingVol }) {
    const [wie, setWie] = useState<'creator' | 'ondernemer' | null>(null);

    return (
        <Paneel titel="Bezoek gepland" sub={c.visit_at ? `Afspraak ${datumTijd(c.visit_at)}` : undefined} className="border-amber-300">
            <p className="text-sm text-muted-foreground">
                Is het bezoek niet doorgegaan omdat iemand er niet was? Leg vast wie er niet verscheen.
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
                <Button variant="outline" onClick={() => setWie('creator')}>
                    Creator niet verschenen
                </Button>
                <Button variant="outline" onClick={() => setWie('ondernemer')}>
                    Ondernemer niet aanwezig
                </Button>
            </div>
            <Dialog open={wie !== null} onOpenChange={(o) => !o && setWie(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Niet verschenen vastleggen</DialogTitle>
                        <DialogDescription>
                            {wie === 'creator'
                                ? `De creator kwam niet. De ondernemer krijgt de ${c.points ?? 0} punten terug.`
                                : 'De ondernemer was er niet. De punten zijn verbruikt.'}{' '}
                            De samenwerking stopt en beide partijen krijgen een melding.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setWie(null)}>
                            Terug
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.post(team.nietVerschenen.url(c.id), { who: wie }, { preserveScroll: true, onSuccess: () => setWie(null) })
                            }
                        >
                            Vastleggen
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Paneel>
    );
}

export default function Samenwerking({
    collaboration: c,
    matches,
    can,
}: {
    collaboration: SamenwerkingVol;
    matches: Match[];
    can: Rechten;
}) {
    const status = c.status.value;
    const toonKoppelen = can.koppelen && ['nieuw', 'creator_zoeken'].includes(status);
    const toonControle = c.versions.length > 0;
    const tabs: { key: Tab; naam: string }[] = [
        ...(toonKoppelen ? [{ key: 'koppelen' as Tab, naam: 'Koppelen' }] : []),
        ...(toonControle ? [{ key: 'controle' as Tab, naam: 'Contentcontrole' }] : []),
        { key: 'gegevens', naam: 'Gegevens' },
        { key: 'logboek', naam: 'Logboek' },
    ];
    const standaard: Tab = toonKoppelen ? 'koppelen' : toonControle && ['ter_controle', 'feedback'].includes(status) ? 'controle' : 'gegevens';
    const [gekozen, setTab] = useState<Tab>(standaard);
    const tab = tabs.some((t) => t.key === gekozen) ? gekozen : standaard;

    return (
        <>
            <Head title={`${c.number} ${c.title}`} />
            <div className="mx-auto w-full max-w-7xl space-y-4 p-4 md:p-6">
                <header className="space-y-4 rounded-2xl bg-aubergine p-4 text-white md:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="font-mono text-xs text-white/70">{c.number}</p>
                            <h1 className="mt-0.5 font-heading text-2xl font-extrabold md:text-3xl">{c.title}</h1>
                            <p className="mt-1 text-sm text-white/80">
                                {c.company}, {c.location}
                                {c.creator && ` · creator ${c.creator}`}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="rounded-full bg-white p-0.5">
                                <StatusBadge status={c.status} />
                            </span>
                            {can.koppelen && annuleerbaar.includes(status) && <Annuleren c={c} />}
                        </div>
                    </div>
                    <div className="rounded-xl bg-white p-2 text-foreground dark:bg-background">
                        <Tijdlijn stappen={c.steps} />
                    </div>
                    {status === 'geannuleerd' && c.cancel_reason && (
                        <p className="text-sm text-white/85">Geannuleerd: {c.cancel_reason}</p>
                    )}
                </header>

                <Fouten />

                {status === 'bezoek_gepland' && can.plannen && <NietVerschenen c={c} />}

                <div role="tablist" aria-label="Onderdelen" className="-mx-4 flex gap-1 overflow-x-auto border-b px-4 md:mx-0 md:px-0">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            role="tab"
                            aria-selected={tab === t.key}
                            onClick={() => setTab(t.key)}
                            className={`-mb-px shrink-0 border-b-2 px-3 py-2 text-sm font-semibold transition ${tab === t.key ? 'border-violet text-violet' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                        >
                            {t.naam}
                        </button>
                    ))}
                </div>

                <div role="tabpanel">
                    {tab === 'koppelen' && (
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,360px)]">
                            <Matchlijst c={c} matches={matches} />
                            <div className="space-y-4">
                                <Uitnodigingen uitnodigingen={c.invitations ?? []} />
                                <Paneel titel="Briefing">
                                    <p className="text-sm whitespace-pre-line">{c.briefing}</p>
                                </Paneel>
                            </div>
                        </div>
                    )}
                    {tab === 'controle' && <Contentcontrole c={c} magControleren={can.controleren} />}
                    {tab === 'gegevens' && (
                        <div className="space-y-4">
                            {!toonKoppelen && (c.invitations?.length ?? 0) > 0 && <Uitnodigingen uitnodigingen={c.invitations ?? []} />}
                            <Gegevens c={c} />
                        </div>
                    )}
                    {tab === 'logboek' && <Logboek c={c} />}
                </div>
            </div>
        </>
    );
}

Samenwerking.layout = (props: { collaboration: SamenwerkingVol }) => ({
    breadcrumbs: [
        { title: 'Samenwerkingen', href: team.samenwerkingen.url() },
        { title: props.collaboration.number, href: team.samenwerking.url(props.collaboration.id) },
    ],
});
