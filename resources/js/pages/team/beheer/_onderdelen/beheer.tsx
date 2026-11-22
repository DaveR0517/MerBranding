import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

/** Bouwstenen voor de beheerschermen (team, instellingen, pakketten, berichtsjablonen). */

/** Toelichting bij een Kerncijfer die rood wordt als er iets aandacht vraagt. */
export function Aandacht({ aan, children }: { aan: boolean; children: ReactNode }) {
    return <span className={cn(aan && "font-semibold text-rood")}>{children}</span>;
}

/** Label VOORSTEL: deze waarde is nog niet besloten (CLAUDE.md 13). */
export function Voorstel({ children = "Voorstel" }: { children?: ReactNode }) {
    return (
        <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-amber-800">
            {children}
        </span>
    );
}

/** Aankruislijst (meerdere keuzes). Opties in "uit" zijn zichtbaar maar niet te kiezen. */
export function Keuzes({
    naam,
    opties,
    gekozen,
    onChange,
    uit = [],
    uitleg = {},
}: {
    naam: string;
    opties: string[];
    gekozen: string[];
    onChange: (gekozen: string[]) => void;
    uit?: string[];
    uitleg?: Record<string, string>;
}) {
    return (
        <div className="grid gap-2 sm:grid-cols-2">
            {opties.map((o) => {
                const id = `${naam}-${o.replace(/\s+/g, "-").toLowerCase()}`;
                const dicht = uit.includes(o);

                return (
                    <label
                        key={o}
                        htmlFor={id}
                        className={cn(
                            "flex min-h-11 items-start gap-2 rounded-xl border p-2.5 text-sm",
                            gekozen.includes(o) && "border-violet bg-violet/5",
                            dicht
                                ? "cursor-not-allowed opacity-60"
                                : "cursor-pointer hover:border-violet",
                        )}
                    >
                        <input
                            id={id}
                            type="checkbox"
                            className="mt-0.5 size-4 accent-violet"
                            checked={gekozen.includes(o)}
                            disabled={dicht}
                            onChange={(e) =>
                                onChange(
                                    e.target.checked
                                        ? [...gekozen, o]
                                        : gekozen.filter((g) => g !== o),
                                )
                            }
                        />
                        <span>
                            <span className="font-semibold">{o}</span>
                            {uitleg[o] && (
                                <span className="block text-xs text-muted-foreground">
                                    {uitleg[o]}
                                </span>
                            )}
                        </span>
                    </label>
                );
            })}
        </div>
    );
}

/** Bedrag in centen naar invoer in euro ("125" of "125,50"). */
export function naarEuro(centen: number): string {
    return centen % 100 === 0
        ? String(centen / 100)
        : (centen / 100).toFixed(2).replace(".", ",");
}
