import { Ban, Check, MapPin, Search } from "lucide-react";
import { useState } from "react";
import { MeerLijst } from "@/components/pagina";
import { ProfielLink } from "@/components/social/profiel-link";
import { cn } from "@/lib/utils";

/** Een creator zoals de ondernemer die mag zien (Matchlijst::voorOndernemer): openbaar profiel en prijs in punten. */
export type KiesbareCreator = {
    id: number;
    name: string;
    level: string;
    level_value: string;
    city: string | null;
    instagram: string | null;
    tiktok: string | null;
    niches: string[];
    kind: string;
    points: number;
    vanaf_25: boolean;
    reasons: string[];
    blocked: string | null;
};

/** Waarom deze creator nu niet kan, of null. Dezelfde regels als op de server (Samenwerking::uitnodigen). */
export function blokkade(
    c: KiesbareCreator,
    alcohol: boolean,
    beschikbaar: number,
): string | null {
    if (c.blocked) {
        return c.blocked;
    }
    if (alcohol && !c.vanaf_25) {
        return "Bij alcohol in beeld alleen creators van 25 jaar of ouder";
    }
    if (c.points > beschikbaar) {
        return `Kost ${c.points} punten, je hebt er ${beschikbaar}`;
    }

    return null;
}

/**
 * Zelf een creator kiezen: een keuzelijst (radioknoppen) met zoeken.
 * Wie niet kan (alcohol, tegoed, al afgeslagen) staat onderaan met de reden.
 */
export function CreatorKiezen({
    creators,
    gekozen,
    onKies,
    alcohol,
    stad,
    beschikbaar,
}: {
    creators: KiesbareCreator[];
    gekozen: number | null;
    onKies: (id: number) => void;
    alcohol: boolean;
    stad: string | null;
    beschikbaar: number;
}) {
    const [zoek, setZoek] = useState("");
    const term = zoek.trim().toLowerCase();
    const lijst = creators
        .filter(
            (c) =>
                !term ||
                [c.name, c.city ?? "", c.level, ...c.niches]
                    .join(" ")
                    .toLowerCase()
                    .includes(term),
        )
        .map((c) => ({
            c,
            reden: blokkade(c, alcohol, beschikbaar),
            dichtbij: !!stad && c.city === stad,
        }))
        .sort(
            (a, b) =>
                Number(!!a.reden) - Number(!!b.reden) ||
                Number(b.dichtbij) - Number(a.dichtbij),
        );

    if (creators.length === 0) {
        return (
            <p className="rounded-xl bg-muted p-3 text-sm text-muted-foreground">
                Er zijn nog geen creators beschikbaar. Laat MEBRANDING kiezen,
                dan zoeken wij iemand voor je.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {creators.length > 6 && (
                <div className="relative">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <label htmlFor="zoek-creator" className="sr-only">
                        Zoek een creator
                    </label>
                    <input
                        id="zoek-creator"
                        type="search"
                        value={zoek}
                        onChange={(e) => setZoek(e.target.value)}
                        placeholder="Zoek op naam, plaats of niche"
                        className="h-10 w-full rounded-lg border bg-background pr-3 pl-9 text-sm"
                    />
                </div>
            )}
            <div
                role="radiogroup"
                aria-label="Kies een creator"
                data-test="creator-kiezen"
            >
                <MeerLijst eerst={6} label="Meer creators">
                    {lijst.map(({ c, reden, dichtbij }) => {
                        const actief = gekozen === c.id;

                        return (
                            <li key={c.id}>
                                <label
                                    data-test="kies-creator"
                                    className={cn(
                                        "flex cursor-pointer items-start gap-3 rounded-xl border-2 p-3 transition has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50",
                                        reden &&
                                            "cursor-not-allowed bg-muted/50 text-muted-foreground",
                                        actief
                                            ? "border-violet bg-violet/5"
                                            : "border-border",
                                        !reden &&
                                            !actief &&
                                            "hover:border-violet/50",
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="creator_id"
                                        value={c.id}
                                        checked={actief}
                                        disabled={!!reden}
                                        onChange={() => onKies(c.id)}
                                        className="sr-only"
                                    />
                                    <span
                                        aria-hidden
                                        className={cn(
                                            "grid size-10 shrink-0 place-items-center rounded-full font-heading text-sm font-bold",
                                            reden
                                                ? "bg-muted"
                                                : "bg-brand-gradient text-white",
                                        )}
                                    >
                                        {actief ? (
                                            <Check className="size-5" />
                                        ) : (
                                            c.name
                                                .split(" ")
                                                .map((w) => w[0])
                                                .slice(0, 2)
                                                .join("")
                                        )}
                                    </span>
                                    <span className="min-w-48 flex-1 text-sm">
                                        <span className="flex flex-wrap items-baseline justify-between gap-x-3">
                                            <span className="font-semibold text-foreground">
                                                {c.name}
                                            </span>
                                            <span className="font-semibold whitespace-nowrap text-violet">
                                                {c.level}, {c.points} punten
                                            </span>
                                        </span>
                                        <span className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-muted-foreground">
                                            {c.city && (
                                                <span className="inline-flex items-center gap-1">
                                                    <MapPin
                                                        className="size-3.5"
                                                        aria-hidden
                                                    />
                                                    {c.city}
                                                </span>
                                            )}
                                            {c.niches.length > 0 && (
                                                <span>
                                                    {c.niches
                                                        .slice(0, 3)
                                                        .join(", ")}
                                                </span>
                                            )}
                                        </span>
                                        {(c.instagram || c.tiktok) && (
                                            <span className="mt-1 flex flex-wrap gap-x-4">
                                                {c.instagram && (
                                                    <ProfielLink
                                                        platform="instagram"
                                                        handle={c.instagram}
                                                        compact
                                                    />
                                                )}
                                                {c.tiktok && (
                                                    <ProfielLink
                                                        platform="tiktok"
                                                        handle={c.tiktok}
                                                        compact
                                                    />
                                                )}
                                            </span>
                                        )}
                                        {reden ? (
                                            <span className="mt-1.5 flex items-center gap-1.5 font-semibold">
                                                <Ban
                                                    className="size-4 shrink-0"
                                                    aria-hidden
                                                />
                                                {reden}
                                            </span>
                                        ) : (
                                            (dichtbij ||
                                                c.reasons.length > 0) && (
                                                <span className="mt-1.5 flex flex-wrap gap-1.5">
                                                    {[
                                                        ...new Set([
                                                            ...(dichtbij
                                                                ? [
                                                                      `Woont in ${stad}`,
                                                                  ]
                                                                : []),
                                                            ...c.reasons,
                                                        ]),
                                                    ].map((r) => (
                                                        <span
                                                            key={r}
                                                            className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800"
                                                        >
                                                            {r}
                                                        </span>
                                                    ))}
                                                </span>
                                            )
                                        )}
                                    </span>
                                </label>
                            </li>
                        );
                    })}
                </MeerLijst>
            </div>
            {term && lijst.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Geen creator gevonden voor “{zoek}”.
                </p>
            )}
        </div>
    );
}
