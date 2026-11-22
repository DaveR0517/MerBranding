import { Head, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { euro } from "@/lib/datum";
import * as beheer from "@/routes/team/beheer";
import {
    intrekken,
    toevoegen,
    versie as versieRoute,
} from "@/routes/team/beheer/pakketten";
import {
    Fouten,
    Kop,
    Label,
    Pagina,
    Paneel,
    Veld,
    invoer,
    morgen,
} from "../_onderdelen/basis";
import { Cijfer, Voorstel, naarEuro } from "./_onderdelen/beheer";

type Versie = {
    id: number;
    nummer: number;
    price_cents: number;
    points_per_month: number;
    valid_from: string;
    is_proposal: boolean;
    status: "geldt" | "gepland" | "vervangen";
    klanten: number;
};
type Pakket = {
    id: number;
    name: string;
    klanten: number;
    huidig_id: number | null;
    versies: Versie[];
};

const dag = (iso: string) =>
    new Date(`${iso}T12:00:00`).toLocaleDateString("nl-NL", {
        weekday: "short",
        day: "numeric",
        month: "short",
        year: "numeric",
    });

/** Eerste dag van de maand, over n maanden, als JJJJ-MM-DD. */
function eersteVan(vandaag: string, n: number): string {
    const [j, m] = vandaag.split("-").map(Number);
    const d = new Date(Date.UTC(j, m - 1 + n, 1));

    return d.toISOString().slice(0, 10);
}

export default function Pakketten({
    pakketten,
    vandaag,
}: {
    pakketten: Pakket[];
    vandaag: string;
}) {
    const [nieuw, setNieuw] = useState(false);
    const klanten = pakketten.reduce((s, p) => s + p.klanten, 0);
    const gepland = pakketten
        .flatMap((p) => p.versies)
        .filter((v) => v.status === "gepland").length;

    return (
        <>
            <Head title="Pakketten en prijzen" />
            <Pagina breed>
                <Kop
                    titel="Pakketten en prijzen"
                    sub="Abonnementen met prijs per maand en punten per maand. Een wijziging is een nieuwe prijsversie met een ingangsdatum."
                    acties={
                        <Button
                            className="bg-violet text-white hover:bg-violet/90"
                            onClick={() => setNieuw(true)}
                        >
                            + Pakket
                        </Button>
                    }
                />

                <p className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <strong>Alles op dit scherm is een voorstel</strong> tot het
                    besloten is: prijzen, punten en namen liggen nog niet vast.
                    Een nieuwe prijs geldt alleen voor nieuwe perioden. Lopende
                    abonnementen houden de versie waarmee ze begonnen.
                </p>

                <Fouten
                    negeer={["name", "price", "points_per_month", "valid_from"]}
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                    <Cijfer
                        label="Pakketten"
                        waarde={pakketten.length}
                        sub={`${pakketten.filter((p) => p.versies.some((v) => v.status === "geldt" && v.is_proposal)).length} met een voorstelprijs`}
                    />
                    <Cijfer
                        label="Klanten op een pakket"
                        waarde={klanten}
                        sub={pakketten
                            .map((p) => `${p.name} ${p.klanten}`)
                            .join(", ")}
                    />
                    <Cijfer
                        label="Geplande wijzigingen"
                        waarde={gepland}
                        sub={
                            gepland
                                ? "Gaan vanzelf in op de ingangsdatum"
                                : "Nog niets ingepland"
                        }
                    />
                </div>

                {nieuw && (
                    <NieuwPakket
                        vandaag={vandaag}
                        sluit={() => setNieuw(false)}
                    />
                )}

                <div className="grid gap-5 lg:grid-cols-2">
                    {pakketten.map((p) => (
                        <PakketKaart key={p.id} pakket={p} vandaag={vandaag} />
                    ))}
                </div>
            </Pagina>
        </>
    );
}

function PakketKaart({ pakket, vandaag }: { pakket: Pakket; vandaag: string }) {
    const [plannen, setPlannen] = useState(false);
    const huidig = pakket.versies.find((v) => v.id === pakket.huidig_id);

    return (
        <Paneel
            titel={
                <span className="flex flex-wrap items-center gap-2">
                    {pakket.name}
                    {huidig?.is_proposal && <Voorstel />}
                </span>
            }
            sub={
                huidig
                    ? `Abonnement · ${euro(huidig.price_cents)} per maand, excl. btw · ${huidig.points_per_month} punten per maand`
                    : "Nog geen geldende versie"
            }
            acties={
                <Label kleur="grijs">
                    {pakket.klanten === 1
                        ? "1 klant"
                        : `${pakket.klanten} klanten`}
                </Label>
            }
        >
            <ol
                className="divide-y rounded-xl border"
                aria-label={`Prijsversies ${pakket.name}`}
            >
                {[...pakket.versies].reverse().map((v) => (
                    <li
                        key={v.id}
                        className="flex flex-wrap items-center gap-x-3 gap-y-1 p-3 text-sm"
                    >
                        <span className="font-mono text-xs text-muted-foreground">
                            v{v.nummer}
                        </span>
                        <span className="font-semibold">
                            {euro(v.price_cents)}
                        </span>
                        <span className="text-muted-foreground">
                            {v.points_per_month} punten
                        </span>
                        <span className="text-muted-foreground">
                            vanaf {dag(v.valid_from)}
                        </span>
                        <span className="ml-auto flex items-center gap-2">
                            {v.is_proposal && <Voorstel />}
                            {v.status === "geldt" && (
                                <Label kleur="groen">Geldt nu</Label>
                            )}
                            {v.status === "gepland" && (
                                <Label kleur="violet">Gepland</Label>
                            )}
                            {v.status === "vervangen" && (
                                <Label kleur="grijs">Vervangen</Label>
                            )}
                            {v.klanten > 0 && (
                                <span className="text-xs text-muted-foreground">
                                    {v.klanten} klant
                                    {v.klanten === 1 ? "" : "en"}
                                </span>
                            )}
                            {v.status === "gepland" && v.klanten === 0 && (
                                <button
                                    type="button"
                                    className="text-xs font-semibold text-rood underline"
                                    onClick={() =>
                                        router.delete(intrekken.url(v.id), {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    Intrekken
                                </button>
                            )}
                        </span>
                    </li>
                ))}
            </ol>

            {plannen ? (
                <Wijziging
                    pakket={pakket}
                    huidig={huidig}
                    vandaag={vandaag}
                    sluit={() => setPlannen(false)}
                />
            ) : (
                <Button
                    variant="outline"
                    className="mt-3 w-full sm:w-auto"
                    onClick={() => setPlannen(true)}
                >
                    Wijziging plannen
                </Button>
            )}
        </Paneel>
    );
}

function Wijziging({
    pakket,
    huidig,
    vandaag,
    sluit,
}: {
    pakket: Pakket;
    huidig?: Versie;
    vandaag: string;
    sluit: () => void;
}) {
    const form = useForm({
        price: huidig ? naarEuro(huidig.price_cents) : "",
        points_per_month: huidig ? String(huidig.points_per_month) : "",
        valid_from: eersteVan(vandaag, 1),
        is_proposal: true,
    });
    const opties = [eersteVan(vandaag, 1), eersteVan(vandaag, 2)];
    const id = `w-${pakket.id}`;

    const verstuur = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            price: String(d.price).replace(",", "."),
        }));
        form.post(versieRoute.url(pakket.id), {
            preserveScroll: true,
            onSuccess: sluit,
        });
    };

    return (
        <form
            onSubmit={verstuur}
            className="mt-4 space-y-3 rounded-xl bg-muted/40 p-3"
            data-test="prijsversie"
        >
            <p className="font-heading text-sm font-bold">Wijziging plannen</p>
            <div className="grid gap-3 sm:grid-cols-2">
                <Veld
                    label="Prijs per maand, excl. btw (€)"
                    htmlFor={`${id}-prijs`}
                    fout={form.errors.price}
                >
                    <input
                        id={`${id}-prijs`}
                        inputMode="decimal"
                        required
                        value={form.data.price}
                        onChange={(e) => form.setData("price", e.target.value)}
                        className={invoer}
                    />
                </Veld>
                <Veld
                    label="Punten per maand"
                    htmlFor={`${id}-punten`}
                    fout={form.errors.points_per_month}
                >
                    <input
                        id={`${id}-punten`}
                        inputMode="numeric"
                        required
                        value={form.data.points_per_month}
                        onChange={(e) =>
                            form.setData("points_per_month", e.target.value)
                        }
                        className={invoer}
                    />
                </Veld>
            </div>
            <fieldset className="space-y-1.5">
                <legend className="text-sm font-semibold">Ingangsdatum</legend>
                <div className="flex flex-wrap gap-2">
                    {opties.map((o) => (
                        <button
                            key={o}
                            type="button"
                            aria-pressed={form.data.valid_from === o}
                            onClick={() => form.setData("valid_from", o)}
                            className={`rounded-full border px-3 py-1.5 text-sm font-semibold ${form.data.valid_from === o ? "border-violet bg-violet text-white" : "bg-background"}`}
                        >
                            {dag(o)}
                        </button>
                    ))}
                    <label htmlFor={`${id}-datum`} className="sr-only">
                        Andere datum
                    </label>
                    <input
                        id={`${id}-datum`}
                        type="date"
                        min={morgen()}
                        value={form.data.valid_from}
                        onChange={(e) =>
                            form.setData("valid_from", e.target.value)
                        }
                        className={`${invoer} w-auto`}
                    />
                </div>
                {form.errors.valid_from && (
                    <p className="text-sm text-rood">
                        {form.errors.valid_from}
                    </p>
                )}
            </fieldset>
            <p className="text-sm text-muted-foreground">
                {pakket.klanten > 0 ? (
                    <>
                        <strong>
                            {pakket.klanten}{" "}
                            {pakket.klanten === 1
                                ? "klant heeft"
                                : "klanten hebben"}
                        </strong>{" "}
                        nu {pakket.name}. Zij houden hun huidige versie; de
                        nieuwe prijs geldt voor nieuwe abonnementen en perioden
                        vanaf de ingangsdatum.
                    </>
                ) : (
                    "Nog geen klanten op dit pakket."
                )}
            </p>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    className="size-4 accent-violet"
                    checked={form.data.is_proposal}
                    onChange={(e) =>
                        form.setData("is_proposal", e.target.checked)
                    }
                />
                Nog een voorstel
            </label>
            <div className="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    className="bg-violet text-white hover:bg-violet/90"
                    disabled={form.processing}
                >
                    Inplannen per ingangsdatum
                </Button>
                <Button type="button" variant="ghost" onClick={sluit}>
                    Annuleren
                </Button>
            </div>
        </form>
    );
}

