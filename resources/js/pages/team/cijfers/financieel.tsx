import { Head, Link, router } from "@inertiajs/react";
import {
    CheckCircle2,
    Download,
    FileText,
    Play,
    RefreshCw,
} from "lucide-react";
import { useState } from "react";
import { bedrag } from "@/components/cijfers/cijfers";
import { Button } from "@/components/ui/button";
import { datum, datumTijd } from "@/lib/datum";
import * as team from "@/routes/team";
import * as geld from "@/routes/team/financieel";
import { Leeg, Pagina, PaginaKop } from "@/components/pagina";
import { Fouten, Label, Paneel } from "../_onderdelen/basis";

type Uitbetaling = {
    id: number;
    creator: string;
    company: string;
    collaboration: string;
    collaboration_id: number;
    number: string;
    amount_cents: number;
    run_on: string;
    status: string;
    vat_status: string | null;
    has_details: boolean;
    live_at: string | null;
};

type Run = {
    id: number;
    run_on: string;
    status: "samengesteld" | "uitbetaald";
    total_cents: number;
    exported_at: string | null;
    paid_at: string | null;
    payouts: Uitbetaling[];
};

type Factuur = {
    id: number;
    number: string;
    company: string;
    description: string;
    issued_on: string;
    due_on: string;
    paid_at: string | null;
    total_cents: number;
    subtotal_cents: number;
    status: "concept" | "openstaand" | "te_laat" | "betaald";
    reminders_sent: number;
};

type Props = {
    can_manage: boolean;
    next_friday: string;
    totals: {
        opeisbaar: number;
        opeisbaar_creators: number;
        uitbetaald_maand: number;
        openstaand: number;
        openstaand_aantal: number;
        te_laat_aantal: number;
        gefactureerd_maand: number;
    };
    runs: Run[];
    claimable: Uitbetaling[];
    invoices: Factuur[];
    settings: {
        unused_credit: "meenemen" | "vervallen";
        unused_credit_is_proposal: boolean;
        vat_rate: number;
        payment_days: number;
        reminder_days: number[];
    };
};

const factuurLabel: Record<
    Factuur["status"],
    { tekst: string; kleur: "groen" | "rood" | "amber" | "grijs" }
> = {
    betaald: { tekst: "Betaald", kleur: "groen" },
    te_laat: { tekst: "Te laat", kleur: "rood" },
    openstaand: { tekst: "Openstaand", kleur: "amber" },
    concept: { tekst: "Concept", kleur: "grijs" },
};

const opties = { preserveScroll: true };

