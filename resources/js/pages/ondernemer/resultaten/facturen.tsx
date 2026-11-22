import { Head } from "@inertiajs/react";
import { ChevronRight, Receipt } from "lucide-react";
import { bedrag } from "@/components/cijfers/cijfers";
import { datum } from "@/lib/datum";
import { facturen } from "@/routes/ondernemer";
import { Blok, Kop } from "../_onderdelen/opmaak";

type Factuur = {
    id: number;
    number: string;
    description: string;
    issued_on: string;
    due_on: string;
    total_cents: number;
    status: "concept" | "openstaand" | "te_laat" | "betaald";
    paid_at: string | null;
    url: string;
};

const label: Record<Factuur["status"], { tekst: string; klasse: string }> = {
    betaald: { tekst: "Betaald", klasse: "bg-emerald-100 text-emerald-800" },
    te_laat: { tekst: "Te laat", klasse: "bg-rood/10 text-rood" },
    openstaand: { tekst: "Openstaand", klasse: "bg-amber-100 text-amber-800" },
    concept: { tekst: "Concept", klasse: "bg-muted text-muted-foreground" },
};

export default function Facturen({
    company,
    invoices,
}: {
    company: string;
    invoices: Factuur[];
}) {
    return (
        <>
            <Head title="Facturen" />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
                <Kop
                    onder={`${company}. Je abonnement wordt op de 1e van de maand gefactureerd.`}
                >
                    Facturen
                </Kop>
                {invoices.length === 0 ? (
                    <Blok className="py-10 text-center">
                        <Receipt
                            className="mx-auto size-8 text-violet"
                            aria-hidden
                        />
                        <h2 className="mt-2 font-heading text-xl font-bold">
                            Nog geen facturen
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-muted-foreground">
                            Je eerste factuur komt op de 1e van de volgende
                            maand.
                        </p>
                    </Blok>
                ) : (
                    <Blok className="p-0 md:p-0">
                        <ul className="divide-y">
                            {invoices.map((f) => (
                                <li key={f.id}>
                                    <a
                                        href={f.url}
                                        className="flex items-center gap-3 px-4 py-3 transition hover:bg-violet/5 md:px-6"
                                        data-test="factuur"
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-semibold">
                                                {f.description}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {f.number} ·{" "}
                                                {f.status === "betaald" &&
                                                f.paid_at
                                                    ? `betaald ${datum(f.paid_at)}`
                                                    : `vervalt ${datum(f.due_on)}`}
                                            </span>
                                        </span>
                                        <span
                                            className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${label[f.status].klasse}`}
                                        >
                                            {label[f.status].tekst}
                                        </span>
                                        <span className="font-heading font-bold tabular-nums">
                                            {bedrag(f.total_cents)}
                                        </span>
                                        <ChevronRight
                                            className="size-4 text-muted-foreground"
                                            aria-hidden
                                        />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </Blok>
                )}
            </div>
        </>
    );
}

Facturen.layout = { breadcrumbs: [{ title: "Facturen", href: facturen() }] };
