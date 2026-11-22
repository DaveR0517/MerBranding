import { Head, Link } from "@inertiajs/react";
import { ArrowDownRight, ArrowRight, ArrowUpRight, Minus } from "lucide-react";
import type { ReactNode } from "react";
import {
    Balk,
    getal,
    PlatformTegels,
    Staven,
} from "@/components/cijfers/cijfers";
import { datum, datumTijd, euro } from "@/lib/datum";
import * as team from "@/routes/team";
import type { Metrieken, PlatformTotaal } from "@/types/cijfers";
import { Pagina, PaginaKop } from "@/components/pagina";
import { Paneel } from "../_onderdelen/basis";

type Vergelijk = { nu: number; vorige: number };

type Props = {
    bijgewerkt: string;
    kerncijfers: {
        aanvragen: Vergelijk;
        lopend: number;
        ter_controle: number;
        te_laat: { content: number; controle: number };
        afgerond: Vergelijk;
        oordeel: { nu: number | null; aantal: number; vorige: number | null };
        bedrijven: { actief: number; nieuw: number };
        creators: { actief: number; screening: number };
        tegoed: {
            nu: { toegekend: number; verbruikt: number };
            vorige: { toegekend: number; verbruikt: number };
        };
        opeisbaar: { bedrag: number; aantal: number; vrijdag: string };
        cijfers_controleren: number;
    };
    statussen: {
        value: string;
        label: string;
        number: number;
        count: number;
    }[];
    aanvragen_per_week: { week: string; label: string; aantal: number }[];
    leads_per_week: {
        week: string;
        label: string;
        ondernemer: number;
        creator: number;
    }[];
    metrics: Metrieken;
    platforms: PlatformTotaal[];
};

/** Verschil met de vorige periode als pijl en tekst (kleur is nooit de enige drager). */
function Trend({
    nu,
    vorige,
    eenheid = "",
    omgekeerd = false,
}: {
    nu: number | null;
    vorige: number | null;
    eenheid?: string;
    omgekeerd?: boolean;
}) {
    if (nu === null || vorige === null) {
        return (
            <span className="text-xs text-muted-foreground">
                Nog geen vergelijking
            </span>
        );
    }
    const verschil = Math.round((nu - vorige) * 10) / 10;
    const goed = omgekeerd ? verschil < 0 : verschil > 0;
    const Icoon =
        verschil === 0 ? Minus : verschil > 0 ? ArrowUpRight : ArrowDownRight;

    return (
        <span
            className={`inline-flex items-center gap-0.5 text-xs font-semibold ${verschil === 0 ? "text-muted-foreground" : goed ? "text-emerald-700" : "text-rood"}`}
        >
            <Icoon className="size-3.5" aria-hidden />
            {verschil > 0 ? "+" : ""}
            {verschil.toLocaleString("nl-NL")}
            {eenheid} t.o.v. vorige
        </span>
    );
}

function Tegel({
    label,
    waarde,
    onder,
    href,
    nadruk,
}: {
    label: string;
    waarde: ReactNode;
    onder?: ReactNode;
    href?: string;
    nadruk?: boolean;
}) {
    const inhoud = (
        <>
            <span className="block text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </span>
            <span
                className={`mt-1 block font-heading text-3xl font-extrabold tabular-nums ${nadruk ? "text-rood" : ""}`}
            >
                {waarde}
            </span>
            {onder && (
                <span className="mt-1 block text-xs text-muted-foreground">
                    {onder}
                </span>
            )}
        </>
    );
    const klasse = "block min-w-0 rounded-2xl border bg-card p-4";

    return href ? (
        <Link
            href={href}
            className={`${klasse} transition hover:border-violet`}
        >
            {inhoud}
        </Link>
    ) : (
        <div className={klasse}>{inhoud}</div>
    );
}

