import { Head, Link, useForm } from "@inertiajs/react";
import { Camera, CheckCircle2, ExternalLink } from "lucide-react";
import {
    getal,
    Herkomst,
    MetingLijst,
    platformNaam,
} from "@/components/cijfers/cijfers";
import { datum, datumTijd } from "@/lib/datum";
import * as creator from "@/routes/creator";
import * as cijfers from "@/routes/creator/cijfers";
import type { Metriek, Metrieken, PublicatieCijfers } from "@/types/cijfers";
import {
    Fout,
    Kaart,
    Kop,
    Uitkomst,
    groteKnop,
    veld,
} from "../_onderdelen/opmaak";

type Props = {
    collaboration: {
        id: number;
        title: string;
        company: string;
        live_at: string | null;
        measure_until: string | null;
        measuring: boolean;
    };
    publications: PublicatieCijfers[];
    history: {
        publication_id: number;
        at: string;
        values: Partial<Record<Metriek, number>>;
        reviewed: boolean;
        screenshot_url: string | null;
    }[];
    metrics: Metrieken;
    measure_days: number;
};

export default function CijfersInvoeren({
    collaboration: c,
    publications,
    history,
    metrics,
    measure_days,
}: Props) {
    return (
        <>
            <Head title={`Cijfers ${c.title}`} />
            <Kop
                boven={<Link href={creator.cijfers.url()}>Cijfers</Link>}
                titel={c.title}
                onder={
                    <>
                        {c.company}
                        {c.measure_until && (
                            <> · meten tot {datum(c.measure_until)}</>
                        )}
                    </>
                }
            />
            <div className="mx-auto w-full max-w-3xl space-y-4 p-4 md:p-6">
                {!c.measuring && (
                    <Uitkomst goed>
                        De meetperiode van {measure_days} dagen is voorbij. De
                        cijfers staan vast; invullen kan niet meer.
                    </Uitkomst>
                )}
                {publications.map((p) => (
                    <Kaart
                        key={p.id}
                        titel={platformNaam(p.platform)}
                        accent={c.measuring}
                    >
                        <a
                            href={p.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mb-3 inline-flex items-center gap-1 text-sm text-violet"
                        >
                            Je post bekijken{" "}
                            <ExternalLink className="size-3.5" aria-hidden />
                        </a>
                        <div className="space-y-2">
                            <MetingLijst p={p} metrics={metrics} />
                            <Herkomst p={p} />
                        </div>
                        {c.measuring && (
                            <Formulier c={c} p={p} metrics={metrics} />
                        )}
                        <Eerder
                            items={history.filter(
                                (h) => h.publication_id === p.id,
                            )}
                            metrics={metrics}
                        />
                    </Kaart>
                ))}
            </div>
        </>
    );
}

function Formulier({
    c,
    p,
    metrics,
}: {
    c: Props["collaboration"];
    p: PublicatieCijfers;
    metrics: Metrieken;
}) {
    const leeg = Object.fromEntries(
        Object.keys(metrics).map((m) => [m, ""]),
    ) as Record<Metriek, string>;
    const form = useForm<
        Record<Metriek, string> & {
            publication_id: number;
            screenshot: File | null;
        }
    >({ ...leeg, publication_id: p.id, screenshot: null });
    const fouten = form.errors as Record<string, string | undefined>;

    return (
        <form
            className="mt-4 space-y-3 border-t pt-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(cijfers.opslaan.url(c.id), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => form.reset(),
                });
            }}
        >
            <p className="text-sm text-muted-foreground">
                Vul de cijfers in zoals ze nu in je app staan (vanaf het moment
                van plaatsen) en voeg een screenshot toe waarop ze te zien zijn.
                Het team kijkt ze na.
            </p>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {(Object.keys(metrics) as Metriek[]).map((m) => (
                    <label
                        key={m}
                        className="block text-sm font-semibold"
                        htmlFor={`c-${p.id}-${m}`}
                    >
                        {metrics[m]}
                        <input
                            id={`c-${p.id}-${m}`}
                            type="number"
                            inputMode="numeric"
                            min={0}
                            className={`${veld} mt-1 font-normal`}
                            value={form.data[m]}
                            onChange={(e) => form.setData(m, e.target.value)}
                            aria-invalid={!!fouten[m]}
                        />
                        <Fout tekst={fouten[m]} />
                    </label>
                ))}
            </div>
            <label
                className="block text-sm font-semibold"
                htmlFor={`c-${p.id}-shot`}
            >
                Screenshot met de cijfers (verplicht)
                <span className="mt-1 flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border-2 border-dashed border-input px-3 py-3 font-normal">
                    <Camera
                        className="size-5 shrink-0 text-violet"
                        aria-hidden
                    />
                    <span className="min-w-0 truncate">
                        {form.data.screenshot
                            ? form.data.screenshot.name
                            : "Kies een screenshot"}
                    </span>
                </span>
                <input
                    id={`c-${p.id}-shot`}
                    type="file"
                    accept="image/*"
                    className="sr-only"
                    onChange={(e) =>
                        form.setData("screenshot", e.target.files?.[0] ?? null)
                    }
                    data-test="screenshot"
                />
            </label>
            <Fout tekst={fouten.screenshot} />
            <button
                type="submit"
                className={groteKnop}
                disabled={form.processing}
                data-test="cijfers-opslaan"
            >
                Cijfers opslaan
            </button>
        </form>
    );
}

function Eerder({
    items,
    metrics,
}: {
    items: Props["history"];
    metrics: Metrieken;
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <details className="mt-4">
            <summary className="cursor-pointer text-sm font-semibold text-violet">
                Eerder ingevuld ({items.length})
            </summary>
            <ul className="mt-2 divide-y text-sm">
                {items.map((h) => (
                    <li key={h.at} className="space-y-1 py-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-semibold">
                                {datumTijd(h.at)}
                            </span>
                            {h.reviewed ? (
                                <span className="inline-flex items-center gap-1 text-emerald-700">
                                    <CheckCircle2
                                        className="size-4"
                                        aria-hidden
                                    />{" "}
                                    Gecontroleerd
                                </span>
                            ) : (
                                <span className="text-muted-foreground">
                                    Wacht op controle
                                </span>
                            )}
                            {h.screenshot_url && (
                                <a
                                    href={h.screenshot_url}
                                    target="_blank"
                                    rel="noopener"
                                    className="text-violet"
                                >
                                    Screenshot
                                </a>
                            )}
                        </div>
                        <p className="text-muted-foreground">
                            {(Object.keys(h.values) as Metriek[])
                                .map(
                                    (m) =>
                                        `${metrics[m]} ${getal(h.values[m])}`,
                                )
                                .join(" · ")}
                        </p>
                    </li>
                ))}
            </ul>
        </details>
    );
}

CijfersInvoeren.layout = {
    breadcrumbs: [{ title: "Cijfers", href: creator.cijfers() }],
};