export default function Financieel({
    can_manage,
    next_friday,
    totals,
    runs,
    claimable,
    invoices,
    settings,
}: Props) {
    const [filter, setFilter] = useState<"alle" | Factuur["status"]>("alle");
    const lopend = runs.find((r) => r.status === "samengesteld");
    const zichtbaar = invoices.filter(
        (f) => filter === "alle" || f.status === filter,
    );

    return (
        <>
            <Head title="Financieel" />
            <Pagina>
                <PaginaKop
                    titel="Financieel"
                    omschrijving="Betaalruns voor creators en facturen voor ondernemers. Bedragen van vergoedingen zoals afgesproken, facturen inclusief btw."
                />
                <Fouten />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Tegel
                        label="Opeisbaar"
                        waarde={bedrag(totals.opeisbaar)}
                        onder={`${totals.opeisbaar_creators} ${totals.opeisbaar_creators === 1 ? "creator" : "creators"}, run ${datum(next_friday)}`}
                    />
                    <Tegel
                        label="Uitbetaald"
                        waarde={bedrag(totals.uitbetaald_maand)}
                        onder="Deze maand"
                    />
                    <Tegel
                        label="Openstaand"
                        waarde={bedrag(totals.openstaand)}
                        onder={`${totals.openstaand_aantal} ${totals.openstaand_aantal === 1 ? "factuur" : "facturen"}, ${totals.te_laat_aantal} te laat`}
                        nadruk={totals.te_laat_aantal > 0}
                    />
                    <Tegel
                        label="Abonnementen"
                        waarde={bedrag(totals.gefactureerd_maand)}
                        onder="Gefactureerd deze maand, excl. btw"
                    />
                </div>

                <Paneel
                    titel="Betaalrun"
                    sub="Vergoedingen zijn opeisbaar zodra de video Live staat en gaan mee in de run van vrijdag. De betaalpartij is nog niet gekoppeld: uitbetalen gaat via een export voor de bank."
                    acties={
                        can_manage && (
                            <Button
                                onClick={() =>
                                    router.post(
                                        geld.samenstellen.url(),
                                        { run_on: next_friday },
                                        opties,
                                    )
                                }
                                data-test="run-samenstellen"
                            >
                                <RefreshCw /> Run {datum(next_friday)}{" "}
                                samenstellen
                            </Button>
                        )
                    }
                >
                    {claimable.length > 0 && (
                        <div className="mb-4">
                            <h3 className="mb-2 text-sm font-semibold">
                                Opeisbaar, nog niet in een run
                            </h3>
                            <UitbetalingLijst
                                items={claimable}
                                beheer={false}
                            />
                        </div>
                    )}
                    {runs.length === 0 ? (
                        <Leeg titel="Nog geen betaalruns." />
                    ) : (
                        <div className="space-y-3">
                            {runs.map((r) => (
                                <RunBlok
                                    key={r.id}
                                    r={r}
                                    beheer={can_manage}
                                    open={r.id === lopend?.id}
                                />
                            ))}
                        </div>
                    )}
                </Paneel>

                <Paneel
                    titel="Facturen"
                    sub={`Abonnementen worden op de 1e van de maand gefactureerd, betaaltermijn ${settings.payment_days} dagen, btw ${settings.vat_rate}%. Herinnering na ${settings.reminder_days.join(" en ")} dagen.`}
                    acties={
                        can_manage && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        geld.maandfacturen.url(),
                                        {},
                                        opties,
                                    )
                                }
                                data-test="maandfacturen"
                            >
                                <Play /> Maandfacturen nu maken
                            </Button>
                        )
                    }
                >
                    <div
                        className="mb-3 flex flex-wrap gap-2"
                        role="group"
                        aria-label="Filter facturen"
                    >
                        {(
                            [
                                "alle",
                                "openstaand",
                                "te_laat",
                                "betaald",
                            ] as const
                        ).map((f) => (
                            <button
                                key={f}
                                type="button"
                                onClick={() => setFilter(f)}
                                aria-pressed={filter === f}
                                className={`rounded-full border px-3 py-1 text-sm font-semibold ${filter === f ? "border-violet bg-violet text-white" : "hover:border-violet"}`}
                            >
                                {f === "alle" ? "Alle" : factuurLabel[f].tekst}{" "}
                                (
                                {f === "alle"
                                    ? invoices.length
                                    : invoices.filter((x) => x.status === f)
                                          .length}
                                )
                            </button>
                        ))}
                    </div>
                    {zichtbaar.length === 0 ? (
                        <Leeg titel="Geen facturen in deze selectie." />
                    ) : (
                        <ul className="divide-y">
                            {zichtbaar.map((f) => (
                                <li
                                    key={f.id}
                                    className="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1 py-3 md:grid-cols-[2fr_1.4fr_1fr_auto_auto]"
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold">
                                            {f.company}
                                        </span>
                                        <span className="block truncate text-sm text-muted-foreground">
                                            {f.number}, {f.description}
                                        </span>
                                    </span>
                                    <span className="font-heading font-bold tabular-nums md:order-3">
                                        {bedrag(f.total_cents)}
                                    </span>
                                    <span className="text-sm text-muted-foreground md:order-2">
                                        {datum(f.issued_on)} ·{" "}
                                        {f.status === "betaald" && f.paid_at
                                            ? `betaald ${datum(f.paid_at)}`
                                            : `vervalt ${datum(f.due_on)}`}
                                        {f.reminders_sent > 0 &&
                                            `, ${f.reminders_sent} herinnering${f.reminders_sent > 1 ? "en" : ""}`}
                                    </span>
                                    <span className="md:order-4">
                                        <Label
                                            kleur={factuurLabel[f.status].kleur}
                                        >
                                            {factuurLabel[f.status].tekst}
                                        </Label>
                                    </span>
                                    <span className="col-span-2 flex flex-wrap gap-2 md:order-5 md:col-span-1">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a
                                                href={geld.factuur.url(f.id)}
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <FileText /> Factuur
                                            </a>
                                        </Button>
                                        {can_manage &&
                                            f.status !== "betaald" && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            geld.betaald.url(
                                                                f.id,
                                                            ),
                                                            {},
                                                            opties,
                                                        )
                                                    }
                                                >
                                                    Betaald
                                                </Button>
                                            )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Paneel>

                <Paneel
                    titel="Instellingen geld"
                    sub="Open punten zijn instelbaar gebouwd en gemarkeerd als voorstel."
                >
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-xl bg-muted/50 p-3">
                            <dt className="text-sm font-semibold">
                                Ongebruikt tegoed einde maand{" "}
                                {settings.unused_credit_is_proposal && (
                                    <Label kleur="amber">VOORSTEL</Label>
                                )}
                            </dt>
                            <dd className="mt-1 text-sm text-muted-foreground">
                                {settings.unused_credit === "vervallen"
                                    ? "Vervalt op de 1e van de maand."
                                    : "Gaat mee naar de volgende maand."}{" "}
                                Nog niet besloten; instelbaar met
                                MEBRANDING_ONGEBRUIKT_TEGOED (meenemen of
                                vervallen).
                            </dd>
                        </div>
                        <div className="rounded-xl bg-muted/50 p-3">
                            <dt className="text-sm font-semibold">
                                Gegevens op de factuur
                            </dt>
                            <dd className="mt-1 text-sm text-muted-foreground">
                                KvK-nummer, adres, btw-nummer en IBAN van
                                MEBRANDING zijn nog placeholders.
                            </dd>
                        </div>
                    </dl>
                </Paneel>
            </Pagina>
        </>
    );
}

function Tegel({
    label,
    waarde,
    onder,
    nadruk,
}: {
    label: string;
    waarde: string;
    onder: string;
    nadruk?: boolean;
}) {
    return (
        <div className="min-w-0 rounded-2xl border bg-card p-4">
            <span className="block text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </span>
            <span
                className={`mt-1 block truncate font-heading text-2xl font-extrabold tabular-nums md:text-3xl ${nadruk ? "text-rood" : ""}`}
            >
                {waarde}
            </span>
            <span className="mt-1 block text-xs text-muted-foreground">
                {onder}
            </span>
        </div>
    );
}

function RunBlok({
    r,
    beheer,
    open,
}: {
    r: Run;
    beheer: boolean;
    open: boolean;
}) {
    return (
        <details open={open} className="rounded-xl border p-3 md:p-4">
            <summary className="flex cursor-pointer flex-wrap items-center gap-x-3 gap-y-1">
                <span className="font-heading font-bold">
                    Run {datum(r.run_on)}
                </span>
                <Label kleur={r.status === "uitbetaald" ? "groen" : "violet"}>
                    {r.status === "uitbetaald" ? "Uitbetaald" : "Samengesteld"}
                </Label>
                <span className="text-sm text-muted-foreground">
                    {r.payouts.length}{" "}
                    {r.payouts.length === 1 ? "vergoeding" : "vergoedingen"} ·{" "}
                    {bedrag(r.total_cents)}
                </span>
                {r.exported_at && (
                    <span className="text-xs text-muted-foreground">
                        export {datumTijd(r.exported_at)}
                    </span>
                )}
            </summary>
            <div className="mt-3 space-y-3">
                {r.payouts.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Er zit nog niets in deze run.
                    </p>
                ) : (
                    <UitbetalingLijst
                        items={r.payouts}
                        beheer={beheer && r.status !== "uitbetaald"}
                    />
                )}
                {beheer &&
                    r.status === "samengesteld" &&
                    r.payouts.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <a
                                    href={geld.exportMethod.url(r.id)}
                                    data-test="run-export"
                                >
                                    <Download /> Export voor de bank (CSV)
                                </a>
                            </Button>
                            <Button
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            `Is ${bedrag(r.total_cents)} overgemaakt? De creators krijgen dan een melding.`,
                                        )
                                    ) {
                                        router.post(
                                            geld.uitbetaald.url(r.id),
                                            {},
                                            opties,
                                        );
                                    }
                                }}
                                data-test="run-uitbetaald"
                            >
                                <CheckCircle2 /> Markeren als uitbetaald
                            </Button>
                        </div>
                    )}
            </div>
        </details>
    );
}