export default function Dashboard(p: Props) {
    const k = p.kerncijfers;
    const maxStatus = Math.max(1, ...p.statussen.map((s) => s.count));
    const leads = p.leads_per_week.map((w) => ({
        label: w.label,
        waarde: w.ondernemer + w.creator,
    }));
    const leadsTotaal = p.leads_per_week.reduce(
        (a, w) => ({ o: a.o + w.ondernemer, c: a.c + w.creator }),
        { o: 0, c: 0 },
    );

    return (
        <>
            <Head title="Dashboard" />
            <Pagina>
                <PaginaKop
                    titel="Dashboard"
                    omschrijving={
                        <>
                            Kerncijfers van het hele platform. Bijgewerkt{" "}
                            {datumTijd(p.bijgewerkt)}.
                        </>
                    }
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Tegel
                        label="Nieuwe aanvragen"
                        waarde={k.aanvragen.nu}
                        onder={
                            <>
                                Deze week ·{" "}
                                <Trend
                                    nu={k.aanvragen.nu}
                                    vorige={k.aanvragen.vorige}
                                />
                            </>
                        }
                        href={team.samenwerkingen.url({
                            query: { status: "nieuw" },
                        })}
                    />
                    <Tegel
                        label="Lopend"
                        waarde={k.lopend}
                        onder="Status 1 tot en met 10"
                        href={team.samenwerkingen.url()}
                    />
                    <Tegel
                        label="Ter controle"
                        waarde={k.ter_controle}
                        onder={
                            k.te_laat.controle > 0
                                ? `${k.te_laat.controle} over de controletermijn`
                                : "Binnen de termijn"
                        }
                        href={team.samenwerkingen.url({
                            query: { status: "ter_controle" },
                        })}
                        nadruk={k.te_laat.controle > 0}
                    />
                    <Tegel
                        label="Te laat"
                        waarde={k.te_laat.content}
                        onder="Content over de deadline"
                        nadruk={k.te_laat.content > 0}
                    />
                    <Tegel
                        label="Afgerond"
                        waarde={k.afgerond.nu}
                        onder={
                            <>
                                Deze maand ·{" "}
                                <Trend
                                    nu={k.afgerond.nu}
                                    vorige={k.afgerond.vorige}
                                />
                            </>
                        }
                    />
                    <Tegel
                        label="Klantoordeel"
                        waarde={
                            k.oordeel.nu === null
                                ? "Nog geen"
                                : k.oordeel.nu.toLocaleString("nl-NL")
                        }
                        onder={
                            <>
                                {k.oordeel.aantal}{" "}
                                {k.oordeel.aantal === 1
                                    ? "beoordeling"
                                    : "beoordelingen"}
                                , 90 dagen ·{" "}
                                <Trend
                                    nu={k.oordeel.nu}
                                    vorige={k.oordeel.vorige}
                                />
                            </>
                        }
                    />
                    <Tegel
                        label="Actieve bedrijven"
                        waarde={k.bedrijven.actief}
                        onder={`${k.bedrijven.nieuw} nieuw deze maand`}
                    />
                    <Tegel
                        label="Actieve creators"
                        waarde={k.creators.actief}
                        onder={`${k.creators.screening} in screening`}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Paneel
                        titel="Lopend per status"
                        sub="Nu; Afgerond telt deze maand"
                        className="lg:col-span-2"
                    >
                        <div className="grid gap-x-6 gap-y-2.5 sm:grid-cols-2">
                            {p.statussen.map((s) => (
                                <Link
                                    key={s.value}
                                    href={team.samenwerkingen.url({
                                        query: { status: s.value },
                                    })}
                                    className="block rounded-md hover:bg-violet/5"
                                >
                                    <Balk
                                        label={
                                            <>
                                                <span className="font-semibold text-violet">
                                                    {s.number}.
                                                </span>{" "}
                                                {s.label}
                                            </>
                                        }
                                        waarde={s.count}
                                        max={maxStatus}
                                        kleur={
                                            s.value === "ter_controle"
                                                ? "bg-amber-500"
                                                : "bg-violet"
                                        }
                                    />
                                </Link>
                            ))}
                        </div>
                    </Paneel>

                    <Paneel
                        titel="Geld"
                        acties={
                            <Link
                                href={team.financieel.url()}
                                className="text-sm font-semibold text-violet"
                            >
                                Financieel{" "}
                                <ArrowRight
                                    className="inline size-3.5"
                                    aria-hidden
                                />
                            </Link>
                        }
                    >
                        <dl className="space-y-3">
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Opeisbare vergoedingen
                                </dt>
                                <dd className="font-heading text-2xl font-extrabold">
                                    {euro(k.opeisbaar.bedrag)}
                                </dd>
                                <dd className="text-xs text-muted-foreground">
                                    {k.opeisbaar.aantal}{" "}
                                    {k.opeisbaar.aantal === 1
                                        ? "vergoeding"
                                        : "vergoedingen"}
                                    , betaalrun {datum(k.opeisbaar.vrijdag)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Tegoed deze maand
                                </dt>
                                <dd className="font-heading text-2xl font-extrabold">
                                    {getal(k.tegoed.nu.verbruikt)}{" "}
                                    <span className="text-sm font-semibold text-muted-foreground">
                                        van {getal(k.tegoed.nu.toegekend)}{" "}
                                        punten verbruikt
                                    </span>
                                </dd>
                                <dd>
                                    <Trend
                                        nu={k.tegoed.nu.verbruikt}
                                        vorige={k.tegoed.vorige.verbruikt}
                                        eenheid=" punten"
                                    />
                                </dd>
                            </div>
                            {k.cijfers_controleren > 0 && (
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Handmatige cijfers
                                    </dt>
                                    <dd>
                                        <Link
                                            href={team.cijfers.url()}
                                            className="font-semibold text-violet"
                                        >
                                            {k.cijfers_controleren} te
                                            controleren
                                        </Link>
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </Paneel>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Paneel
                        titel="Nieuwe aanvragen per week"
                        sub="Laatste 8 weken"
                    >
                        <Staven
                            data={p.aanvragen_per_week.map((w) => ({
                                label: w.label,
                                waarde: w.aantal,
                            }))}
                        />
                    </Paneel>
                    <Paneel
                        titel="Aanmeldingen website per week"
                        sub={`Laatste 8 weken: ${leadsTotaal.o} ondernemers en ${leadsTotaal.c} creators`}
                    >
                        <Staven data={leads} kleur="bg-rood" />
                    </Paneel>
                </div>

                <Paneel
                    titel="Cijfers per platform"
                    sub="Posts van de laatste 12 weken, laatste stand per post. Platforms worden niet bij elkaar opgeteld."
                >
                    {p.platforms.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nog geen posts met cijfers.
                        </p>
                    ) : (
                        <PlatformTegels
                            platforms={p.platforms}
                            metrics={p.metrics}
                        />
                    )}
                </Paneel>
            </Pagina>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: "Dashboard", href: team.dashboard() }],
};
