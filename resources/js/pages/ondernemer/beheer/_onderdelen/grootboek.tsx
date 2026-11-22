import { cn } from "@/lib/utils";
import type { Boeking } from "./types";

const SOORTEN: Record<string, string> = {
    toekennen: "Toegekend",
    reserveren: "Gereserveerd",
    verbruiken: "Verbruikt",
    vrijgeven: "Teruggegeven",
    correctie: "Correctie",
};

function dag(iso: string): string {
    return new Date(iso).toLocaleDateString("nl-NL", {
        timeZone: "Europe/Amsterdam",
        day: "numeric",
        month: "short",
        year: "numeric",
    });
}

/** Tegoedgrootboek: elke boeking met datum, soort, punten, reden en het saldo daarna. Nieuwste eerst. */
export function Grootboek({ ledger }: { ledger: Boeking[] }) {
    if (ledger.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">Nog geen boekingen.</p>
        );
    }

    return (
        <div className="@container">
            <div className="hidden grid-cols-[7rem_7.5rem_minmax(0,1fr)_5rem_5rem] gap-3 border-b pb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase @xl:grid">
                <span>Datum</span>
                <span>Soort</span>
                <span>Reden</span>
                <span className="text-right">Punten</span>
                <span className="text-right">Saldo</span>
            </div>
            <ul className="divide-y" data-test="grootboek">
                {ledger.map((r) => {
                    const punten = (
                        <span
                            className={cn(
                                "font-heading font-bold tabular-nums",
                                r.points > 0
                                    ? "text-emerald-700 dark:text-emerald-400"
                                    : r.points < 0
                                      ? "text-rood"
                                      : "",
                            )}
                        >
                            {r.points > 0 ? `+${r.points}` : r.points}
                        </span>
                    );
                    const reden = (
                        <>
                            {r.reason}
                            {r.collaboration && (
                                <span className="text-muted-foreground">
                                    {" "}
                                    · {r.collaboration}
                                </span>
                            )}
                        </>
                    );

                    return (
                        <li key={r.id} className="py-2.5 text-sm">
                            <div className="flex items-start justify-between gap-3 @xl:hidden">
                                <div className="min-w-0">
                                    <p className="font-semibold">
                                        {SOORTEN[r.kind] ?? r.kind}{" "}
                                        <span className="font-normal text-muted-foreground">
                                            · {dag(r.date)}
                                        </span>
                                    </p>
                                    <p className="break-words text-muted-foreground">
                                        {reden}
                                    </p>
                                </div>
                                <div className="shrink-0 text-right">
                                    <p className="text-base">{punten}</p>
                                    <p className="text-xs text-muted-foreground">
                                        saldo {r.balance}
                                    </p>
                                </div>
                            </div>
                            <div className="hidden grid-cols-[7rem_7.5rem_minmax(0,1fr)_5rem_5rem] gap-3 @xl:grid">
                                <span className="text-muted-foreground">
                                    {dag(r.date)}
                                </span>
                                <span className="font-semibold">
                                    {SOORTEN[r.kind] ?? r.kind}
                                </span>
                                <span className="break-words">{reden}</span>
                                <span className="text-right">{punten}</span>
                                <span className="text-right text-muted-foreground tabular-nums">
                                    {r.balance}
                                </span>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
