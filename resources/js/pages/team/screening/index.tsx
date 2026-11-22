import { Head, Link } from "@inertiajs/react";
import { AlertTriangle, ChevronRight } from "lucide-react";
import { datumTijd } from "@/lib/datum";
import * as screening from "@/routes/team/screening";
import { Leeg, Pagina, PaginaKop } from "@/components/pagina";
import { Label, initialen } from "../_onderdelen/basis";
import type { Aanmelding } from "./_gedeeld";
import { platformKort, soortNaam } from "./_gedeeld";

const filters: { key: string; naam: string }[] = [
    { key: "nieuw", naam: "Nieuw" },
    { key: "aanvulling", naam: "Aanvulling gevraagd" },
    { key: "creator", naam: "Goedgekeurd" },
    { key: "afgewezen", naam: "Afgewezen" },
];

/** Nieuwe aanmeldingen als creator, oudste eerst (ontwerp AdminCreators, tab Aanmeldingen). */
export default function Index({
    filter,
    counts,
    leads,
}: {
    filter: string;
    counts: Record<string, number>;
    leads: Aanmelding[];
}) {
    return (
        <>
            <Head title="Screening" />
            <Pagina>
                <PaginaKop
                    titel="Screening"
                    omschrijving="Aanmeldingen als creator van de website. Een teamlid besluit altijd, met reden."
                />

                <nav
                    className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:px-0"
                    aria-label="Filter op status"
                >
                    {filters.map((f) => (
                        <Link
                            key={f.key}
                            href={screening.index.url({
                                query: { status: f.key },
                            })}
                            aria-current={filter === f.key ? "page" : undefined}
                            className={`inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-semibold transition ${filter === f.key ? "border-aubergine bg-aubergine text-white" : "bg-card hover:border-violet"}`}
                        >
                            {f.naam}
                            <span
                                className={`rounded-full px-1.5 text-xs ${filter === f.key ? "bg-white/20" : "bg-muted"}`}
                            >
                                {counts[f.key] ?? 0}
                            </span>
                        </Link>
                    ))}
                </nav>

                {leads.length === 0 ? (
                    <Leeg titel={<>{filter === "nieuw"
                            ? "Geen nieuwe aanmeldingen. Alles is bij."
                            : "Geen aanmeldingen in dit filter."}</>} />
                ) : (
                    <ul className="space-y-2" data-test="aanmeldingen">
                        {leads.map((l) => (
                            <li key={l.id}>
                                <Link
                                    href={screening.toon.url(l.id)}
                                    className={`flex items-start gap-3 rounded-2xl border bg-card p-4 transition hover:border-violet ${l.overdue ? "border-rood/40" : ""}`}
                                >
                                    <span
                                        className="grid size-10 shrink-0 place-items-center rounded-full bg-brand-gradient font-heading text-sm font-bold text-white"
                                        aria-hidden
                                    >
                                        {initialen(l.name)}
                                    </span>
                                    <span className="min-w-0 flex-1 text-sm">
                                        <span className="flex flex-wrap items-center gap-1.5">
                                            <span className="font-semibold">
                                                {l.name}
                                            </span>
                                            {l.kind && (
                                                <Label>
                                                    {soortNaam[l.kind] ??
                                                        l.kind}
                                                </Label>
                                            )}
                                        </span>
                                        <span className="mt-0.5 block text-muted-foreground">
                                            {[l.city, l.email]
                                                .filter(Boolean)
                                                .join(" · ")}
                                        </span>
                                        <span className="mt-1 flex flex-wrap gap-1.5">
                                            {l.channels.length === 0 && (
                                                <span className="text-muted-foreground">
                                                    Geen kanalen opgegeven
                                                </span>
                                            )}
                                            {l.channels.map((k) => (
                                                <Label
                                                    key={k.platform}
                                                    kleur={
                                                        k.business
                                                            ? "groen"
                                                            : "grijs"
                                                    }
                                                >
                                                    {platformKort[k.platform] ??
                                                        k.platform}{" "}
                                                    {k.followers ?? k.handle}
                                                    {k.business
                                                        ? ", zakelijk"
                                                        : ""}
                                                </Label>
                                            ))}
                                        </span>
                                        <span
                                            className={`mt-1 flex items-center gap-1 ${l.overdue ? "font-semibold text-rood" : "text-muted-foreground"}`}
                                        >
                                            {l.overdue && (
                                                <AlertTriangle
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                            )}
                                            Aangemeld {datumTijd(l.created_at)}
                                            {l.due_at &&
                                                `, ${l.overdue ? "termijn was" : "reageren voor"} ${datumTijd(l.due_at)}`}
                                        </span>
                                    </span>
                                    <ChevronRight
                                        className="mt-2 size-5 shrink-0 text-muted-foreground"
                                        aria-hidden
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </Pagina>
        </>
    );
}

Index.layout = {
    breadcrumbs: [{ title: "Screening", href: screening.index.url() }],
};
