import { Head, Link } from '@inertiajs/react';
import { MapPin, MapPinOff } from 'lucide-react';
import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import type { KaartPunt } from '@/components/kaart/kaart-weergave';
import { Kaart, Pagina, PaginaKop } from '@/components/pagina';
import { datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import * as team from '@/routes/team';
import * as bedrijfsbeheer from '@/routes/team/bedrijfsbeheer';

// Leaflet werkt alleen in de browser: pas laden na het tonen van de pagina.
const KaartWeergave = lazy(() => import('@/components/kaart/kaart-weergave'));

type Vestiging = {
    id: number;
    company_id: number;
    bedrijf: string;
    naam: string;
    adres: string;
    branche: string | null;
    lat: number | null;
    lng: number | null;
    precisie: 'adres' | 'plaats' | null;
    gezocht: boolean;
    soort: 'klant' | 'eigen' | 'zonder';
    open_klussen: { id: number; titel: string; nummer: string; status: string }[];
    lopend: number;
};

type Aanmelding = {
    id: number;
    naam: string;
    bedrijf: string | null;
    plaats: string | null;
    adres: string | null;
    branche: string | null;
    aangemeld: string;
    lat: number | null;
    lng: number | null;
    precisie: 'adres' | 'plaats' | null;
    gezocht: boolean;
};

type BeschikbareCreator = { id: number; naam: string; niveau: string; plaats: string | null; tot: string; lat: number | null; lng: number | null };

type Filter = 'klant' | 'eigen' | 'zonder' | 'aanmelding' | 'beschikbaar';

const FILTERS: { key: Filter; naam: string; stip: string }[] = [
    { key: 'klant', naam: 'Klanten', stip: 'bg-violet' },
    { key: 'eigen', naam: 'Eigen zaak', stip: 'bg-brand-gradient' },
    { key: 'zonder', naam: 'Zonder abonnement', stip: 'bg-[#8A86A3]' },
    { key: 'aanmelding', naam: 'Aanmeldingen', stip: 'border-2 border-dashed border-amber-600 bg-white' },
    { key: 'beschikbaar', naam: 'Beschikbare creators', stip: 'bg-emerald-500' },
];

/** Kaart voor het team: aangesloten zaken, aanmeldingen van ondernemers en open klussen. */
export default function KaartPagina({ vestigingen, aanmeldingen, creators = [] }: { vestigingen: Vestiging[]; aanmeldingen: Aanmelding[]; creators?: BeschikbareCreator[] }) {
    const [aan, setAan] = useState<Record<Filter, boolean>>({ klant: true, eigen: true, zonder: true, aanmelding: true, beschikbaar: true });
    const [alleenOpen, setAlleenOpen] = useState(false);
    const [focus, setFocus] = useState<KaartPunt | null>(null);
    const [inBrowser, setInBrowser] = useState(false);
    useEffect(() => setInBrowser(true), []);

    const punten = useMemo<KaartPunt[]>(() => {
        const v = vestigingen
            .filter((x) => x.lat !== null && x.lng !== null && aan[x.soort] && (!alleenOpen || x.open_klussen.length > 0))
            .map((x) => ({
                sleutel: `v${x.id}`,
                lat: x.lat as number,
                lng: x.lng as number,
                kleur: x.soort,
                teller: x.open_klussen.length,
                titel: `${x.bedrijf}, ${x.naam}`,
                popup: <VestigingPopup v={x} />,
            }));
        const a = aanmeldingen
            .filter((x) => x.lat !== null && x.lng !== null && aan.aanmelding && !alleenOpen)
            .map((x) => ({
                sleutel: `a${x.id}`,
                lat: x.lat as number,
                lng: x.lng as number,
                kleur: 'aanmelding' as const,
                titel: `Aanmelding: ${x.bedrijf ?? x.naam}`,
                popup: <AanmeldingPopup a={x} />,
            }));

        const c = creators
            .filter((x) => x.lat !== null && x.lng !== null && aan.beschikbaar && !alleenOpen)
            .map((x) => ({
                sleutel: `c${x.id}`,
                lat: x.lat as number,
                lng: x.lng as number,
                kleur: 'beschikbaar' as const,
                titel: `Beschikbaar: ${x.naam}`,
                popup: (
                    <div className="text-[13px] leading-snug">
                        <p className="text-[11px] font-bold tracking-wide text-emerald-700 uppercase">Beschikbaar</p>
                        <p className="font-heading text-sm font-bold text-aubergine">{x.naam}</p>
                        <p className="text-muted-foreground">
                            {x.niveau} · tot {new Date(x.tot).toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' })}
                        </p>
                    </div>
                ),
            }));

        return [...v, ...a, ...c];
    }, [vestigingen, aanmeldingen, creators, aan, alleenOpen]);

    const nietOpKaart = [
        ...vestigingen.filter((x) => x.lat === null).map((x) => ({ sleutel: `v${x.id}`, naam: `${x.bedrijf}, ${x.naam}`, adres: x.adres, gezocht: x.gezocht })),
        ...aanmeldingen.filter((x) => x.lat === null).map((x) => ({ sleutel: `a${x.id}`, naam: `Aanmelding: ${x.bedrijf ?? x.naam}`, adres: [x.adres, x.plaats].filter(Boolean).join(', '), gezocht: x.gezocht })),
    ];
    const openTotaal = vestigingen.reduce((n, x) => n + x.open_klussen.length, 0);

    return (
        <>
            <Head title="Kaart" />
            <Pagina>
                <PaginaKop
                    titel="Kaart"
                    omschrijving={`${vestigingen.length} ${vestigingen.length === 1 ? 'vestiging' : 'vestigingen'}, ${aanmeldingen.length} nieuwe ${aanmeldingen.length === 1 ? 'aanmelding' : 'aanmeldingen'}, ${openTotaal} open ${openTotaal === 1 ? 'klus' : 'klussen'}.`}
                />

                <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Wat staat er op de kaart">
                    {FILTERS.filter((f) => f.key !== 'beschikbaar' || creators.length > 0).map((f) => (
                        <button
                            key={f.key}
                            type="button"
                            aria-pressed={aan[f.key]}
                            onClick={() => setAan((x) => ({ ...x, [f.key]: !x[f.key] }))}
                            className={cn(
                                'inline-flex min-h-10 items-center gap-2 rounded-full border px-3 text-sm font-semibold transition md:min-h-9',
                                aan[f.key] ? 'border-aubergine bg-card' : 'bg-transparent text-muted-foreground opacity-60',
                            )}
                        >
                            <span className={cn('size-3 rounded-full', f.stip)} aria-hidden />
                            {f.naam}
                        </button>
                    ))}
                    <button
                        type="button"
                        aria-pressed={alleenOpen}
                        onClick={() => setAlleenOpen((x) => !x)}
                        className={cn(
                            'inline-flex min-h-10 items-center gap-2 rounded-full border px-3 text-sm font-semibold transition md:min-h-9',
                            alleenOpen ? 'border-rood bg-rood text-white' : 'bg-card',
                        )}
                    >
                        <span className="grid size-4 place-items-center rounded-full bg-rood text-[10px] text-white ring-2 ring-white" aria-hidden>
                            {openTotaal}
                        </span>
                        Alleen met open klus
                    </button>
                </div>

                <div className="grid gap-5 @4xl:grid-cols-[minmax(0,1fr)_minmax(0,340px)]">
                    <div className="isolate h-[60dvh] min-h-80 overflow-hidden rounded-2xl border bg-muted @4xl:h-[calc(100dvh-15rem)] @4xl:min-h-[30rem]">
                        {inBrowser ? (
                            <Suspense fallback={<p className="p-4 text-sm text-muted-foreground">Kaart laden...</p>}>
                                <KaartWeergave punten={punten} focus={focus} />
                            </Suspense>
                        ) : null}
                    </div>

                    <div className="space-y-4">
                        <Kaart className="p-0">
                            <h2 className="border-b px-4 py-3 font-heading text-base font-bold">Op de kaart ({punten.length})</h2>
                            {punten.length === 0 ? (
                                <p className="p-4 text-sm text-muted-foreground">Niets in deze selectie.</p>
                            ) : (
                                <ul className="max-h-[50dvh] divide-y overflow-y-auto" data-test="kaart-lijst">
                                    {punten.map((p) => (
                                        <li key={p.sleutel}>
                                            <button
                                                type="button"
                                                onClick={() => setFocus({ ...p })}
                                                className="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-muted"
                                            >
                                                <span className={cn('size-3 shrink-0 rounded-full', FILTERS.find((f) => f.key === p.kleur)?.stip)} aria-hidden />
                                                <span className="min-w-0 flex-1 truncate font-semibold">{p.titel}</span>
                                                {p.teller ? (
                                                    <span className="rounded-full bg-rood px-1.5 text-xs font-bold text-white">{p.teller}</span>
                                                ) : null}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Kaart>

                        {nietOpKaart.length > 0 && (
                            <Kaart>
                                <h2 className="mb-2 flex items-center gap-2 font-heading text-base font-bold">
                                    <MapPinOff className="size-4 text-muted-foreground" aria-hidden />
                                    Nog niet op de kaart ({nietOpKaart.length})
                                </h2>
                                <ul className="space-y-1.5 text-sm">
                                    {nietOpKaart.map((x) => (
                                        <li key={x.sleutel}>
                                            <span className="font-semibold">{x.naam}</span>
                                            <span className="block text-xs text-muted-foreground">
                                                {x.gezocht ? `Adres niet gevonden${x.adres ? `: ${x.adres}` : ''}. Vul het adres aan.` : 'Wordt opgezocht.'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </Kaart>
                        )}
                    </div>
                </div>
            </Pagina>
        </>
    );
}

function Precisie({ precisie }: { precisie: 'adres' | 'plaats' | null }) {
    return precisie === 'plaats' ? (
        <span className="mt-1 block text-[11px] text-amber-700">Alleen de plaats bekend: stip staat in het centrum.</span>
    ) : null;
}

function VestigingPopup({ v }: { v: Vestiging }) {
    return (
        <div className="min-w-52 text-[13px] leading-snug">
            <p className="font-heading text-sm font-bold text-aubergine">{v.bedrijf}</p>
            <p className="flex items-start gap-1 text-muted-foreground">
                <MapPin className="mt-0.5 size-3.5 shrink-0" aria-hidden />
                {v.naam !== v.bedrijf ? `${v.naam}, ` : ''}
                {v.adres || 'Geen adres'}
            </p>
            <Precisie precisie={v.precisie} />
            <p className="mt-1.5 text-xs">
                {v.soort === 'eigen' ? 'Eigen zaak, onbeperkt tegoed' : v.soort === 'klant' ? 'Klant met abonnement' : 'Geen abonnement'}
                {v.lopend > 0 ? ` · ${v.lopend} lopend` : ''}
            </p>
            {v.open_klussen.length > 0 && (
                <ul className="mt-2 space-y-1 border-t pt-2">
                    {v.open_klussen.map((k) => (
                        <li key={k.id}>
                            <Link href={team.samenwerking.url(k.id)} className="font-semibold text-violet hover:underline">
                                {k.titel}
                            </Link>
                            <span className="block text-[11px] text-muted-foreground">{k.status}</span>
                        </li>
                    ))}
                </ul>
            )}
            <Link href={bedrijfsbeheer.toon.url(v.company_id)} className="mt-2 inline-block text-xs font-semibold text-violet hover:underline">
                Bedrijf openen
            </Link>
        </div>
    );
}

function AanmeldingPopup({ a }: { a: Aanmelding }) {
    return (
        <div className="min-w-52 text-[13px] leading-snug">
            <p className="text-[11px] font-bold tracking-wide text-amber-700 uppercase">Aanmelding</p>
            <p className="font-heading text-sm font-bold text-aubergine">{a.bedrijf ?? a.naam}</p>
            <p className="text-muted-foreground">{[a.adres, a.plaats].filter(Boolean).join(', ')}</p>
            <Precisie precisie={a.precisie} />
            <p className="mt-1.5 text-xs">
                {a.naam}
                {a.branche ? ` · ${a.branche}` : ''} · {datumTijd(a.aangemeld)}
            </p>
            <Link href={team.bedrijven.url()} className="mt-2 inline-block text-xs font-semibold text-violet hover:underline">
                Naar kennismakingen
            </Link>
        </div>
    );
}