function UitbetalingLijst({
    items,
    beheer,
}: {
    items: Uitbetaling[];
    beheer: boolean;
}) {
    return (
        <ul className="divide-y rounded-lg border">
            {items.map((p) => (
                <li
                    key={p.id}
                    className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2.5"
                >
                    <span className="min-w-0 basis-full sm:basis-auto sm:flex-1">
                        <span className="block truncate font-semibold">
                            {p.creator}
                        </span>
                        <Link
                            href={team.samenwerking.url(p.collaboration_id)}
                            className="block truncate text-sm text-muted-foreground hover:text-violet"
                        >
                            {p.collaboration}, {p.company}
                            {p.live_at && <> · live {datum(p.live_at)}</>}
                        </Link>
                    </span>
                    <span className="text-xs text-muted-foreground">
                        Btw: {p.vat_status ?? "niet opgegeven"}
                    </span>
                    {!p.has_details && (
                        <Label kleur="rood">
                            Uitbetaalgegevens ontbreken, ingehouden
                        </Label>
                    )}
                    <span className="font-heading font-bold tabular-nums">
                        {bedrag(p.amount_cents)}
                    </span>
                    {beheer && (
                        <button
                            type="button"
                            className="text-sm font-semibold text-violet"
                            onClick={() =>
                                router.post(geld.uitRun.url(p.id), {}, opties)
                            }
                        >
                            Uit run
                        </button>
                    )}
                </li>
            ))}
        </ul>
    );
}

Financieel.layout = {
    breadcrumbs: [{ title: "Financieel", href: team.financieel() }],
};
