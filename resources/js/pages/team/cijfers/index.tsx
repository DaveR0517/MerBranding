import { Head, Link, router, useForm } from "@inertiajs/react";
import { Check, ExternalLink, FileImage, Pencil } from "lucide-react";
import { useState } from "react";
import {
    getal,
    Herkomst,
    MetingLijst,
    platformNaam,
} from "@/components/cijfers/cijfers";
import { StatusBadge } from "@/components/samenwerking/status-badge";
import { Button } from "@/components/ui/button";
import { datum, datumTijd } from "@/lib/datum";
import * as team from "@/routes/team";
import * as cijfers from "@/routes/team/cijfers";
import type { Metriek, Metrieken, PublicatieCijfers } from "@/types/cijfers";
import type { SamenwerkingKort } from "@/types/samenwerking";
import {
    Fouten,
    Kop,
    Label,
    Leeg,
    Pagina,
    Paneel,
    Veld,
    invoer,
} from "../_onderdelen/basis";

type Historie = {
    id: number;
    metric: Metriek;
    value: number;
    at: string;
    by: string | null;
    note: string | null;
    replaces_id: number | null;
    reviewed: boolean;
    screenshot_url: string | null;
};

type Rij = SamenwerkingKort & {
    live_at: string | null;
    measure_until: string | null;
    publications: (PublicatieCijfers & { history: Historie[] })[];
};

type Props = {
    filter: number | null;
    collaborations: Rij[];
    metrics: Metrieken;
    companies: { id: number; name: string }[];
};

