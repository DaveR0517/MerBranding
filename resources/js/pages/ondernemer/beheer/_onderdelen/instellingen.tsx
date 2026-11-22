import { router } from "@inertiajs/react";
import { Checkbox } from "@/components/ui/checkbox";
import { cn } from "@/lib/utils";
import { Blok, BlokKop } from "../../_onderdelen/opmaak";
import type { Actie, Instellingen } from "./types";

/**
 * Instellingen per bedrijf: hoe een bezoekmoment vastkomt (W2) en wie de video keurt (W3).
 * Gedeeld door ondernemer (alleen eigenaar wijzigt) en team.
 */
export function BedrijfsInstellingen({
    waarden,
    actie,
    kanWijzigen,
    bedrijf,
    voorTeam = false,
}: {
    waarden: Instellingen;
    actie: Actie;
    kanWijzigen: boolean;
    bedrijf: string;
    voorTeam?: boolean;
}) {
    const zet = (veld: keyof Instellingen, waarde: boolean) =>
        router.visit(actie.url, {
            method: actie.method,
            data: { [veld]: waarde },
            preserveScroll: true,
        });

    const keuzes = [
        {
            waarde: false,
            titel: "Direct vast (standaard)",
            tekst: `De creator kiest een tijdvak uit de bezoektijden. Het bezoek staat meteen vast en ${voorTeam ? "de ondernemer krijgt" : "jij krijgt"} een bericht.`,
        },
        {
            waarde: true,
            titel: "Zelf bevestigen",
            tekst: `${voorTeam ? "De ondernemer krijgt" : "Je krijgt"} 24 uur om te bevestigen of een ander moment voor te stellen. Geen reactie: het bezoek staat vast.`,
        },
    ];

    return (
        <Blok className="space-y-6">
            <fieldset disabled={!kanWijzigen} className="space-y-3">
                <legend className="w-full">
                    <BlokKop>Bezoekmoment bevestigen</BlokKop>
                </legend>
                <p className="-mt-2 text-sm text-muted-foreground">
                    Geldt voor alle vestigingen van {bedrijf}.
                </p>
                <div
                    className="grid gap-2 md:grid-cols-2"
                    role="radiogroup"
                    aria-label="Bezoekmoment bevestigen"
                >
                    {keuzes.map((k) => {
                        const gekozen =
                            waarden.self_confirm_visits === k.waarde;

                        return (
                            <button
                                key={k.titel}
                                type="button"
                                role="radio"
                                aria-checked={gekozen}
                                disabled={!kanWijzigen}
                                onClick={() =>
                                    !gekozen &&
                                    zet("self_confirm_visits", k.waarde)
                                }
                                className={cn(
                                    "rounded-xl border p-4 text-left transition disabled:cursor-not-allowed",
                                    gekozen
                                        ? "border-violet bg-violet/5 ring-1 ring-violet"
                                        : "hover:border-violet/60",
                                )}
                            >
                                <span className="flex items-center gap-2 font-semibold">
                                    <span
                                        aria-hidden
                                        className={cn(
                                            "grid size-4 place-items-center rounded-full border-2",
                                            gekozen
                                                ? "border-violet"
                                                : "border-muted-foreground/40",
                                        )}
                                    >
                                        {gekozen && (
                                            <span className="size-2 rounded-full bg-violet" />
                                        )}
                                    </span>
                                    {k.titel}
                                </span>
                                <span className="mt-1 block text-sm text-muted-foreground">
                                    {k.tekst}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </fieldset>

            <div className="space-y-2 border-t pt-5">
                <h3 className="font-heading font-bold">Wie keurt de video?</h3>
                <p className="text-sm text-muted-foreground">
                    Standaard controleert het team van MEBRANDING elke video en{" "}
                    {voorTeam ? "kijkt de ondernemer" : "kijk jij"} mee:
                    opmerkingen geven mag altijd.
                </p>
                <label
                    className={cn(
                        "flex items-start gap-3 rounded-xl border p-4",
                        kanWijzigen
                            ? "cursor-pointer"
                            : "cursor-not-allowed opacity-80",
                    )}
                >
                    <Checkbox
                        checked={waarden.owner_approves_content}
                        disabled={!kanWijzigen}
                        onCheckedChange={(v) =>
                            zet("owner_approves_content", v === true)
                        }
                        className="mt-0.5"
                        data-test="zelf-keuren"
                    />
                    <span className="text-sm">
                        <span className="block font-semibold">
                            {voorTeam
                                ? "De ondernemer keurt de video zelf"
                                : "Ik keur de video zelf"}
                        </span>
                        <span className="block text-muted-foreground">
                            {voorTeam ? "De ondernemer krijgt" : "Je krijgt"}{" "}
                            een herinnering na 3 en na 6 werkdagen. Is er dan
                            nog geen besluit, dan keurt MEBRANDING de video
                            zodat de samenwerking doorloopt. Standaard uit.
                        </span>
                    </span>
                </label>
                <p className="text-xs text-muted-foreground">
                    Let op: de keten gebruikt deze instelling nog niet. Tot die
                    stap gebouwd is, keurt het team elke video.
                </p>
            </div>

            {!kanWijzigen && (
                <p className="text-sm text-muted-foreground">
                    Alleen een eigenaar van het bedrijf kan deze instellingen
                    wijzigen.
                </p>
            )}
        </Blok>
    );
}
