import { router } from "@inertiajs/react";
import { Printer } from "lucide-react";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/samenwerking/status-badge";
import type { Maand, Maandrapport, Metrieken } from "@/types/cijfers";
import { BronLabel, getal, PlatformTegels, platformNaam } from "./cijfers";

/** Het maandrapport zoals team en ondernemer het zien. De afdrukbare versie is een losse pagina (Blade). */
export function MaandrapportWeergave({
    r,
    maanden,
    metrics,
    maandUrl,
    afdrukUrl,
}: {
    r: Maandrapport;
    maanden: Maand[];
    metrics: Metrieken;
    maandUrl: (maand: string) => string;
    afdrukUrl: string;
}) {
    const beste = r.publicaties.find((p) => p.metrics.views);

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-end gap-2">
                <label className="text-sm">
                    <span className="mb-1 block font-semibold">Maand</span>
                    <select
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        value={r.maand}
                        onChange={(e) => router.visit(maandUrl(e.target.value))}
                        data-test="maand"
                    >
                        {maanden.map((m) => (
                            <option key={m.value} value={m.value}>
                                {m.label}
                            </option>
                        ))}
                    </select>
                </label>
                <Button asChild variant="outline" className="h-10">
                    <a
                        href={afdrukUrl}
                        target="_blank"
                        rel="noopener"
                        data-test="afdrukken"
                    >
                        <Printer /> Afdrukbare versie
                    </a>
                </Button>
            </div>

            <section className="overflow-hidden rounded-2xl bg-aubergine-dark p-5 text-white md:p-7">
                <span
                    aria-hidden
                    className="mb-3 block h-1 w-10 rounded-full bg-brand-gradient"
                />
                <p className="text-xs font-bold tracking-wider text-white/70 uppercase">
                    Maandrapport · {r.maand_label}
                </p>
                <h2 className="mt-1 font-heading text-2xl font-extrabold md:text-3xl">
                    {r.bedrijf.name}
                </h2>
                <p className="mt-2 max-w-2xl text-sm text-white/80">
                    {r.pakket && <>Pakket {r.pakket}. </>}
                    {r.tellingen.live}{" "}
                    {r.tellingen.live === 1 ? "post" : "posts"} live
                    {beste && (
                        <>
                            , de beste: {beste.title} van {beste.creator} met{" "}
                            {getal(beste.metrics.views?.value)} views op{" "}
                            {platformNaam(beste.platform)}
                        </>
                    )}
                    .{" "}
                    {r.lopend
                        ? "Deze maand loopt nog: dit is een tussenstand."
                        : ""}{" "}
                    Cijfers jonger dan 14 dagen zijn voorlopig.
                </p>
                <dl className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                    {[
                        ["Aangevraagd", r.tellingen.aangevraagd],
                        ["Live gegaan", r.tellingen.live],
                        ["Afgerond", r.tellingen.afgerond],
                        ["Creators", r.tellingen.creators],
                    ].map(([l, w]) => (
                        <div key={l} className="rounded-xl bg-white/10 p-3">
                            <dt className="text-xs text-white/70">{l}</dt>
                            <dd className="font-heading text-2xl font-extrabold">
                                {w}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>

            <section className="space-y-2">
                <h2 className="font-heading text-lg font-bold">
                    Cijfers per platform
                </h2>
                <p className="text-sm text-muted-foreground">
                    Per platform opgeteld, niet over platforms heen: elk
                    platform telt views anders.
                </p>
                {r.platforms.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                        Deze maand ging er geen post live.
                    </p>
                ) : (
                    <PlatformTegels platforms={r.platforms} metrics={metrics} />
                )}
            </section>

            {r.publicaties.length > 0 && (
                <section className="rounded-xl border bg-card p-4 md:p-5">
                    <h2 className="mb-2 font-heading text-lg font-bold">
                        Alle posts op views
                    </h2>
                    <ol className="divide-y">
                        {r.publicaties.map((p, i) => (
                            <li
                                key={p.id}
                                className="flex items-start gap-3 py-2.5"
                            >
                                <span className="w-6 shrink-0 font-heading font-extrabold text-violet">
                                    {i + 1}
                                </span>
                                <span className="min-w-0 flex-1 space-y-1">
                                    <span className="block truncate font-semibold">
                                        {p.title}
                                    </span>
                                    <span className="block text-sm text-muted-foreground">
                                        {p.creator} · {platformNaam(p.platform)}
                                    </span>
                                    <BronLabel
                                        bronnen={p.sources}
                                        schatting={p.is_estimate}
                                    />
                                </span>
                                <span className="shrink-0 text-right font-heading font-bold tabular-nums">
                                    {p.metrics.views
                                        ? getal(p.metrics.views.value)
                                        : "nog geen"}
                                    <span className="block text-xs font-normal text-muted-foreground">
                                        views
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ol>
                </section>
            )}

            <div className="grid gap-4 md:grid-cols-2">
                <section className="rounded-xl border bg-card p-4 md:p-5">
                    <h2 className="mb-2 font-heading text-lg font-bold">
                        Samenwerkingen
                    </h2>
                    {r.samenwerkingen.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Geen samenwerkingen in deze maand.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {r.samenwerkingen.map((c) => (
                                <li
                                    key={c.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2"
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold">
                                            {c.title}
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            {c.location} ·{" "}
                                            {c.creator ?? "nog geen creator"}
                                        </span>
                                    </span>
                                    <StatusBadge status={c.status} />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
                <section className="rounded-xl border bg-card p-4 md:p-5">
                    <h2 className="mb-2 font-heading text-lg font-bold">
                        Tegoed
                    </h2>
                    <dl className="grid grid-cols-2 gap-3">
                        {[
                            ["Toegekend", r.tegoed.toegekend],
                            ["Verbruikt", r.tegoed.verbruikt],
                            ["Terug", r.tegoed.vrijgegeven],
                            [
                                r.lopend ? "Saldo nu" : "Saldo einde maand",
                                r.tegoed.saldo_einde,
                            ],
                        ].map(([l, w]) => (
                            <div key={l} className="rounded-lg bg-muted/60 p-3">
                                <dt className="text-xs text-muted-foreground">
                                    {l}
                                </dt>
                                <dd className="font-heading text-xl font-extrabold">
                                    {w}{" "}
                                    <span className="text-xs font-normal text-muted-foreground">
                                        punten
                                    </span>
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <p className="mt-3 text-xs text-muted-foreground">
                        Ongebruikt tegoed aan het einde van de maand{" "}
                        {r.ongebruikt_tegoed === "vervallen"
                            ? "vervalt"
                            : "gaat mee naar de volgende maand"}
                        . Dit is een voorstel, nog niet besloten.
                    </p>
                </section>
            </div>
        </div>
    );
}
