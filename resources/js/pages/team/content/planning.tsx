import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Clock, MapPin } from 'lucide-react';
import { useState } from 'react';
import { Etiket } from '@/components/content/labels';
import { datum, datumTijd } from '@/lib/datum';
import { samenwerking } from '@/routes/team';
import content from '@/routes/team/content';
import { invoer, Kop, Leeg, Pagina, Paneel } from '../_onderdelen/basis';

type Bezoek = {
    id: number;
    number: string;
    title: string;
    status: string;
    status_nr: number | null;
    company: string;
    location: string;
    city: string | null;
    address: string | null;
    experience: string | null;
    creator: string | null;
    level: string | null;
    visit_at: string | null;
    wacht: 'creator' | 'ondernemer' | 'geen';
    vast_op: string | null;
};

type Filters = {
    weergave: 'dag' | 'week';
    datum: string;
    vestiging: number | null;
    creator: number | null;
};

type Props = {
    filters: Filters;
    periode: { van: string; tot: string; week: number };
    visits: Bezoek[];
    unconfirmed: Bezoek[];
    locations: { id: number; name: string }[];
    creators: { id: number; name: string }[];
};

function verschuif(iso: string, dagen: number): string {
    const d = new Date(`${iso}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() + dagen);

    return d.toISOString().slice(0, 10);
}

const tijd = (iso: string) =>
    new Date(iso).toLocaleTimeString('nl-NL', {
        timeZone: 'Europe/Amsterdam',
        hour: '2-digit',
        minute: '2-digit',
    });
const dagVan = (iso: string) =>
    new Date(iso).toLocaleDateString('sv-SE', { timeZone: 'Europe/Amsterdam' });

/** AdminPlanning: agenda van alle bezoeken per dag of week, met filters en de bezoeken zonder bevestiging. */
export default function Planning({
    filters,
    periode,
    visits,
    unconfirmed,
    locations,
    creators,
}: Props) {
    const [gekozen, setGekozen] = useState<Bezoek | null>(visits[0] ?? null);
    const kies = (v: Bezoek) => {
        setGekozen(v);
        // Op de telefoon staat het detail onder de agenda.
        if (window.innerWidth < 1024) {
            requestAnimationFrame(() =>
                document
                    .getElementById('gekozen-bezoek')
                    ?.scrollIntoView({ behavior: 'smooth' }),
            );
        }
    };
    const ga = (wijziging: Partial<Filters>) =>
        router.get(
            content.planning.url(),
            Object.fromEntries(
                Object.entries({ ...filters, ...wijziging }).filter(
                    ([, v]) => v !== null && v !== '',
                ),
            ),
            { preserveScroll: true, preserveState: true, replace: true },
        );
    const stap = filters.weergave === 'dag' ? 1 : 7;
    const dagen = Array.from(
        { length: filters.weergave === 'dag' ? 1 : 7 },
        (_, i) => verschuif(periode.van, i),
    );

    return (
        <>
            <Head title="Planning" />
            <Pagina breed>
                <Kop
                    titel="Planning"
                    sub={
                        filters.weergave === 'week'
                            ? `Week ${periode.week}, ${datum(periode.van)} tot en met ${datum(periode.tot)}`
                            : datum(periode.van)
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-2xl border bg-card p-3">
                    <div className="flex items-center gap-1">
                        <button
                            type="button"
                            onClick={() =>
                                ga({ datum: verschuif(filters.datum, -stap) })
                            }
                            className="grid size-10 place-items-center rounded-full border hover:border-violet"
                            aria-label="Vorige periode"
                        >
                            <ChevronLeft className="size-5" aria-hidden />
                        </button>
                        <button
                            type="button"
                            onClick={() =>
                                ga({ datum: verschuif(filters.datum, stap) })
                            }
                            className="grid size-10 place-items-center rounded-full border hover:border-violet"
                            aria-label="Volgende periode"
                        >
                            <ChevronRight className="size-5" aria-hidden />
                        </button>
                    </div>
                    <div
                        className="flex rounded-full border p-0.5"
                        role="group"
                        aria-label="Weergave"
                    >
                        {(['dag', 'week'] as const).map((w) => (
                            <button
                                key={w}
                                type="button"
                                aria-pressed={filters.weergave === w}
                                onClick={() => ga({ weergave: w })}
                                className={`rounded-full px-3 py-1.5 text-sm font-semibold capitalize ${filters.weergave === w ? 'bg-aubergine text-white' : ''}`}
                            >
                                {w}
                            </button>
                        ))}
                    </div>
                    <label className="grid min-w-0 flex-1 gap-1 text-xs font-semibold sm:flex-none">
                        Datum
                        <input
                            type="date"
                            className={invoer}
                            value={filters.datum}
                            onChange={(e) =>
                                e.target.value && ga({ datum: e.target.value })
                            }
                        />
                    </label>
                    <label className="grid min-w-0 basis-full gap-1 text-xs font-semibold sm:max-w-64 sm:flex-1 sm:basis-auto">
                        Vestiging
                        <select
                            className={invoer}
                            value={filters.vestiging ?? ''}
                            onChange={(e) =>
                                ga({
                                    vestiging: e.target.value
                                        ? Number(e.target.value)
                                        : null,
                                })
                            }
                        >
                            <option value="">Alle vestigingen</option>
                            {locations.map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid min-w-0 basis-full gap-1 text-xs font-semibold sm:max-w-56 sm:flex-1 sm:basis-auto">
                        Creator
                        <select
                            className={invoer}
                            value={filters.creator ?? ''}
                            onChange={(e) =>
                                ga({
                                    creator: e.target.value
                                        ? Number(e.target.value)
                                        : null,
                                })
                            }
                        >
                            <option value="">Alle creators</option>
                            {creators.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <div className="grid gap-5 lg:grid-cols-[1fr_340px] lg:items-start">
                    <div className="min-w-0 space-y-3">
                        <h2 className="font-heading text-base font-bold">
                            Bezoeken{' '}
                            {filters.weergave === 'week'
                                ? 'deze week'
                                : 'op deze dag'}{' '}
                            <span className="text-muted-foreground">
                                ({visits.length})
                            </span>
                        </h2>
                        <div
                            className={`grid gap-3 ${filters.weergave === 'week' ? 'md:grid-cols-2 xl:grid-cols-4' : ''}`}
                        >
                            {dagen.map((dag) => {
                                const lijst = visits.filter(
                                    (v) =>
                                        v.visit_at &&
                                        dagVan(v.visit_at) === dag,
                                );

                                return (
                                    <section
                                        key={dag}
                                        className="rounded-2xl border bg-card p-3"
                                    >
                                        <h3 className="mb-2 text-sm font-bold first-letter:uppercase">
                                            {datum(`${dag}T12:00:00`)}
                                        </h3>
                                        {lijst.length === 0 ? (
                                            <p className="text-xs text-muted-foreground">
                                                Geen bezoeken
                                            </p>
                                        ) : (
                                            <ul className="space-y-1.5">
                                                {lijst.map((v) => (
                                                    <li key={v.id}>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                kies(v)
                                                            }
                                                            aria-pressed={
                                                                gekozen?.id ===
                                                                v.id
                                                            }
                                                            className={`w-full rounded-lg border-l-4 px-2 py-1.5 text-left text-sm transition ${v.wacht === 'ondernemer' ? 'border-l-amber-500 bg-amber-50 dark:bg-amber-900/20' : 'border-l-violet bg-violet/5'} ${gekozen?.id === v.id ? 'ring-2 ring-violet' : ''}`}
                                                        >
                                                            <span className="block font-semibold">
                                                                {v.visit_at &&
                                                                    tijd(
                                                                        v.visit_at,
                                                                    )}{' '}
                                                                {v.company},{' '}
                                                                {v.location}
                                                            </span>
                                                            <span className="block text-xs text-muted-foreground">
                                                                {v.creator ??
                                                                    'Nog geen creator'}
                                                                {v.level
                                                                    ? ` (${v.level})`
                                                                    : ''}
                                                            </span>
                                                        </button>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </section>
                                );
                            })}
                        </div>
                    </div>

                    <div className="space-y-5 lg:sticky lg:top-4">
                        <Paneel titel="Geselecteerd bezoek" id="gekozen-bezoek">
                            {gekozen ? (
                                <BezoekDetail bezoek={gekozen} />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Kies een bezoek in de agenda.
                                </p>
                            )}
                        </Paneel>
                        <Paneel
                            titel="Zonder bevestiging"
                            sub="Wacht op een tijdvak van de creator, of op de ondernemer bij zelf bevestigen."
                        >
                            {unconfirmed.length === 0 ? (
                                <Leeg>Alles is bevestigd.</Leeg>
                            ) : (
                                <ul
                                    className="space-y-2"
                                    data-test="zonder-bevestiging"
                                >
                                    {unconfirmed.map((v) => (
                                        <li key={v.id}>
                                            <Link
                                                href={samenwerking.url(v.id)}
                                                className="block rounded-xl border p-3 hover:border-violet"
                                            >
                                                <Etiket
                                                    kleur={
                                                        v.wacht === 'creator'
                                                            ? 'violet'
                                                            : 'amber'
                                                    }
                                                >
                                                    {v.wacht === 'creator'
                                                        ? 'Wacht op tijdvak van creator'
                                                        : 'Wacht op ondernemer (zelf bevestigen)'}
                                                </Etiket>
                                                <span className="mt-1 block font-semibold">
                                                    {v.company}, {v.location}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {v.title}, {v.creator}
                                                    {v.vast_op &&
                                                        `. Staat vast op ${datumTijd(v.vast_op)}`}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Paneel>
                    </div>
                </div>
            </Pagina>
        </>
    );
}

function BezoekDetail({ bezoek: v }: { bezoek: Bezoek }) {
    return (
        <div className="space-y-3 text-sm">
            <div className="flex flex-wrap gap-1.5">
                <Etiket>
                    {v.status_nr ? `${v.status_nr} ` : ''}
                    {v.status}
                </Etiket>
                {v.status_nr === 4 && (
                    <Etiket
                        kleur={v.wacht === 'ondernemer' ? 'amber' : 'groen'}
                    >
                        {v.wacht === 'ondernemer'
                            ? 'Wacht op ondernemer (zelf bevestigen)'
                            : 'Tijdvak gekozen, staat vast'}
                    </Etiket>
                )}
            </div>
            <p className="font-heading text-base font-bold">{v.title}</p>
            {v.visit_at && (
                <p className="flex items-center gap-2">
                    <Clock
                        className="size-4 text-muted-foreground"
                        aria-hidden
                    />
                    {datumTijd(v.visit_at)}
                </p>
            )}
            <p className="flex items-start gap-2">
                <MapPin
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    aria-hidden
                />
                <span>
                    {v.company}, {v.location}
                    {v.address && (
                        <span className="block text-muted-foreground">
                            {v.address}
                            {v.city ? `, ${v.city}` : ''}
                        </span>
                    )}
                </span>
            </p>
            <p>
                <span className="font-semibold">Creator:</span>{' '}
                {v.creator ?? 'nog niet gekoppeld'}
                {v.level ? ` (${v.level})` : ''}
            </p>
            {v.experience && (
                <p>
                    <span className="font-semibold">
                        Wat de creator ervaart:
                    </span>{' '}
                    {v.experience}
                </p>
            )}
            <Link
                href={samenwerking.url(v.id)}
                className="inline-flex min-h-10 items-center rounded-md bg-violet px-4 font-semibold text-white hover:bg-violet/90"
            >
                Open samenwerking
            </Link>
        </div>
    );
}

Planning.layout = {
    breadcrumbs: [{ title: 'Planning', href: content.planning.url() }],
};
