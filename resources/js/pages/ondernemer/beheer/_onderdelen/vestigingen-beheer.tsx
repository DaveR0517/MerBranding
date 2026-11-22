import { useForm } from "@inertiajs/react";
import { Clock, MapPin, Pencil, Plus, Trash2, X } from "lucide-react";
import type { FormEvent } from "react";
import { useState } from "react";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { Blok, BlokKop, Tekstvak } from "../../_onderdelen/opmaak";
import { DAGEN } from "./types";
import type { Actie, Tijdvak, Vestiging } from "./types";

type Velden = {
    name: string;
    address: string;
    city: string;
    experience: string;
    windows: Tijdvak[];
};

/** Korte samenvatting van de bezoektijden: "ma 10:00 - 16:00, di ...". */
export function tijdenKort(windows: Tijdvak[]): string {
    if (windows.length === 0) {
        return "Nog geen bezoektijden";
    }

    return DAGEN.map((dag, i) => {
        const vakken = windows.filter((w) => w.weekday === i + 1);

        return vakken.length
            ? `${dag.slice(0, 2)} ${vakken.map((w) => `${w.starts_at} - ${w.ends_at}`).join(", ")}`
            : null;
    })
        .filter(Boolean)
        .join(" · ");
}

/**
 * Vestigingen met adres, wat de creator mag ervaren en bezoektijden per weekdag.
 * Gedeeld door het ondernemersportaal en het teamscherm van een bedrijf.
 */
export function VestigingenBeheer({
    locations,
    toevoegen,
    bijwerken,
    kanBewerken = true,
}: {
    locations: Vestiging[];
    toevoegen: Actie;
    bijwerken: (id: number) => Actie;
    kanBewerken?: boolean;
}) {
    const [open, setOpen] = useState<number | "nieuw" | null>(null);

    return (
        <div className="space-y-4">
            {kanBewerken && open !== "nieuw" && (
                <Button
                    onClick={() => setOpen("nieuw")}
                    className="w-full sm:w-auto"
                    data-test="vestiging-toevoegen"
                >
                    <Plus /> Vestiging toevoegen
                </Button>
            )}
            {open === "nieuw" && (
                <VestigingFormulier
                    actie={toevoegen}
                    sluit={() => setOpen(null)}
                />
            )}

            {locations.length === 0 && open !== "nieuw" && (
                <Blok className="text-center text-sm text-muted-foreground">
                    Nog geen vestigingen. Voeg de eerste toe.
                </Blok>
            )}

            <ul className="space-y-3">
                {locations.map((l) =>
                    open === l.id ? (
                        <li key={l.id}>
                            <VestigingFormulier
                                vestiging={l}
                                actie={bijwerken(l.id)}
                                sluit={() => setOpen(null)}
                            />
                        </li>
                    ) : (
                        <li key={l.id}>
                            <Blok
                                className="flex flex-col gap-3 sm:flex-row sm:items-start"
                                data-test="vestiging"
                            >
                                <span
                                    className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet/10 text-violet"
                                    aria-hidden
                                >
                                    <MapPin className="size-5" />
                                </span>
                                <div className="min-w-0 flex-1 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="font-heading text-lg font-bold">
                                            {l.name}
                                        </h3>
                                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">
                                            {l.active === 0
                                                ? "Geen lopende samenwerkingen"
                                                : `${l.active} lopend`}
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {[l.address, l.city]
                                            .filter(Boolean)
                                            .join(", ") || "Geen adres"}
                                    </p>
                                    <p className="flex items-start gap-1.5 text-sm">
                                        <Clock
                                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                            aria-hidden
                                        />
                                        <span className="min-w-0 break-words">
                                            {tijdenKort(l.windows)}
                                        </span>
                                    </p>
                                    {l.experience && (
                                        <p className="text-sm">
                                            <span className="font-semibold">
                                                Ervaring:{" "}
                                            </span>
                                            {l.experience}
                                        </p>
                                    )}
                                </div>
                                {kanBewerken && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setOpen(l.id)}
                                        className="self-start"
                                    >
                                        <Pencil /> Bewerken
                                    </Button>
                                )}
                            </Blok>
                        </li>
                    ),
                )}
            </ul>
        </div>
    );
}

