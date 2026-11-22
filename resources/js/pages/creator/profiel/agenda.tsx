import { Head, Link } from '@inertiajs/react';
import {
    CalendarPlus,
    Clapperboard,
    MapPin,
    Navigation,
    Upload,
} from 'lucide-react';
import {
    Kaart,
    Leeg,
    MeerLijst,
    Pagina,
    PaginaKop,
    Rij,
    Sectie,
    Uitklap,
} from '@/components/pagina';
import * as profiel from '@/routes/creator/profiel';
import { klus } from '@/routes/creator';

type Afspraak = {
    collaboration_id: number;
    company: string;
    title: string;
    status: string;
    address: string;
    kind: 'bezoek' | 'deadline' | 'publicatie';
    label: string;
    at: string;
    end: string | null;
    all_day: boolean;
};

const tz = 'Europe/Amsterdam';
const dagSleutel = (a: Afspraak) =>
    a.all_day
        ? a.at
        : new Date(a.at).toLocaleDateString('sv-SE', { timeZone: tz });
const tijd = (iso: string) =>
    new Date(iso).toLocaleTimeString('nl-NL', {
        timeZone: tz,
        hour: '2-digit',
        minute: '2-digit',
    });

function dagKop(sleutel: string): string {
    const vandaag = new Date().toLocaleDateString('sv-SE', { timeZone: tz });
    const morgen = new Date(Date.now() + 864e5).toLocaleDateString('sv-SE', {
        timeZone: tz,
    });
    const lang = new Date(`${sleutel}T12:00:00`).toLocaleDateString('nl-NL', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

    return sleutel === vandaag
        ? `Vandaag, ${lang}`
        : sleutel === morgen
          ? `Morgen, ${lang}`
          : lang.charAt(0).toUpperCase() + lang.slice(1);
}

const icoon = { bezoek: MapPin, deadline: Upload, publicatie: Clapperboard };

/** Komende bezoeken, deadlines en publicatiedata per dag (ontwerp CreatorAgenda). */
export default function Agenda({
    upcoming,
    past,
}: {
    upcoming: Afspraak[];
    past: Afspraak[];
}) {
    const dagen = upcoming.reduce<Record<string, Afspraak[]>>((acc, a) => {
        (acc[dagSleutel(a)] ??= []).push(a);

        return acc;
    }, {});

    return (
        <>
            <Head title="Agenda" />
            <Pagina smal>
                <PaginaKop
                    titel="Je agenda"
                    omschrijving={`${upcoming.length} ${upcoming.length === 1 ? 'afspraak' : 'afspraken'} gepland`}
                />

                {upcoming.length === 0 && (
                    <Leeg titel="Nog niets gepland">
                        Zodra je een tijdvak kiest voor een bezoek, staat het
                        hier.
                    </Leeg>
                )}

                {Object.entries(dagen).map(([dag, lijst]) => (
                    <Sectie key={dag} titel={dagKop(dag)}>
                        <ul className="space-y-2" data-test="agenda-dag">
                            {lijst.map((a) => (
                                <AfspraakKaart
                                    key={`${a.collaboration_id}-${a.kind}`}
                                    a={a}
                                />
                            ))}
                        </ul>
                    </Sectie>
                ))}

                {past.length > 0 && (
                    <Uitklap
                        titel="Afgelopen bezoeken"
                        rechts={
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {past.length}
                            </span>
                        }
                    >
                        <MeerLijst>
                            {past.map((a) => (
                                <li key={`${a.collaboration_id}-past`}>
                                    <Link
                                        href={klus(a.collaboration_id)}
                                        className="block"
                                    >
                                        <Rij
                                            className="transition hover:border-violet"
                                            titel={a.company}
                                            onder={`${new Date(a.at).toLocaleDateString('nl-NL', { timeZone: tz, day: 'numeric', month: 'long' })}, ${tijd(a.at)}`}
                                            rechts={
                                                <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold">
                                                    {a.status}
                                                </span>
                                            }
                                        />
                                    </Link>
                                </li>
                            ))}
                        </MeerLijst>
                    </Uitklap>
                )}
            </Pagina>
        </>
    );
}

function AfspraakKaart({ a }: { a: Afspraak }) {
    const Icoon = icoon[a.kind];

    return (
        <li>
            <Kaart
                compact
                className={a.kind === 'bezoek' ? 'border-violet' : undefined}
            >
                <div className="flex items-start gap-3">
                    <span
                        className="grid size-10 shrink-0 place-items-center rounded-full bg-violet/10 text-violet"
                        aria-hidden
                    >
                        <Icoon className="size-5" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-violet">
                            {a.label}
                            {!a.all_day &&
                                `, ${tijd(a.at)}${a.end ? ` tot ${tijd(a.end)}` : ''}`}
                            {a.all_day && ', hele dag'}
                        </p>
                        <Link
                            href={klus(a.collaboration_id)}
                            className="block font-heading text-lg font-bold break-words hover:text-violet"
                        >
                            {a.company}
                        </Link>
                        <p className="text-sm text-muted-foreground">
                            {a.title}
                            {a.kind === 'bezoek' && a.address
                                ? `, ${a.address}`
                                : ''}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {a.status}
                        </p>
                    </div>
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                    <a
                        href={profiel.ics.url({
                            samenwerking: a.collaboration_id,
                            soort: a.kind,
                        })}
                        className="inline-flex min-h-11 items-center gap-2 rounded-xl border-2 px-3 text-sm font-semibold hover:border-violet"
                        data-test="ics"
                    >
                        <CalendarPlus className="size-4" aria-hidden />
                        Toevoegen aan agenda
                    </a>
                    {a.kind === 'bezoek' && a.address && (
                        <a
                            href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(a.address)}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex min-h-11 items-center gap-2 rounded-xl border-2 px-3 text-sm font-semibold hover:border-violet"
                        >
                            <Navigation className="size-4" aria-hidden />
                            Route
                            <span className="sr-only">
                                (opent in een nieuw tabblad)
                            </span>
                        </a>
                    )}
                </div>
            </Kaart>
        </li>
    );
}

Agenda.layout = {
    breadcrumbs: [{ title: 'Agenda', href: profiel.agenda.url() }],
};
