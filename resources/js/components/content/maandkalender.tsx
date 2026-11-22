import { Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { Kalender, KalenderItem } from './types';

const soorten: Record<
    KalenderItem['soort'],
    { naam: string; stip: string; vak: string }
> = {
    bezoek: {
        naam: 'Bezoek',
        stip: 'bg-violet',
        vak: 'border-l-violet bg-violet/5',
    },
    publicatie: {
        naam: 'Publicatie gepland',
        stip: 'bg-amber-500',
        vak: 'border-l-amber-500 bg-amber-50 dark:bg-amber-900/20',
    },
    live: {
        naam: 'Live',
        stip: 'bg-emerald-600',
        vak: 'border-l-emerald-600 bg-emerald-50 dark:bg-emerald-900/20',
    },
    notitie: {
        naam: 'Notitie of idee',
        stip: 'bg-rood',
        vak: 'border-l-rood bg-rood/5',
    },
};

const dagen = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];

/** Alle dagen van de maand, aangevuld tot hele weken (maandag eerst). */
function weken(maand: string): (string | null)[] {
    const [j, m] = maand.split('-').map(Number);
    const eerste = new Date(Date.UTC(j, m - 1, 1));
    const aantal = new Date(Date.UTC(j, m, 0)).getUTCDate();
    const leeg = (eerste.getUTCDay() + 6) % 7;
    const cellen: (string | null)[] = Array.from({ length: leeg }, () => null);
    for (let d = 1; d <= aantal; d++) {
        cellen.push(`${maand}-${String(d).padStart(2, '0')}`);
    }
    while (cellen.length % 7 !== 0) {
        cellen.push(null);
    }

    return cellen;
}