function NieuwPakket({
    vandaag,
    sluit,
}: {
    vandaag: string;
    sluit: () => void;
}) {
    const form = useForm({
        name: "",
        price: "",
        points_per_month: "",
        valid_from: vandaag,
        is_proposal: true,
    });

    const verstuur = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            price: String(d.price).replace(",", "."),
        }));
        form.post(toevoegen.url(), { preserveScroll: true, onSuccess: sluit });
    };

    return (
        <Paneel
            titel="Nieuw pakket"
            sub="Een nieuw pakket begint met versie 1. Prijs en punten pas je later aan met een nieuwe prijsversie."
        >
            <form
                onSubmit={verstuur}
                className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                data-test="nieuw-pakket"
            >
                <Veld label="Naam" htmlFor="p-naam" fout={form.errors.name}>
                    <input
                        id="p-naam"
                        required
                        value={form.data.name}
                        onChange={(e) => form.setData("name", e.target.value)}
                        className={invoer}
                    />
                </Veld>
                <Veld
                    label="Prijs per maand, excl. btw (€)"
                    htmlFor="p-prijs"
                    fout={form.errors.price}
                >
                    <input
                        id="p-prijs"
                        inputMode="decimal"
                        required
                        value={form.data.price}
                        onChange={(e) => form.setData("price", e.target.value)}
                        className={invoer}
                    />
                </Veld>
                <Veld
                    label="Punten per maand"
                    htmlFor="p-punten"
                    fout={form.errors.points_per_month}
                >
                    <input
                        id="p-punten"
                        inputMode="numeric"
                        required
                        value={form.data.points_per_month}
                        onChange={(e) =>
                            form.setData("points_per_month", e.target.value)
                        }
                        className={invoer}
                    />
                </Veld>
                <Veld
                    label="Ingangsdatum"
                    htmlFor="p-datum"
                    fout={form.errors.valid_from}
                >
                    <input
                        id="p-datum"
                        type="date"
                        min={vandaag}
                        required
                        value={form.data.valid_from}
                        onChange={(e) =>
                            form.setData("valid_from", e.target.value)
                        }
                        className={invoer}
                    />
                </Veld>
                <label className="flex items-center gap-2 text-sm sm:col-span-2">
                    <input
                        type="checkbox"
                        className="size-4 accent-violet"
                        checked={form.data.is_proposal}
                        onChange={(e) =>
                            form.setData("is_proposal", e.target.checked)
                        }
                    />
                    Nog een voorstel
                </label>
                <div className="flex flex-wrap justify-end gap-2 sm:col-span-2">
                    <Button type="button" variant="ghost" onClick={sluit}>
                        Annuleren
                    </Button>
                    <Button
                        type="submit"
                        className="bg-violet text-white hover:bg-violet/90"
                        disabled={form.processing}
                    >
                        Pakket toevoegen
                    </Button>
                </div>
            </form>
        </Paneel>
    );
}

Pakketten.layout = {
    breadcrumbs: [
        { title: "Pakketten en prijzen", href: beheer.pakketten.url() },
    ],
};
