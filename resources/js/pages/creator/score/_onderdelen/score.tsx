/** Gedeelde bouwstenen voor CreatorScore en AdminKlassement. */

export type Deelscore = {
    key: string;
    name: string;
    value: number | null;
    weight: number;
    effective: number | null;
    uitleg: string;
};

export type Drempel = { value: string; name: string; score: number; jobs: number; quality: number | null };

/** Score met één decimaal, Nederlandse notatie. Gehele getallen zonder ",0". */
export function getal(x: number | null | undefined): string {
    if (x === null || x === undefined) {
        return 'n.v.t.';
    }

    return x.toLocaleString('nl-NL', { maximumFractionDigits: 1 });
}

/** Kleur van een deelscore zoals in het ontwerp: groen vanaf 85, rood onder 60. */
export function scoreKleur(x: number | null): string {
    if (x === null) {
        return 'text-muted-foreground';
    }

    return x >= 85 ? 'text-emerald-700 dark:text-emerald-400' : x < 60 ? 'text-rood' : '';
}

export function Balk({ waarde, label }: { waarde: number | null; label: string }) {
    return (
        <span
            role="progressbar"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={waarde ?? 0}
            className="block h-1.5 w-full rounded-full bg-muted"
        >
            <span
                className={`block h-1.5 rounded-full ${waarde === null ? 'bg-transparent' : 'bg-violet'}`}
                style={{ width: `${Math.max(0, Math.min(100, waarde ?? 0))}%` }}
            />
        </span>
    );
}

/** Verloop van de totaalscore: één lijn, een punt per week, met tooltip per punt en een leesbare lijst ernaast. */
export function Verloop({ punten }: { punten: { date: string; total: number }[] }) {
    if (punten.length < 2) {
        return <p className="text-sm text-muted-foreground">Het verloop verschijnt zodra er een paar weken metingen zijn.</p>;
    }

    const w = 320;
    const h = 96;
    const pad = 8;
    const waarden = punten.map((p) => p.total);
    const min = Math.max(0, Math.floor((Math.min(...waarden) - 5) / 10) * 10);
    const max = Math.min(100, Math.ceil((Math.max(...waarden) + 5) / 10) * 10);
    const x = (i: number) => pad + (i / (punten.length - 1)) * (w - 2 * pad);
    const y = (v: number) => h - pad - ((v - min) / Math.max(1, max - min)) * (h - 2 * pad);
    const kort = (d: string) => new Date(d).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short', timeZone: 'Europe/Amsterdam' });

    return (
        <figure className="space-y-1" aria-label={`Verloop van ${getal(waarden[0])} naar ${getal(waarden.at(-1))}`}>
            <div className="relative h-24 w-full">
                <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" className="absolute inset-0 size-full" aria-hidden>
                    <line x1={0} x2={w} y1={y(min)} y2={y(min)} className="stroke-border" strokeWidth={1} vectorEffect="non-scaling-stroke" />
                    <polyline
                        points={punten.map((p, i) => `${x(i)},${y(p.total)}`).join(' ')}
                        fill="none"
                        className="stroke-violet"
                        strokeWidth={2}
                        strokeLinejoin="round"
                        strokeLinecap="round"
                        vectorEffect="non-scaling-stroke"
                    />
                </svg>
                {punten.map((p, i) => (
                    <span
                        key={p.date}
                        title={`${kort(p.date)}: ${getal(p.total)}`}
                        className="group absolute grid size-5 -translate-x-1/2 -translate-y-1/2 place-items-center"
                        style={{ left: `${(x(i) / w) * 100}%`, top: `${(y(p.total) / h) * 100}%` }}
                    >
                        <span className={`block rounded-full border-2 border-card bg-violet transition-transform group-hover:scale-150 ${i === punten.length - 1 ? 'size-3' : 'size-2'}`} />
                    </span>
                ))}
            </div>
            <figcaption className="flex justify-between text-xs text-muted-foreground">
                <span>{kort(punten[0].date)}</span>
                <span>
                    {min} tot {max}
                </span>
                <span>{kort(punten.at(-1)!.date)}</span>
            </figcaption>
            <ol className="sr-only">
                {punten.map((p) => (
                    <li key={p.date}>
                        {kort(p.date)}: {getal(p.total)}
                    </li>
                ))}
            </ol>
        </figure>
    );
}