export default function Cijfers({
    filter,
    collaborations,
    metrics,
    companies,
}: Props) {
    const teControleren = collaborations.filter((c) =>
        c.publications.some((p) => p.unreviewed),
    ).length;
    const [bedrijf, setBedrijf] = useState<string>(
        companies[0] ? String(companies[0].id) : "",
    );

    return (
        <>
            <Head title="Cijfers" />
            <Pagina breed>
                <Kop
                    titel="Cijfers"
                    sub="Cijfers per publicatie, met bron en ophaaldatum. Handmatig ingevoerde cijfers controleer je tegen de screenshot."
                    acties={
                        filter ? (
                            <Button asChild variant="outline">
                                <Link href={team.cijfers.url()}>
                                    Alle publicaties
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />
                <Fouten />

                <div className="grid gap-3 md:grid-cols-[1fr_auto]">
                    <p className="rounded-2xl border bg-card p-4 text-sm">
                        {teControleren > 0 ? (
                            <>
                                <strong className="text-violet">
                                    {teControleren}
                                </strong>{" "}
                                {teControleren === 1
                                    ? "samenwerking heeft"
                                    : "samenwerkingen hebben"}{" "}
                                handmatige cijfers die nog gecontroleerd moeten
                                worden. Ze staan bovenaan.
                            </>
                        ) : (
                            "Alle handmatige cijfers zijn gecontroleerd."
                        )}
                    </p>
                    {companies.length > 0 && (
                        <div className="flex flex-wrap items-end gap-2 rounded-2xl border bg-card p-4">
                            <Veld
                                label="Maandrapport van"
                                htmlFor="rapport-bedrijf"
                            >
                                <select
                                    id="rapport-bedrijf"
                                    className={invoer}
                                    value={bedrijf}
                                    onChange={(e) => setBedrijf(e.target.value)}
                                >
                                    {companies.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </Veld>
                            <Button asChild>
                                <Link
                                    href={team.maandrapport.url({
                                        bedrijf: Number(bedrijf),
                                    })}
                                >
                                    Openen
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                {collaborations.length === 0 ? (
                    <Leeg>
                        Er zijn nog geen publicaties. Zodra een creator een link
                        koppelt (status Live), staan ze hier.
                    </Leeg>
                ) : (
                    collaborations.map((c) => (
                        <Samenwerking key={c.id} c={c} metrics={metrics} />
                    ))
                )}
            </Pagina>
        </>
    );
}

function Samenwerking({ c, metrics }: { c: Rij; metrics: Metrieken }) {
    const open = c.publications.some((p) => p.unreviewed);

    return (
        <Paneel
            titel={
                <Link
                    href={team.samenwerking.url(c.id)}
                    className="hover:text-violet"
                >
                    {c.title}
                </Link>
            }
            sub={
                <>
                    {c.company} · {c.creator ?? "geen creator"} · {c.number}
                    {c.measure_until && (
                        <> · meten tot {datum(c.measure_until)}</>
                    )}
                </>
            }
            acties={
                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge status={c.status} />
                    {open && (
                        <Button
                            size="sm"
                            onClick={() =>
                                router.post(
                                    cijfers.akkoord.url(c.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            data-test="cijfers-akkoord"
                        >
                            <Check /> Klopt met screenshot
                        </Button>
                    )}
                </div>
            }
            className={open ? "border-l-4 border-l-violet" : undefined}
        >
            <div className="space-y-4">
                {c.publications.map((p) => (
                    <Publicatie key={p.id} p={p} metrics={metrics} />
                ))}
            </div>
        </Paneel>
    );
}

function Publicatie({
    p,
    metrics,
}: {
    p: Rij["publications"][number];
    metrics: Metrieken;
}) {
    const [invoeren, setInvoeren] = useState(false);

    return (
        <div className="rounded-xl border p-3 md:p-4">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div className="flex min-w-0 flex-wrap items-center gap-2">
                    <span className="font-heading font-bold">
                        {platformNaam(p.platform)}
                    </span>
                    <a
                        href={p.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex min-w-0 items-center gap-1 text-sm text-violet"
                    >
                        <span className="truncate">Post bekijken</span>{" "}
                        <ExternalLink
                            className="size-3.5 shrink-0"
                            aria-hidden
                        />
                    </a>
                    <span className="text-xs text-muted-foreground">
                        geplaatst {datumTijd(p.posted_at)}
                    </span>
                </div>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setInvoeren(!invoeren)}
                    aria-expanded={invoeren}
                >
                    <Pencil /> Cijfers invoeren
                </Button>
            </div>
            <MetingLijst p={p} metrics={metrics} />
            <div className="mt-2">
                <Herkomst p={p} />
            </div>
            {invoeren && (
                <TeamInvoer
                    p={p}
                    metrics={metrics}
                    klaar={() => setInvoeren(false)}
                />
            )}
            {p.history.length > 0 && (
                <Historie items={p.history} metrics={metrics} />
            )}
        </div>
    );
}

function TeamInvoer({
    p,
    metrics,
    klaar,
}: {
    p: PublicatieCijfers;
    metrics: Metrieken;
    klaar: () => void;
}) {
    const leeg = Object.fromEntries(
        Object.keys(metrics).map((m) => [m, ""]),
    ) as Record<Metriek, string>;
    const form = useForm<
        Record<Metriek, string> & { reason: string; screenshot: File | null }
    >({ ...leeg, reason: "", screenshot: null });
    const fouten = form.errors as Record<string, string | undefined>;

    return (
        <form
            className="mt-3 space-y-3 rounded-lg bg-muted/50 p-3"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(cijfers.invoeren.url(p.id), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: klaar,
                });
            }}
        >
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                {(Object.keys(metrics) as Metriek[]).map((m) => (
                    <Veld
                        key={m}
                        label={metrics[m]}
                        htmlFor={`t-${p.id}-${m}`}
                        fout={fouten[m]}
                    >
                        <input
                            id={`t-${p.id}-${m}`}
                            type="number"
                            inputMode="numeric"
                            min={0}
                            className={invoer}
                            value={form.data[m]}
                            onChange={(e) => form.setData(m, e.target.value)}
                        />
                    </Veld>
                ))}
            </div>
            <div className="grid gap-3 md:grid-cols-2">
                <Veld
                    label="Reden"
                    htmlFor={`t-${p.id}-reden`}
                    fout={fouten.reason}
                    hint="Bijvoorbeeld: screenshot per mail van de creator."
                >
                    <input
                        id={`t-${p.id}-reden`}
                        className={invoer}
                        value={form.data.reason}
                        onChange={(e) => form.setData("reason", e.target.value)}
                        required
                    />
                </Veld>
                <Veld
                    label="Screenshot (mag)"
                    htmlFor={`t-${p.id}-shot`}
                    fout={fouten.screenshot}
                >
                    <input
                        id={`t-${p.id}-shot`}
                        type="file"
                        accept="image/*"
                        className="block w-full text-sm"
                        onChange={(e) =>
                            form.setData(
                                "screenshot",
                                e.target.files?.[0] ?? null,
                            )
                        }
                    />
                </Veld>
            </div>
            <Button type="submit" disabled={form.processing}>
                Opslaan
            </Button>
        </form>
    );
}

function Historie({
    items,
    metrics,
}: {
    items: Historie[];
    metrics: Metrieken;
}) {
    const vervangen = new Set(items.map((i) => i.replaces_id).filter(Boolean));
    const [bewerk, setBewerk] = useState<number | null>(null);

    return (
        <details className="mt-3">
            <summary className="cursor-pointer text-sm font-semibold text-violet">
                Handmatige invoer ({items.length})
            </summary>
            <ul className="mt-2 divide-y text-sm">
                {items.map((h) => (
                    <li
                        key={h.id}
                        className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2"
                    >
                        <span
                            className={`min-w-28 font-semibold ${vervangen.has(h.id) ? "text-muted-foreground line-through" : ""}`}
                        >
                            {metrics[h.metric]}: {getal(h.value)}
                        </span>
                        <span className="text-muted-foreground">
                            {h.by ?? "Onbekend"}, {datumTijd(h.at)}
                            {h.note && <> · {h.note}</>}
                        </span>
                        {h.replaces_id && (
                            <Label kleur="grijs">Correctie</Label>
                        )}
                        {!h.reviewed && <Label>Nog niet gecontroleerd</Label>}
                        {h.screenshot_url && (
                            <a
                                href={h.screenshot_url}
                                target="_blank"
                                rel="noopener"
                                className="inline-flex items-center gap-1 text-violet"
                            >
                                <FileImage className="size-4" aria-hidden />{" "}
                                Screenshot
                            </a>
                        )}
                        {!vervangen.has(h.id) &&
                            (bewerk === h.id ? (
                                <Correctie
                                    h={h}
                                    klaar={() => setBewerk(null)}
                                />
                            ) : (
                                <button
                                    type="button"
                                    className="ml-auto text-sm font-semibold text-violet"
                                    onClick={() => setBewerk(h.id)}
                                >
                                    Corrigeren
                                </button>
                            ))}
                    </li>
                ))}
            </ul>
        </details>
    );
}

function Correctie({ h, klaar }: { h: Historie; klaar: () => void }) {
    const form = useForm({ value: String(h.value), reason: "" });

    return (
        <form
            className="flex w-full flex-wrap items-end gap-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(cijfers.corrigeren.url(h.id), {
                    preserveScroll: true,
                    onSuccess: klaar,
                });
            }}
        >
            <Veld
                label="Juiste waarde"
                htmlFor={`c-${h.id}`}
                fout={form.errors.value}
                className="w-32"
            >
                <input
                    id={`c-${h.id}`}
                    type="number"
                    min={0}
                    className={invoer}
                    value={form.data.value}
                    onChange={(e) => form.setData("value", e.target.value)}
                />
            </Veld>
            <Veld
                label="Reden"
                htmlFor={`cr-${h.id}`}
                fout={form.errors.reason}
                className="min-w-48 flex-1"
            >
                <input
                    id={`cr-${h.id}`}
                    className={invoer}
                    value={form.data.reason}
                    onChange={(e) => form.setData("reason", e.target.value)}
                    required
                />
            </Veld>
            <Button type="submit" size="sm" disabled={form.processing}>
                Corrigeren
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={klaar}>
                Annuleren
            </Button>
        </form>
    );
}

Cijfers.layout = { breadcrumbs: [{ title: "Cijfers", href: team.cijfers() }] };