function dagLabel(iso: string): string {
    return new Date(`${iso}T12:00:00`).toLocaleDateString('nl-NL', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

/**
 * Maandkalender (contentkalender.html): bezoeken, publicatiedata en livegangen (alleen lezen) plus eigen notities.
 * Op de telefoon een lijst per dag, vanaf tablet een maandraster.
 */
export function Maandkalender({
    kalender,
    maandUrl,
    samenwerkingUrl,
    notitieUrl,
    verwijderUrl,
}: {
    kalender: Kalender;
    maandUrl: (maand: string) => string;
    samenwerkingUrl?: (id: number) => string;
    notitieUrl?: string;
    verwijderUrl?: (id: number) => string;
}) {
    const [nieuw, setNieuw] = useState<string | null>(null);
    const perDag = kalender.items.reduce<Record<string, KalenderItem[]>>(
        (acc, i) => {
            (acc[i.datum] ??= []).push(i);

            return acc;
        },
        {},
    );
    const vandaag = new Date().toLocaleDateString('sv-SE', {
        timeZone: 'Europe/Amsterdam',
    });

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-1">
                    <Link
                        href={maandUrl(kalender.vorige)}
                        className="grid size-10 place-items-center rounded-full border bg-card hover:border-violet"
                        aria-label="Vorige maand"
                        preserveScroll
                    >
                        <ChevronLeft className="size-5" aria-hidden />
                    </Link>
                    <h2
                        className="min-w-40 text-center font-heading text-lg font-extrabold first-letter:uppercase"
                        aria-live="polite"
                    >
                        {kalender.label}
                    </h2>
                    <Link
                        href={maandUrl(kalender.volgende)}
                        className="grid size-10 place-items-center rounded-full border bg-card hover:border-violet"
                        aria-label="Volgende maand"
                        preserveScroll
                    >
                        <ChevronRight className="size-5" aria-hidden />
                    </Link>
                </div>
                {notitieUrl && (
                    <Button
                        type="button"
                        className="min-h-11 bg-violet hover:bg-violet/90"
                        onClick={() =>
                            setNieuw(
                                nieuw
                                    ? null
                                    : `${kalender.maand}-01` > vandaag
                                      ? `${kalender.maand}-01`
                                      : vandaag,
                            )
                        }
                    >
                        <Plus aria-hidden />
                        Notitie of idee
                    </Button>
                )}
            </div>

            <ul
                className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                aria-label="Legenda"
            >
                {Object.values(soorten).map((s) => (
                    <li key={s.naam} className="flex items-center gap-1.5">
                        <span
                            className={cn('size-2.5 rounded-full', s.stip)}
                            aria-hidden
                        />
                        {s.naam}
                    </li>
                ))}
            </ul>

            {nieuw && notitieUrl && (
                <NotitieFormulier
                    url={notitieUrl}
                    datum={nieuw}
                    onKlaar={() => setNieuw(null)}
                />
            )}

            {/* Raster vanaf tablet */}
            <div className="hidden overflow-hidden rounded-2xl border bg-card md:block">
                <div className="grid grid-cols-7 border-b bg-muted/50 text-center text-xs font-semibold text-muted-foreground uppercase">
                    {dagen.map((d) => (
                        <div key={d} className="py-2">
                            {d}
                        </div>
                    ))}
                </div>
                <div className="grid grid-cols-7">
                    {weken(kalender.maand).map((dag, i) => (
                        <div
                            key={dag ?? `leeg-${i}`}
                            className={cn(
                                'min-h-28 border-r border-b p-1.5 last:border-r-0 [&:nth-child(7n)]:border-r-0',
                                !dag && 'bg-muted/30',
                            )}
                        >
                            {dag && (
                                <>
                                    <button
                                        type="button"
                                        disabled={!notitieUrl}
                                        onClick={() => setNieuw(dag)}
                                        className={cn(
                                            'grid size-7 place-items-center rounded-full text-xs font-semibold enabled:hover:bg-violet/10',
                                            dag === vandaag &&
                                                'bg-aubergine text-white enabled:hover:bg-aubergine',
                                        )}
                                        aria-label={
                                            notitieUrl
                                                ? `Notitie toevoegen op ${dagLabel(dag)}`
                                                : dagLabel(dag)
                                        }
                                    >
                                        {Number(dag.slice(8))}
                                    </button>
                                    <ul className="mt-1 space-y-1">
                                        {(perDag[dag] ?? []).map((item, n) => (
                                            <li key={n}>
                                                <ItemBlok
                                                    item={item}
                                                    compact
                                                    samenwerkingUrl={
                                                        samenwerkingUrl
                                                    }
                                                    verwijderUrl={verwijderUrl}
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            )}
                        </div>
                    ))}
                </div>
            </div>

            {/* Lijst op de telefoon */}
            <div className="space-y-3 md:hidden">
                {Object.keys(perDag).length === 0 ? (
                    <p className="rounded-2xl border border-dashed bg-card p-6 text-center text-sm text-muted-foreground">
                        Niets gepland in {kalender.label}.
                    </p>
                ) : (
                    Object.entries(perDag).map(([dag, items]) => (
                        <section
                            key={dag}
                            className="rounded-2xl border bg-card p-3"
                        >
                            <h3
                                className={cn(
                                    'mb-2 text-sm font-bold first-letter:uppercase',
                                    dag === vandaag && 'text-violet',
                                )}
                            >
                                {dagLabel(dag)}
                            </h3>
                            <ul className="space-y-2">
                                {items.map((item, n) => (
                                    <li key={n}>
                                        <ItemBlok
                                            item={item}
                                            samenwerkingUrl={samenwerkingUrl}
                                            verwijderUrl={verwijderUrl}
                                        />
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))
                )}
            </div>
        </div>
    );
}

function ItemBlok({
    item,
    compact = false,
    samenwerkingUrl,
    verwijderUrl,
}: {
    item: KalenderItem;
    compact?: boolean;
    samenwerkingUrl?: (id: number) => string;
    verwijderUrl?: (id: number) => string;
}) {
    const s = soorten[item.soort];
    const regel = [item.tijd, item.vestiging, item.creator]
        .filter(Boolean)
        .join(', ');
    const inhoud = (
        <>
            <span
                className={cn(
                    'block font-semibold',
                    compact && 'truncate text-xs',
                )}
            >
                {compact ? '' : `${s.naam}: `}
                {item.titel}
            </span>
            {!compact && (regel || item.omschrijving) && (
                <span className="block text-xs text-muted-foreground">
                    {regel || item.omschrijving}
                </span>
            )}
        </>
    );
    const klassen = cn('block rounded-md border-l-4 px-2 py-1 text-sm', s.vak);

    if (item.soort === 'notitie') {
        return (
            <div
                className={cn(
                    klassen,
                    'flex items-start justify-between gap-1',
                )}
                title={item.omschrijving ?? undefined}
            >
                <span className="min-w-0">{inhoud}</span>
                {verwijderUrl && item.id && (
                    <button
                        type="button"
                        className="shrink-0 rounded p-0.5 text-muted-foreground hover:text-rood"
                        aria-label={`Notitie ${item.titel} verwijderen`}
                        onClick={() =>
                            window.confirm('Deze notitie verwijderen?') &&
                            router.delete(verwijderUrl(item.id!), {
                                preserveScroll: true,
                            })
                        }
                    >
                        <Trash2 className="size-3.5" aria-hidden />
                    </button>
                )}
            </div>
        );
    }

    return samenwerkingUrl && item.samenwerking_id ? (
        <Link
            href={samenwerkingUrl(item.samenwerking_id)}
            className={cn(klassen, 'hover:ring-1 hover:ring-violet')}
            title={`${s.naam}: ${item.titel}`}
        >
            {inhoud}
        </Link>
    ) : (
        <div className={klassen} title={`${s.naam}: ${item.titel}`}>
            {inhoud}
        </div>
    );
}

function NotitieFormulier({
    url,
    datum,
    onKlaar,
}: {
    url: string;
    datum: string;
    onKlaar: () => void;
}) {
    const form = useForm({ date: datum, title: '', body: '' });

    function opslaan(e: FormEvent) {
        e.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onKlaar });
    }

    return (
        <form
            onSubmit={opslaan}
            className="grid gap-3 rounded-2xl border bg-card p-4 md:grid-cols-[160px_1fr_1fr_auto] md:items-end"
            noValidate
        >
            <div className="grid gap-1.5">
                <label
                    htmlFor="notitie-datum"
                    className="text-sm font-semibold"
                >
                    Datum
                </label>
                <Input
                    id="notitie-datum"
                    type="date"
                    value={form.data.date}
                    onChange={(e) => form.setData('date', e.target.value)}
                />
                <InputError message={form.errors.date} />
            </div>
            <div className="grid gap-1.5">
                <label
                    htmlFor="notitie-titel"
                    className="text-sm font-semibold"
                >
                    Titel
                </label>
                <Input
                    id="notitie-titel"
                    value={form.data.title}
                    maxLength={120}
                    onChange={(e) => form.setData('title', e.target.value)}
                    placeholder="Bijvoorbeeld: kerstmenu laten zien"
                />
                <InputError message={form.errors.title} />
            </div>
            <div className="grid gap-1.5">
                <label
                    htmlFor="notitie-tekst"
                    className="text-sm font-semibold"
                >
                    Toelichting (optioneel)
                </label>
                <Input
                    id="notitie-tekst"
                    value={form.data.body}
                    maxLength={2000}
                    onChange={(e) => form.setData('body', e.target.value)}
                />
            </div>
            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-10"
                    onClick={onKlaar}
                >
                    Annuleren
                </Button>
                <Button
                    type="submit"
                    className="min-h-10 bg-violet hover:bg-violet/90"
                    disabled={form.processing}
                >
                    Opslaan
                </Button>
            </div>
        </form>
    );
}
