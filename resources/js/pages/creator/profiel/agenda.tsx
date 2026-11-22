import { Head, Link } from "@inertiajs/react";
import {
    CalendarPlus,
    Clapperboard,
    MapPin,
    Navigation,
    Upload,
} from "lucide-react";
import * as profiel from "@/routes/creator/profiel";
import { klus } from "@/routes/creator";
import { Kaart, Kop } from "../_onderdelen/opmaak";

type Afspraak = {
    collaboration_id: number;
    company: string;
    title: string;
    status: string;
    address: string;
    kind: "bezoek" | "deadline" | "publicatie";
    label: string;
    at: string;
    end: string | null;
    all_day: boolean;
};

const tz = "Europe/Amsterdam";
const dagSleutel = (a: Afspraak) =>
    a.all_day
        ? a.at
        : new Date(a.at).toLocaleDateString("sv-SE", { timeZone: tz });
const tijd = (iso: string) =>
    new Date(iso).toLocaleTimeString("nl-NL", {
        timeZone: tz,
        hour: "2-digit",
        minute: "2-digit",
    });

function dagKop(sleutel: string): string {
    const vandaag = new Date().toLocaleDateString("sv-SE", { timeZone: tz });
    const morgen = new Date(Date.now() + 864e5).toLocaleDateString("sv-SE", {
        timeZone: tz,
    });
    const lang = new Date(`${sleutel}T12:00:00`).toLocaleDateString("nl-NL", {
        weekday: "long",
        day: "numeric",
        month: "long",
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
            <Kop
                boven="Agenda"
                titel="Je agenda"
                onder={`${upcoming.length} ${upcoming.length === 1 ? "afspraak" : "afspraken"} gepland`}
            />

            <div className="mx-auto w-full max-w-3xl space-y-5 px-4 py-5 md:px-6">
                {upcoming.length === 0 && (
                    <Kaart>
                        <p className="text-muted-foreground">
                            Nog niets gepland. Zodra je een tijdvak kiest voor
                            een bezoek, staat het hier.
                        </p>
                    </Kaart>
                )}

                {Object.entries(dagen).map(([dag, lijst]) => (
                    <section
                        key={dag}
                        aria-labelledby={`dag-${dag}`}
                        className="space-y-2"
                    >
                        <h2
                            id={`dag-${dag}`}
                            className="font-heading text-base font-extrabold"
                        >
                            {dagKop(dag)}
                        </h2>
                        <ul className="space-y-2" data-test="agenda-dag">
                            {lijst.map((a) => (
                                <AfspraakKaart
                                    key={`${a.collaboration_id}-${a.kind}`}
                                    a={a}
                                />
                            ))}
                        </ul>
                    </section>
                ))}

                {past.length > 0 && (
                    <details className="rounded-2xl border bg-card p-4">
                        <summary className="cursor-pointer font-heading font-extrabold">
                            Afgelopen bezoeken ({past.length})
                        </summary>
                        <ul className="mt-3 divide-y">
                            {past.map((a) => (
                                <li key={`${a.collaboration_id}-past`}>
                                    <Link
                                        href={klus(a.collaboration_id)}
                                        className="flex items-center justify-between gap-3 py-2.5 hover:text-violet"
                                    >
                                        <span className="min-w-0">
                                            <span className="block font-semibold break-words">
                                                {a.company}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {new Date(
                                                    a.at,
                                                ).toLocaleDateString("nl-NL", {
                                                    timeZone: tz,
                                                    day: "numeric",
                                                    month: "long",
                                                })}
                                                , {tijd(a.at)}
                                            </span>
                                        </span>
                                        <span className="shrink-0 rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold">
                                            {a.status}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </details>
                )}
            </div>
        </>
    );
}

function AfspraakKaart({ a }: { a: Afspraak }) {
    const Icoon = icoon[a.kind];

    return (
        <li
            className={`rounded-2xl border bg-card p-4 shadow-xs ${a.kind === "bezoek" ? "border-l-4 border-l-violet" : ""}`}
        >
            <div className="flex items-start gap-3">
                <span
                    className="grid size-10 shrink-0 place-items-center rounded-full bg-violet/10 text-violet"
                    aria-hidden
                >
                    <Icoon className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-bold tracking-wider text-violet uppercase">
                        {a.label}
                        {!a.all_day &&
                            `, ${tijd(a.at)}${a.end ? ` tot ${tijd(a.end)}` : ""}`}
                        {a.all_day && ", hele dag"}
                    </p>
                    <Link
                        href={klus(a.collaboration_id)}
                        className="mt-0.5 block font-heading text-lg font-extrabold break-words hover:text-violet"
                    >
                        {a.company}
                    </Link>
                    <p className="text-sm text-muted-foreground">
                        {a.title}
                        {a.kind === "bezoek" && a.address
                            ? `, ${a.address}`
                            : ""}
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
                {a.kind === "bezoek" && a.address && (
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
        </li>
    );
}

Agenda.layout = {
    breadcrumbs: [{ title: "Agenda", href: profiel.agenda.url() }],
};
