import { Head, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Minus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { datum, datumTijd } from '@/lib/datum';
import * as team from '@/routes/team';
import { besluit as besluitRoute, niveau as niveauRoute, status as statusRoute } from '@/routes/team/klassement';
import { getal, scoreKleur } from '../../creator/score/_onderdelen/score';
import type { Drempel } from '../../creator/score/_onderdelen/score';
import { KaartRaster, Kerncijfer, Leeg, MeerLijst, Pagina, PaginaKop, Tabs } from '@/components/pagina';
import { Fouten, Label, Paneel, Veld, invoer, useTab } from '../_onderdelen/basis';

type Rij = {
    positie: number;
    creator_id: number;
    name: string;
    level: string;
    level_name: string;
    kind: string;
    niches: string[];
    city: string | null;
    status: string;
    since: string | null;
    total: number;
    deelscores: Record<'reach' | 'engagement' | 'ad_result' | 'quality' | 'reliability', number | null>;
    jobs_count: number;
    afgerond: number;
    voorlopig: boolean;
    new_talent: boolean;
    trend: number | null;
    haalbaar: string;
    in_gevaar: boolean;
};
type Voorstel = {
    id: number;
    creator: string;
    from: string;
    to: string;
    omhoog: boolean;
    score: number | null;
    reason: string;
    created_at: string;
};
type Besluit = {
    id: number;
    event: string;
    creator: string | null;
    door: string | null;
    van: string | null;
    naar: string | null;
    reason: string | null;
    at: string;
};

type Props = {
    rijen: Rij[];
    levels: { value: string; name: string }[];
    niches: string[];
    voorstellen: Voorstel[];
    besluiten: Besluit[];
    geblokkeerd: { id: number; name: string }[];
    drempels: Drempel[];
    gewichten: { key: string; name: string; weight: number }[];
    instellingen: {
        min_jobs: number;
        periode_dagen: number;
        nieuw_talent_dagen: number;
        startscore: number;
    };
    herberekening: string;
    bijgewerkt: string | null;
    can: { beheren: boolean };
};

const soorten: Record<string, string> = {
    influencer: 'Influencer',
    ugc: 'UGC-maker',
    beide: 'Beide',
};
const kolommen = [
    ['reach', 'Bereik'],
    ['engagement', 'Engag.'],
    ['ad_result', 'Advert.'],
    ['quality', 'Klantoordeel'],
    ['reliability', 'Betrouwb.'],
] as const;

function Trend({ waarde, nieuw }: { waarde: number | null; nieuw: boolean }) {
    if (waarde === null) {
        return <span className="text-xs text-muted-foreground">{nieuw ? 'Nieuw' : 'Geen'}</span>;
    }

    if (Math.abs(waarde) < 0.05) {
        return (
            <span className="inline-flex items-center gap-0.5 text-xs text-muted-foreground">
                <Minus className="size-3" aria-hidden />
                gelijk
            </span>
        );
    }

    const omhoog = waarde > 0;

    return (
        <span className={`inline-flex items-center gap-0.5 text-xs font-semibold ${omhoog ? 'text-emerald-700 dark:text-emerald-400' : 'text-rood'}`}>
            {omhoog ? <ArrowUp className="size-3" aria-hidden /> : <ArrowDown className="size-3" aria-hidden />}
            <span className="sr-only">{omhoog ? 'omhoog' : 'omlaag'}</span>
            {getal(Math.abs(waarde))}
        </span>
    );
}

export default function Klassement(props: Props) {
    const { rijen, levels, niches, voorstellen, besluiten, geblokkeerd, drempels, gewichten, instellingen, herberekening, bijgewerkt, can } = props;
    const [tab, setTab] = useTab('klassement', 'algemeen', ['algemeen', 'nieuw', 'voorstellen', 'grenzen'] as const);
    const [niveau, setNiveau] = useState('');
    const [niche, setNiche] = useState('');
    const [zoek, setZoek] = useState('');
    const [beheer, setBeheer] = useState<{
        id: number;
        name: string;
        level: string;
        status: string;
    } | null>(null);

    const zichtbaar = useMemo(
        () =>
            rijen
                .filter((r) => (tab === 'nieuw' ? r.new_talent : true))
                .filter((r) => !niveau || r.level === niveau)
                .filter((r) => !niche || r.niches.includes(niche))
                .filter((r) => !zoek || r.name.toLowerCase().includes(zoek.toLowerCase())),
        [rijen, tab, niveau, niche, zoek],
    );
    const nieuwAantal = rijen.filter((r) => r.new_talent).length;
    const gevaarAantal = rijen.filter((r) => r.in_gevaar).length;
    const filtersAan = Boolean(niveau || niche || zoek);

    return (
        <>
            <Head title="Klassement" />
            <Pagina>
                <PaginaKop
                    titel="Klassement"
                    omschrijving={`Score over de laatste ${instellingen.periode_dagen} dagen.${bijgewerkt ? ` Bijgewerkt ${datumTijd(bijgewerkt)}.` : ''} Volgende herberekening ${datum(herberekening)} (voorstel).`}
                />
                <Fouten />

                <KaartRaster kolommen={2} className="grid-cols-2">
                    <Kerncijfer label="Voorstellen" waarde={`${voorstellen.length} open`} toelichting="Het team beslist, nooit automatisch" />
                    <Kerncijfer label="Niveau in gevaar" waarde={`${gevaarAantal} ${gevaarAantal === 1 ? 'creator' : 'creators'}`} toelichting="Onder de grens van het huidige niveau" />
                </KaartRaster>

                <Tabs
                    tabs={[
                        {
                            waarde: 'algemeen',
                            label: 'Ranglijst',
                            aantal: rijen.length,
                        },
                        {
                            waarde: 'nieuw',
                            label: 'Nieuw talent',
                            aantal: nieuwAantal,
                        },
                        {
                            waarde: 'voorstellen',
                            label: 'Voorstellen',
                            aantal: voorstellen.length,
                        },
                        { waarde: 'grenzen', label: 'Grenzen en besluiten' },
                    ]}
                    actief={tab}
                    onKies={setTab}
                />

                {tab === 'voorstellen' && (
                    <Paneel titel="Voorstellen tot niveauwijziging" sub="Alleen met reden, komt in het logboek. De creator krijgt bericht na goedkeuren.">
                        {voorstellen.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Geen open voorstellen. Nieuwe voorstellen ontstaan bij de herberekening op de 1e van de maand.</p>
                        ) : (
                            <ul className="space-y-3" data-test="voorstellen">
                                {voorstellen.map((v) => (
                                    <VoorstelKaart key={v.id} voorstel={v} beheren={can.beheren} />
                                ))}
                            </ul>
                        )}
                    </Paneel>
                )}

                {(tab === 'algemeen' || tab === 'nieuw') && (
                    <section className="space-y-3" aria-labelledby="ranglijst">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 id="ranglijst" className="sr-only">
                                    {tab === 'nieuw' ? 'Nieuw talent' : 'Ranglijst'}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {tab === 'nieuw'
                                        ? `Creators in hun eerste ${instellingen.nieuw_talent_dagen} dagen, met een eigen klassement.`
                                        : `${zichtbaar.length} ${zichtbaar.length === 1 ? 'creator' : 'creators'}, gesorteerd op score.`}
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-[minmax(0,1fr)_auto_auto_auto]">
                            <label className="relative col-span-2 sm:col-span-1">
                                <span className="sr-only">Zoeken op naam</span>
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
                                <input type="search" value={zoek} onChange={(e) => setZoek(e.target.value)} placeholder="Zoeken" className={`${invoer} pl-9`} />
                            </label>
                            <label>
                                <span className="sr-only">Niveau</span>
                                <select value={niveau} onChange={(e) => setNiveau(e.target.value)} className={invoer}>
                                    <option value="">Alle niveaus</option>
                                    {levels.map((l) => (
                                        <option key={l.value} value={l.value}>
                                            {l.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                <span className="sr-only">Niche</span>
                                <select value={niche} onChange={(e) => setNiche(e.target.value)} className={invoer}>
                                    <option value="">Alle niches</option>
                                    {niches.map((n) => (
                                        <option key={n} value={n}>
                                            {n}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            {filtersAan && (
                                <Button
                                    variant="outline"
                                    className="h-10"
                                    onClick={() => {
                                        setNiveau('');
                                        setNiche('');
                                        setZoek('');
                                    }}
                                >
                                    Wis filters
                                </Button>
                            )}
                        </div>

                        {zichtbaar.length === 0 ? (
                            <Leeg titel={<>{rijen.length === 0 ? 'Nog geen scores. Ze worden elke nacht berekend.' : 'Geen creators gevonden met deze zoekterm of filters.'}</>} />
                        ) : (
                            <>
                                <div className="lg:hidden" data-test="ranglijst-mobiel">
                                    <MeerLijst key={tab + niveau + niche + zoek}>
                                        {zichtbaar.map((r, i) => (
                                            <li key={r.creator_id} className="rounded-xl border bg-card p-3">
                                                <div className="flex items-start gap-3">
                                                    <span className="w-6 shrink-0 font-heading text-lg font-extrabold text-muted-foreground">{i + 1}</span>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="flex flex-wrap items-center gap-1.5">
                                                            <span className="font-semibold break-words">{r.name}</span>
                                                            <Label>{r.level_name}</Label>
                                                            <RijLabels rij={r} />
                                                        </p>
                                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                                            {[soorten[r.kind] ?? r.kind, r.city, `${r.afgerond} afgerond`].filter(Boolean).join(' · ')}
                                                        </p>
                                                    </div>
                                                    <div className="shrink-0 text-right">
                                                        <p className="font-heading text-2xl font-extrabold tabular-nums">{getal(r.total)}</p>
                                                        <Trend waarde={r.trend} nieuw={r.new_talent} />
                                                    </div>
                                                </div>
                                                <dl className="mt-2 grid grid-cols-5 gap-1 text-center text-xs">
                                                    {kolommen.map(([k, naam]) => (
                                                        <div key={k} className="min-w-0">
                                                            <dt className="truncate text-muted-foreground">{naam}</dt>
                                                            <dd className={`font-bold tabular-nums ${scoreKleur(r.deelscores[k])}`}>{r.deelscores[k] === null ? '-' : getal(r.deelscores[k])}</dd>
                                                        </div>
                                                    ))}
                                                </dl>
                                                {can.beheren && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="mt-2 h-10 w-full"
                                                        onClick={() =>
                                                            setBeheer({
                                                                id: r.creator_id,
                                                                name: r.name,
                                                                level: r.level,
                                                                status: r.status,
                                                            })
                                                        }
                                                    >
                                                        Niveau of status aanpassen
                                                    </Button>
                                                )}
                                            </li>
                                        ))}
                                    </MeerLijst>
                                </div>

                                <div className="hidden overflow-x-auto rounded-2xl border bg-card lg:block">
                                    <table className="w-full text-sm" data-test="ranglijst">
                                        <thead className="bg-muted/60 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-3 py-3 font-semibold">#</th>
                                                <th className="px-3 py-3 font-semibold">Creator</th>
                                                <th className="px-3 py-3 font-semibold">Niveau</th>
                                                <th className="px-3 py-3 font-semibold">Soort</th>
                                                <th className="px-3 py-3 text-right font-semibold">Score</th>
                                                {kolommen.map(([k, naam]) => (
                                                    <th key={k} className="px-2 py-3 text-right font-semibold">
                                                        {naam}
                                                    </th>
                                                ))}
                                                <th className="px-3 py-3 text-right font-semibold">Samenw.</th>
                                                <th className="px-3 py-3 font-semibold">Trend</th>
                                                {can.beheren && (
                                                    <th className="px-3 py-3">
                                                        <span className="sr-only">Acties</span>
                                                    </th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {zichtbaar.map((r, i) => (
                                                <tr key={r.creator_id}>
                                                    <td className="px-3 py-3 font-bold text-muted-foreground">{i + 1}</td>
                                                    <td className="px-3 py-3">
                                                        <span className="font-semibold">{r.name}</span>
                                                        <span className="mt-0.5 flex flex-wrap gap-1">
                                                            <RijLabels rij={r} />
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        <Label>{r.level_name}</Label>
                                                    </td>
                                                    <td className="px-3 py-3 whitespace-nowrap text-muted-foreground">{soorten[r.kind] ?? r.kind}</td>
                                                    <td className="px-3 py-3 text-right font-heading text-base font-extrabold tabular-nums">{getal(r.total)}</td>
                                                    {kolommen.map(([k]) => (
                                                        <td key={k} className={`px-2 py-3 text-right tabular-nums ${scoreKleur(r.deelscores[k])}`}>
                                                            {r.deelscores[k] === null ? '-' : getal(r.deelscores[k])}
                                                        </td>
                                                    ))}
                                                    <td className="px-3 py-3 text-right tabular-nums">{r.afgerond}</td>
                                                    <td className="px-3 py-3">
                                                        <Trend waarde={r.trend} nieuw={r.new_talent} />
                                                    </td>
                                                    {can.beheren && (
                                                        <td className="px-3 py-3 text-right">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setBeheer({
                                                                        id: r.creator_id,
                                                                        name: r.name,
                                                                        level: r.level,
                                                                        status: r.status,
                                                                    })
                                                                }
                                                            >
                                                                Aanpassen
                                                            </Button>
                                                        </td>
                                                    )}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Deelscores van 0 tot 100. Groen is 85 of hoger, rood onder 60. Een streepje: geen gegevens, het gewicht is verdeeld over de andere deelscores. Nieuwe creators starten met
                            een gemiddelde ({getal(instellingen.startscore)}) tot ze {instellingen.min_jobs} samenwerkingen hebben (label Voorlopig).
                        </p>
                    </section>
                )}

                {tab === 'grenzen' && (
                    <div className="grid gap-5 lg:grid-cols-2">
                        <Paneel titel="Niveaugrenzen" sub="Voorstel, instelbaar">
                            <ul className="divide-y text-sm">
                                {drempels.map((d, i) => (
                                    <li key={d.value} className="grid grid-cols-[6rem_6rem_1fr] gap-2 py-2">
                                        <b>{d.name}</b>
                                        <span className="tabular-nums">
                                            {i === drempels.length - 1
                                                ? `${d.score} en hoger`
                                                : d.score === 0
                                                  ? `Onder ${drempels[i + 1]?.score}`
                                                  : `${d.score} tot ${(drempels[i + 1]?.score ?? 100) - 1}`}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {d.jobs > 0 ? `Minimaal ${d.jobs} samenwerkingen` : 'Start na screening'}
                                            {d.quality ? `, Klantoordeel ${d.quality} of hoger` : ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-3 text-sm text-muted-foreground">
                                Score is het gewogen gemiddelde van: {gewichten.map((g) => `${g.weight}% ${g.name}`).join(', ')}. Het niveau wordt elke 1e van de maand opnieuw bekeken (voorstel).
                                Omhoog zodra alle grenzen gehaald zijn, omlaag pas na 2 metingen achter elkaar onder de grens, met eerst de melding Niveau in gevaar. Weigeren van een uitnodiging telt
                                nooit mee.
                            </p>
                        </Paneel>

                        <Paneel titel="Laatste besluiten" sub="Niveau en status, uit het logboek">
                            {besluiten.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Nog geen besluiten.</p>
                            ) : (
                                <ul className="divide-y text-sm" data-test="besluiten">
                                    {besluiten.map((b) => (
                                        <li key={b.id} className="py-2">
                                            <p className="font-semibold">
                                                {b.creator}: {besluitTekst(b, levels)}
                                            </p>
                                            <p className="text-muted-foreground">{[b.reason, b.door, datumTijd(b.at)].filter(Boolean).join(' · ')}</p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {geblokkeerd.length > 0 && (
                                <div className="mt-4 border-t pt-3">
                                    <p className="text-sm font-semibold">Geblokkeerd, niet in het klassement</p>
                                    <ul className="mt-1 space-y-1 text-sm">
                                        {geblokkeerd.map((g) => (
                                            <li key={g.id} className="flex items-center justify-between gap-2">
                                                {g.name}
                                                {can.beheren && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setBeheer({
                                                                id: g.id,
                                                                name: g.name,
                                                                level: '',
                                                                status: 'geblokkeerd',
                                                            })
                                                        }
                                                    >
                                                        Aanpassen
                                                    </Button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </Paneel>
                    </div>
                )}
            </Pagina>

            {beheer && <BeheerDialoog creator={beheer} levels={levels} sluit={() => setBeheer(null)} />}
        </>
    );
}

function RijLabels({ rij }: { rij: Rij }) {
    return (
        <>
            {rij.voorlopig && <Label kleur="grijs">Voorlopig</Label>}
            {rij.new_talent && <Label kleur="groen">Nieuw talent</Label>}
            {rij.in_gevaar && <Label kleur="rood">Niveau in gevaar</Label>}
            {rij.status === 'gepauzeerd' && <Label kleur="amber">Gepauzeerd</Label>}
        </>
    );
}

function besluitTekst(b: Besluit, levels: { value: string; name: string }[]): string {
    const naam = (v: string | null) => levels.find((l) => l.value === v)?.name ?? v ?? '';

    if (b.event === 'niveau_gewijzigd') {
        return `${naam(b.van)} naar ${naam(b.naar)}`;
    }

    if (b.event === 'niveauvoorstel_afgewezen') {
        return `voorstel ${naam(b.van)} naar ${naam(b.naar)} afgewezen`;
    }

    return `status ${b.van ?? ''} naar ${b.naar ?? ''}`;
}

function VoorstelKaart({ voorstel, beheren }: { voorstel: Voorstel; beheren: boolean }) {
    const form = useForm({ besluit: '', reden: '' });
    const id = `reden-${voorstel.id}`;
    const kies = (besluit: 'goedkeuren' | 'afwijzen') => {
        form.transform((d) => ({ ...d, besluit }));
        form.post(besluitRoute.url(voorstel.id), { preserveScroll: true });
    };

    return (
        <li className={`rounded-xl border border-l-4 p-3 ${voorstel.omhoog ? 'border-l-violet' : 'border-l-rood'}`}>
            <p className="flex flex-wrap items-center gap-2">
                <span className="font-semibold">{voorstel.creator}</span>
                <Label kleur={voorstel.omhoog ? 'violet' : 'rood'}>
                    {voorstel.from} naar {voorstel.to}
                </Label>
                {voorstel.score !== null && <span className="text-sm text-muted-foreground">score {getal(voorstel.score)}</span>}
            </p>
            <p className="mt-1 text-sm text-muted-foreground">
                {voorstel.reason} Voorgesteld op {datum(voorstel.created_at)}.
            </p>
            {beheren ? (
                <form
                    className="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end"
                    onSubmit={(e) => {
                        e.preventDefault();
                        kies('goedkeuren');
                    }}
                >
                    <Veld label="Reden" htmlFor={id} fout={form.errors.reden}>
                        <input id={id} value={form.data.reden} onChange={(e) => form.setData('reden', e.target.value)} className={invoer} required minLength={3} maxLength={255} />
                    </Veld>
                    <Button type="submit" disabled={form.processing}>
                        Goedkeuren
                    </Button>
                    <Button type="button" variant="outline" disabled={form.processing || form.data.reden.trim().length < 3} onClick={() => kies('afwijzen')}>
                        Afwijzen
                    </Button>
                </form>
            ) : (
                <p className="mt-2 text-xs text-muted-foreground">Alleen teamleden met het recht Klassement beheren besluiten hierover.</p>
            )}
        </li>
    );
}

function BeheerDialoog({ creator, levels, sluit }: { creator: { id: number; name: string; level: string; status: string }; levels: { value: string; name: string }[]; sluit: () => void }) {
    const niveauForm = useForm({
        level: creator.level || levels[0]?.value || '',
        reden: '',
    });
    const statusForm = useForm({
        status: creator.status === 'actief' ? 'gepauzeerd' : 'actief',
        reden: '',
    });

    return (
        <Dialog open onOpenChange={(open) => !open && sluit()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{creator.name}</DialogTitle>
                    <DialogDescription>Alleen met reden, komt in het logboek. Nooit automatisch: dit is een besluit van het team.</DialogDescription>
                </DialogHeader>

                {creator.status !== 'geblokkeerd' && (
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            niveauForm.post(niveauRoute.url(creator.id), {
                                preserveScroll: true,
                                onSuccess: sluit,
                            });
                        }}
                    >
                        <h3 className="font-heading font-bold">Niveau handmatig aanpassen</h3>
                        <Veld label="Nieuw niveau" htmlFor="b-niveau" fout={niveauForm.errors.level}>
                            <select id="b-niveau" value={niveauForm.data.level} onChange={(e) => niveauForm.setData('level', e.target.value)} className={invoer}>
                                {levels.map((l) => (
                                    <option key={l.value} value={l.value}>
                                        {l.name}
                                        {l.value === creator.level ? ' (huidig)' : ''}
                                    </option>
                                ))}
                            </select>
                        </Veld>
                        <Veld label="Reden" htmlFor="b-niveau-reden" fout={niveauForm.errors.reden}>
                            <input
                                id="b-niveau-reden"
                                value={niveauForm.data.reden}
                                onChange={(e) => niveauForm.setData('reden', e.target.value)}
                                className={invoer}
                                required
                                minLength={3}
                                maxLength={255}
                            />
                        </Veld>
                        <Button type="submit" disabled={niveauForm.processing || niveauForm.data.level === creator.level}>
                            Niveau aanpassen
                        </Button>
                    </form>
                )}

                <form
                    className="space-y-3 border-t pt-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        statusForm.post(statusRoute.url(creator.id), {
                            preserveScroll: true,
                            onSuccess: sluit,
                        });
                    }}
                >
                    <h3 className="font-heading font-bold">Pauzeren of blokkeren</h3>
                    <p className="text-sm text-muted-foreground">Huidige status: {creator.status}. Geblokkeerde creators staan direct niet meer in het klassement.</p>
                    <Veld label="Nieuwe status" htmlFor="b-status" fout={statusForm.errors.status}>
                        <select id="b-status" value={statusForm.data.status} onChange={(e) => statusForm.setData('status', e.target.value)} className={invoer}>
                            <option value="actief">Actief</option>
                            <option value="gepauzeerd">Gepauzeerd</option>
                            <option value="geblokkeerd">Geblokkeerd</option>
                        </select>
                    </Veld>
                    <Veld label="Reden" htmlFor="b-status-reden" fout={statusForm.errors.reden}>
                        <input
                            id="b-status-reden"
                            value={statusForm.data.reden}
                            onChange={(e) => statusForm.setData('reden', e.target.value)}
                            className={invoer}
                            required
                            minLength={3}
                            maxLength={255}
                        />
                    </Veld>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={sluit}>
                            Annuleren
                        </Button>
                        <Button
                            type="submit"
                            variant={statusForm.data.status === 'geblokkeerd' ? 'destructive' : 'default'}
                            disabled={statusForm.processing || statusForm.data.status === creator.status}
                        >
                            Status opslaan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

Klassement.layout = {
    breadcrumbs: [{ title: 'Klassement', href: team.klassement.url() }],
};
