import { Head, Link } from "@inertiajs/react";
import { AlertTriangle, ChevronRight, Gift, Landmark } from "lucide-react";
import { datum, euro } from "@/lib/datum";
import * as profiel from "@/routes/creator/profiel";
import { klus } from "@/routes/creator";
import { Kaart, Kop } from "../_onderdelen/opmaak";

type Uitbetaling = {
    id: number;
    company: string;
    title: string;
    amount_cents: number;
    status: string;
    run_on: string;
    live_at: string | null;
};
type Wachtend = {
    id: number;
    company: string;
    title: string;
    amount_cents: number;
    status: string;
};

type Props = {
    level: string;
    paid_level: boolean;
    fee_cents: number;
    next_run: string;
    totals: {
        open_cents: number;
        paid_year_cents: number;
        waiting_cents: number;
    };
    payouts: Uitbetaling[];
    waiting: Wachtend[];
    payout_details: {
        complete: boolean;
        iban_masked: string | null;
        vat_status: string | null;
    };
};

/** Euro met centen, voor bedragen per samenwerking. */
const bedrag = (c: number) =>
    (c / 100).toLocaleString("nl-NL", { style: "currency", currency: "EUR" });

const statusTekst: Record<string, string> = {
    opeisbaar: "Opeisbaar",
    uitbetaald: "Betaald",
    in_run: "In betaalrun",
};