function VestigingFormulier({
    vestiging,
    actie,
    sluit,
}: {
    vestiging?: Vestiging;
    actie: Actie;
    sluit: () => void;
}) {
    const form = useForm<Velden>({
        name: vestiging?.name ?? "",
        address: vestiging?.address ?? "",
        city: vestiging?.city ?? "",
        experience: vestiging?.experience ?? "",
        windows:
            vestiging?.windows ??
            [1, 2, 3, 4, 5].map((d) => ({
                weekday: d,
                starts_at: "10:00",
                ends_at: "16:00",
            })),
    });
    const { data, setData, errors, processing } = form;
    const fout = errors as Record<string, string | undefined>;
    const id = vestiging ? `v${vestiging.id}` : "nieuw";

    const zetVak = (
        index: number,
        veld: "starts_at" | "ends_at",
        waarde: string,
    ) =>
        setData(
            "windows",
            data.windows.map((w, i) =>
                i === index ? { ...w, [veld]: waarde } : w,
            ),
        );
    const voegToe = (dag: number) => {
        const laatste = [...data.windows]
            .filter((w) => w.weekday === dag)
            .pop();
        const start = laatste ? laatste.ends_at : "10:00";
        const eind = laatste
            ? `${String(Math.min(23, Number(laatste.ends_at.slice(0, 2)) + 2)).padStart(2, "0")}:00`
            : "16:00";
        setData("windows", [
            ...data.windows,
            { weekday: dag, starts_at: start, ends_at: eind },
        ]);
    };
    const verwijder = (index: number) =>
        setData(
            "windows",
            data.windows.filter((_, i) => i !== index),
        );
    const neemOver = (dag: number) => {
        const bron = data.windows.filter((w) => w.weekday === dag);
        setData("windows", [
            ...bron,
            ...[1, 2, 3, 4, 5, 6, 7]
                .filter((d) => d !== dag)
                .flatMap((d) => bron.map((w) => ({ ...w, weekday: d }))),
        ]);
    };

    function opslaan(e: FormEvent) {
        e.preventDefault();
        form.submit(actie.method, actie.url, {
            preserveScroll: true,
            onSuccess: () => sluit(),
        });
    }

    return (
        <Blok className="border-violet/40">
            <form
                onSubmit={opslaan}
                className="space-y-5"
                noValidate
                data-test="vestiging-formulier"
            >
                <BlokKop
                    rechts={
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={sluit}
                            aria-label="Sluiten zonder opslaan"
                        >
                            <X />
                        </Button>
                    }
                >
                    {vestiging
                        ? `${vestiging.name} bewerken`
                        : "Nieuwe vestiging"}
                </BlokKop>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1.5 sm:col-span-2">
                        <Label htmlFor={`${id}-name`}>
                            Naam van de vestiging
                        </Label>
                        <Input
                            id={`${id}-name`}
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor={`${id}-address`}>Adres</Label>
                        <Input
                            id={`${id}-address`}
                            value={data.address}
                            onChange={(e) => setData("address", e.target.value)}
                            autoComplete="street-address"
                        />
                        <InputError message={errors.address} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor={`${id}-city`}>Plaats</Label>
                        <Input
                            id={`${id}-city`}
                            value={data.city}
                            onChange={(e) => setData("city", e.target.value)}
                            required
                            autoComplete="address-level2"
                        />
                        <InputError message={errors.city} />
                    </div>
                    <div className="space-y-1.5 sm:col-span-2">
                        <Label htmlFor={`${id}-experience`}>
                            Wat mag een creator ervaren?
                        </Label>
                        <Tekstvak
                            id={`${id}-experience`}
                            value={data.experience}
                            onChange={(e) =>
                                setData("experience", e.target.value)
                            }
                            className="min-h-20"
                            placeholder="Bijvoorbeeld: lunch voor 1 persoon, inclusief drankje en dessert."
                        />
                        <p className="text-xs text-muted-foreground">
                            De creator ziet dit vooraf in de samenwerking.
                        </p>
                        <InputError message={errors.experience} />
                    </div>
                </div>

                <fieldset className="space-y-2">
                    <legend className="font-heading font-bold">
                        Tijdvakken voor bezoeken
                    </legend>
                    <p className="text-sm text-muted-foreground">
                        De creator kiest zelf een moment binnen deze tijden. Een
                        bezoek duurt meestal 1 uur. Geen tijdvak op een dag
                        betekent: geen bezoek die dag.
                    </p>
                    <ul className="divide-y rounded-lg border">
                        {DAGEN.map((dag, d) => {
                            const weekday = d + 1;
                            const vakken = data.windows
                                .map((w, i) => ({ w, i }))
                                .filter(({ w }) => w.weekday === weekday);

                            return (
                                <li
                                    key={dag}
                                    className="grid gap-2 p-3 sm:grid-cols-[7rem_1fr] sm:items-start"
                                >
                                    <span className="pt-2 text-sm font-semibold capitalize">
                                        {dag}
                                    </span>
                                    <div className="space-y-2">
                                        {vakken.length === 0 && (
                                            <p className="pt-2 text-sm text-muted-foreground">
                                                Geen bezoeken
                                            </p>
                                        )}
                                        {vakken.map(({ w, i }) => (
                                            <div key={i}>
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        type="time"
                                                        step={900}
                                                        value={w.starts_at}
                                                        onChange={(e) =>
                                                            zetVak(
                                                                i,
                                                                "starts_at",
                                                                e.target.value,
                                                            )
                                                        }
                                                        aria-label={`${dag}, begintijd`}
                                                        className="w-28"
                                                    />
                                                    <span aria-hidden>tot</span>
                                                    <Input
                                                        type="time"
                                                        step={900}
                                                        value={w.ends_at}
                                                        onChange={(e) =>
                                                            zetVak(
                                                                i,
                                                                "ends_at",
                                                                e.target.value,
                                                            )
                                                        }
                                                        aria-label={`${dag}, eindtijd`}
                                                        className="w-28"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            verwijder(i)
                                                        }
                                                        aria-label={`Tijdvak op ${dag} verwijderen`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </div>
                                                <InputError
                                                    message={
                                                        fout[
                                                            `windows.${i}.starts_at`
                                                        ] ??
                                                        fout[
                                                            `windows.${i}.ends_at`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        ))}
                                        <div className="flex flex-wrap gap-x-4 gap-y-1">
                                            <button
                                                type="button"
                                                onClick={() => voegToe(weekday)}
                                                className="text-sm font-semibold text-violet hover:underline"
                                            >
                                                + Tijdvak
                                            </button>
                                            {vakken.length > 0 && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        neemOver(weekday)
                                                    }
                                                    className="text-sm text-muted-foreground hover:text-foreground hover:underline"
                                                >
                                                    Zelfde tijden op alle dagen
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                    <InputError message={errors.windows} />
                </fieldset>

                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" onClick={sluit}>
                        Annuleren
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}{" "}
                        {vestiging
                            ? "Wijzigingen opslaan"
                            : "Vestiging toevoegen"}
                    </Button>
                </div>
            </form>
        </Blok>
    );
}
