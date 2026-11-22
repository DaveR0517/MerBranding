import { Head, Link, useForm } from "@inertiajs/react";
import { RotateCcw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { datumTijd } from "@/lib/datum";
import * as team from "@/routes/team";
import * as beheer from "@/routes/team/beheer";
import { opslaan } from "@/routes/team/beheer/instellingen";
import { Pagina, PaginaKop, Tabs } from "@/components/pagina";
import { Paneel, invoer, useTab } from "../_onderdelen/basis";
import { Voorstel, naarEuro } from "./_onderdelen/beheer";

type Waarde = number | string | boolean;
type Instelling = {
    key: string;
    groep: string;
    label: string;
    type: "getal" | "bedrag" | "tijd" | "aan_uit";
    eenheid: string | null;
    uitleg: string;
    voorstel: boolean;
    standaard: Waarde;
    alleen_super_admin: boolean;
    waarde: Waarde;
    is_proposal: boolean;
    gewijzigd: { door: string | null; op: string | null } | null;
};

/** Waarde uit de config naar wat in het invoerveld staat. */
function naarInvoer(i: Instelling, w: Waarde): string | boolean {
    if (i.type === "aan_uit") {
        return Boolean(w);
    }

    return i.type === "bedrag" ? naarEuro(Number(w)) : String(w);
}

function leesbaar(i: Instelling, w: Waarde): string {
    if (i.type === "aan_uit") {
        return w ? "aan" : "uit";
    }

    return i.type === "bedrag"
        ? `€ ${naarEuro(Number(w))}`
        : `${w}${i.eenheid && i.eenheid !== "tijd" ? ` ${i.eenheid}` : ""}`;
}

export default function Instellingen({
    instellingen,
    is_super_admin,
}: {
    instellingen: Instelling[];
    is_super_admin: boolean;
}) {
    const form = useForm({
        waarden: Object.fromEntries(
            instellingen.map((i) => [i.key, naarInvoer(i, i.waarde)]),
        ) as Record<string, string | boolean>,
        voorstel: Object.fromEntries(
            instellingen.map((i) => [i.key, i.is_proposal]),
        ) as Record<string, boolean>,
    });
    const groepen = [...new Set(instellingen.map((i) => i.groep))];
    const [groep, setGroep] = useTab("instellingen", groepen[0] ?? "", groepen);
    const errors = form.errors as Record<string, string>;
    const aantalVoorstel = Object.values(form.data.voorstel).filter(
        Boolean,
    ).length;

    const zetWaarde = (key: string, w: string | boolean) =>
        form.setData("waarden", { ...form.data.waarden, [key]: w });
    const zetVoorstel = (key: string, v: boolean) =>
        form.setData("voorstel", { ...form.data.voorstel, [key]: v });

    const verstuur = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(opslaan.url(), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    return (
        <>
            <Head title="Instellingen" />
            <Pagina>
                <PaginaKop
                    titel="Instellingen"
                    omschrijving="Zelf beheren zonder ontwikkelaar. Een wijziging geldt voor stappen na het opslaan en komt met naam, oude en nieuwe waarde in het logboek."
                    acties={
                        <Button variant="outline" asChild>
                            <Link href={team.logboek.url()}>
                                Logboek bekijken
                            </Link>
                        </Button>
                    }
                />

                <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <strong>
                        {aantalVoorstel} van de {instellingen.length} waarden
                        zijn nog een voorstel.
                    </strong>{" "}
                    Ze werken al, maar zijn niet besloten. Haal het vinkje weg
                    zodra een waarde vaststaat.
                </p>

                <form
                    onSubmit={verstuur}
                    className="space-y-5"
                    data-test="instellingen"
                >
                    <Tabs
                        tabs={groepen.map((g) => ({
                            waarde: g,
                            label: instellingen.some(
                                (i) =>
                                    i.groep === g && errors[`waarden.${i.key}`],
                            )
                                ? `${g} (fout)`
                                : g,
                            aantal: instellingen.filter((i) => i.groep === g)
                                .length,
                        }))}
                        actief={groep}
                        onKies={setGroep}
                    />
                    {groepen
                        .filter((g) => g === groep)
                        .map((g) => (
                            <Paneel
                                key={g}
                                titel={g}
                                sub={
                                    g === "Niveaus creators"
                                        ? "Alleen Platinum en Elite krijgen geld (besloten 18-11-2026). De andere niveaus krijgen een gratis bezoek."
                                        : undefined
                                }
                            >
                                <ul className="divide-y">
                                    {instellingen
                                        .filter((i) => i.groep === g)
                                        .map((i) => {
                                            const id = `i-${i.key.replaceAll(".", "-")}`;
                                            const dicht =
                                                i.alleen_super_admin &&
                                                !is_super_admin;
                                            const w = form.data.waarden[i.key];
                                            const afwijkend =
                                                naarInvoer(i, i.standaard) !==
                                                w;

                                            return (
                                                <li
                                                    key={i.key}
                                                    className="grid gap-3 py-3 first:pt-0 last:pb-0 md:grid-cols-[minmax(0,1fr)_minmax(0,260px)] md:items-start"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="flex flex-wrap items-center gap-2">
                                                            <label
                                                                htmlFor={id}
                                                                className="font-semibold"
                                                            >
                                                                {i.label}
                                                            </label>
                                                            {form.data.voorstel[
                                                                i.key
                                                            ] && <Voorstel />}
                                                        </p>
                                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                                            {i.uitleg}
                                                        </p>
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            Standaard{" "}
                                                            {leesbaar(
                                                                i,
                                                                i.standaard,
                                                            )}
                                                            {i.gewijzigd?.op &&
                                                                ` · gewijzigd ${datumTijd(i.gewijzigd.op)}${i.gewijzigd.door ? ` door ${i.gewijzigd.door}` : ""}`}
                                                        </p>
                                                    </div>
                                                    <div className="space-y-2">
                                                        <div className="flex items-center gap-2">
                                                            {i.type ===
                                                            "aan_uit" ? (
                                                                <label className="flex min-h-10 items-center gap-2 text-sm font-semibold">
                                                                    <input
                                                                        id={id}
                                                                        type="checkbox"
                                                                        className="size-5 accent-violet"
                                                                        checked={Boolean(
                                                                            w,
                                                                        )}
                                                                        disabled={
                                                                            dicht
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            zetWaarde(
                                                                                i.key,
                                                                                e
                                                                                    .target
                                                                                    .checked,
                                                                            )
                                                                        }
                                                                    />
                                                                    {w
                                                                        ? "Aan"
                                                                        : "Uit"}
                                                                </label>
                                                            ) : (
                                                                <>
                                                                    {i.type ===
                                                                        "bedrag" && (
                                                                        <span className="text-sm text-muted-foreground">
                                                                            €
                                                                        </span>
                                                                    )}
                                                                    <input
                                                                        id={id}
                                                                        type={
                                                                            i.type ===
                                                                            "tijd"
                                                                                ? "time"
                                                                                : "text"
                                                                        }
                                                                        inputMode={
                                                                            i.type ===
                                                                            "getal"
                                                                                ? "numeric"
                                                                                : i.type ===
                                                                                    "bedrag"
                                                                                  ? "decimal"
                                                                                  : undefined
                                                                        }
                                                                        required
                                                                        value={String(
                                                                            w,
                                                                        )}
                                                                        disabled={
                                                                            dicht
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            zetWaarde(
                                                                                i.key,
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        className={`${invoer} max-w-36`}
                                                                    />
                                                                    {i.eenheid &&
                                                                        ![
                                                                            "tijd",
                                                                            "euro",
                                                                        ].includes(
                                                                            i.eenheid,
                                                                        ) && (
                                                                            <span className="text-sm text-muted-foreground">
                                                                                {
                                                                                    i.eenheid
                                                                                }
                                                                            </span>
                                                                        )}
                                                                </>
                                                            )}
                                                            {afwijkend &&
                                                                !dicht && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            zetWaarde(
                                                                                i.key,
                                                                                naarInvoer(
                                                                                    i,
                                                                                    i.standaard,
                                                                                ),
                                                                            )
                                                                        }
                                                                        className="ml-auto inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-violet hover:bg-violet/5"
                                                                        aria-label={`${i.label}: standaard herstellen`}
                                                                    >
                                                                        <RotateCcw
                                                                            className="size-3.5"
                                                                            aria-hidden
                                                                        />{" "}
                                                                        Standaard
                                                                    </button>
                                                                )}
                                                        </div>
                                                        {!dicht && (
                                                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                <input
                                                                    type="checkbox"
                                                                    className="size-4 accent-violet"
                                                                    checked={
                                                                        form
                                                                            .data
                                                                            .voorstel[
                                                                            i
                                                                                .key
                                                                        ]
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        zetVoorstel(
                                                                            i.key,
                                                                            e
                                                                                .target
                                                                                .checked,
                                                                        )
                                                                    }
                                                                />
                                                                Nog een voorstel
                                                            </label>
                                                        )}
                                                        {dicht && (
                                                            <p className="text-xs text-muted-foreground">
                                                                Alleen een Super
                                                                Admin wijzigt
                                                                dit.
                                                            </p>
                                                        )}
                                                        {errors[
                                                            `waarden.${i.key}`
                                                        ] && (
                                                            <p className="text-sm text-rood">
                                                                {
                                                                    errors[
                                                                        `waarden.${i.key}`
                                                                    ]
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                </li>
                                            );
                                        })}
                                </ul>
                            </Paneel>
                        ))}

                    {errors.waarden && (
                        <p className="text-sm text-rood">{errors.waarden}</p>
                    )}

                    <div className="sticky bottom-20 z-10 flex flex-wrap items-center justify-end gap-3 rounded-2xl border bg-card/95 p-3 shadow-sm backdrop-blur md:bottom-4">
                        <p className="mr-auto text-xs text-muted-foreground">
                            {form.isDirty
                                ? "Niet opgeslagen wijzigingen."
                                : "Alles is opgeslagen."}
                        </p>
                        <Button
                            type="submit"
                            className="bg-violet text-white hover:bg-violet/90"
                            disabled={form.processing || !form.isDirty}
                        >
                            {form.processing
                                ? "Bezig..."
                                : "Wijzigingen opslaan"}
                        </Button>
                    </div>
                </form>
            </Pagina>
        </>
    );
}

Instellingen.layout = {
    breadcrumbs: [{ title: "Instellingen", href: beheer.instellingen.url() }],
};