/** Vergoedingen per samenwerking en de betaalrun op vrijdag (ontwerp CreatorVergoedingen en uitbetalingen). */
export default function Vergoedingen({
    level,
    paid_level,
    fee_cents,
    next_run,
    totals,
    payouts,
    waiting,
    payout_details,
}: Props) {
    const leeg = payouts.length === 0 && waiting.length === 0;

    return (
        <>
            <Head title="Vergoedingen" />
            <Kop
                boven="Geld"
                titel="Vergoedingen"
                onder="Je vergoeding staat klaar zodra je video Live staat. Elke vrijdag is er een betaalrun."
            />

            <div className="mx-auto w-full max-w-3xl space-y-5 px-4 py-5 md:px-6">
                {paid_level || totals.open_cents > 0 ? (
                    <Kaart>
                        <p className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                            Volgende uitbetaling
                        </p>
                        <p
                            className="mt-1 font-heading text-3xl font-extrabold"
                            data-test="open-bedrag"
                        >
                            {bedrag(totals.open_cents)}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Betaalrun {datum(next_run)}, btw volgens jouw opgave
                        </p>
                        <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div className="rounded-xl bg-muted p-3">
                                <dt className="text-muted-foreground">
                                    Wacht op Live
                                </dt>
                                <dd className="font-heading text-lg font-extrabold">
                                    {bedrag(totals.waiting_cents)}
                                </dd>
                            </div>
                            <div className="rounded-xl bg-muted p-3">
                                <dt className="text-muted-foreground">
                                    Uitbetaald dit jaar
                                </dt>
                                <dd className="font-heading text-lg font-extrabold">
                                    {bedrag(totals.paid_year_cents)}
                                </dd>
                            </div>
                        </dl>
                    </Kaart>
                ) : null}

                <Kaart accent>
                    <p className="flex items-start gap-3">
                        <Gift
                            className="mt-0.5 size-5 shrink-0 text-violet"
                            aria-hidden
                        />
                        <span>
                            {paid_level ? (
                                <>
                                    Als {level} krijg je een gratis bezoek en{" "}
                                    {euro(fee_cents)} per samenwerking.
                                </>
                            ) : (
                                <>
                                    <strong>Gratis bezoek, geen geld.</strong>{" "}
                                    Als {level} krijg je een gratis bezoek
                                    (eten, overnachting of entree), betaald door
                                    de ondernemer. Een vergoeding in geld krijg
                                    je vanaf Platinum.
                                </>
                            )}{" "}
                            Je vergoeding volgt je niveau bij acceptatie. Een
                            niveauwijziging raakt lopende samenwerkingen niet.
                        </span>
                    </p>
                </Kaart>

                {!payout_details.complete &&
                    (paid_level ||
                        totals.open_cents > 0 ||
                        totals.waiting_cents > 0) && (
                        <Link
                            href={profiel.account.url()}
                            className="flex items-center gap-3 rounded-2xl border-2 border-rood/40 bg-rood/5 p-4 text-sm font-semibold text-rood"
                            data-test="gegevens-ontbreken"
                        >
                            <AlertTriangle
                                className="size-5 shrink-0"
                                aria-hidden
                            />
                            <span className="flex-1">
                                Vul je uitbetaalgegevens en btw-status in, dan
                                gaat je vergoeding mee in de betaalrun.
                            </span>
                            <ChevronRight
                                className="size-5 shrink-0"
                                aria-hidden
                            />
                        </Link>
                    )}

                <section
                    aria-labelledby="per-samenwerking"
                    className="space-y-3"
                >
                    <h2
                        id="per-samenwerking"
                        className="flex items-baseline justify-between font-heading text-lg font-extrabold"
                    >
                        Per samenwerking
                        <span className="text-xs font-semibold text-muted-foreground">
                            excl. btw
                        </span>
                    </h2>
                    {leeg ? (
                        <Kaart>
                            <p className="text-muted-foreground">
                                {paid_level
                                    ? "Nog geen vergoedingen. Zodra je video Live staat, zie je hem hier."
                                    : "Bij je niveau krijg je geen geld maar een gratis bezoek. Vergoedingen verschijnen hier vanaf Platinum."}
                            </p>
                        </Kaart>
                    ) : (
                        <Kaart>
                            <ul className="divide-y" data-test="vergoedingen">
                                {payouts.map((p) => (
                                    <li
                                        key={`p${p.id}`}
                                        className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                    >
                                        <span className="min-w-0">
                                            <span className="block font-semibold break-words">
                                                {p.company}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {p.live_at
                                                    ? `Live sinds ${datum(p.live_at)}`
                                                    : p.title}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-right">
                                            <span className="block font-heading text-lg font-extrabold">
                                                {bedrag(p.amount_cents)}
                                            </span>
                                            <span
                                                className={`text-xs font-semibold ${p.status === "uitbetaald" ? "text-emerald-700" : "text-violet"}`}
                                            >
                                                {p.status === "uitbetaald"
                                                    ? `Betaald, betaalrun ${datum(p.run_on)}`
                                                    : `${statusTekst[p.status] ?? p.status}, betaalrun ${datum(p.run_on)}`}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                                {waiting.map((w) => (
                                    <li key={`w${w.id}`}>
                                        <Link
                                            href={klus(w.id)}
                                            className="flex items-center justify-between gap-3 py-3 hover:text-violet"
                                        >
                                            <span className="min-w-0">
                                                <span className="block font-semibold break-words">
                                                    {w.company}
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {w.status}
                                                </span>
                                            </span>
                                            <span className="shrink-0 text-right">
                                                <span className="block font-heading text-lg font-extrabold">
                                                    {bedrag(w.amount_cents)}
                                                </span>
                                                <span className="text-xs font-semibold text-muted-foreground">
                                                    Wacht op Live
                                                </span>
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </Kaart>
                    )}
                </section>

                <Kaart
                    titel={
                        <span className="flex items-center gap-2">
                            <Landmark
                                className="size-5 text-violet"
                                aria-hidden
                            />
                            Uitbetaalgegevens
                        </span>
                    }
                >
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                        <dt className="text-muted-foreground">IBAN</dt>
                        <dd className="font-mono">
                            {payout_details.iban_masked ?? "Nog niet ingevuld"}
                        </dd>
                        <dt className="text-muted-foreground">Btw-status</dt>
                        <dd>
                            {payout_details.vat_status ?? "Nog niet gekozen"}
                        </dd>
                    </dl>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Je bent zelf verantwoordelijk voor je btw-aangifte.
                    </p>
                    <Link
                        href={profiel.account.url()}
                        className="mt-2 inline-block font-semibold text-violet hover:underline"
                    >
                        Wijzigen
                    </Link>
                </Kaart>
            </div>
        </>
    );
}

Vergoedingen.layout = {
    breadcrumbs: [{ title: "Vergoedingen", href: profiel.vergoedingen.url() }],
};
