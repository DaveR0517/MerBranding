import type { ReactNode } from 'react';
import { Kaart, KaartRaster } from '@/components/pagina';
import { datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import type {
    Bron,
    Metriek,
    Metrieken,
    PlatformTotaal,
    PublicatieCijfers,
} from '@/types/cijfers';

/** Gedeelde bouwstenen voor cijfers: bron, schatting, ophaaldatum en balken. Eén plek voor team, ondernemer en creator. */

export function getal(n: number | null | undefined): string {
    return n === null || n === undefined ? '' : n.toLocaleString('nl-NL');
}

export function platformNaam(p: string): string {
    return (
        { instagram: 'Instagram', tiktok: 'TikTok' }[p] ??
        p.charAt(0).toUpperCase() + p.slice(1)
    );
}

export function bronNaam(b: Bron | string): string {
    return (
        { handmatig: 'Handmatig', instagram: 'Instagram', tiktok: 'TikTok' }[
            b
        ] ?? b
    );
}

/** Klein label: bron, met "schatting" of "niet gecontroleerd" waar dat zo is. */
export function BronLabel({
    bronnen,
    schatting,
    ongecontroleerd,
}: {
    bronnen: Bron[];
    schatting?: boolean;
    ongecontroleerd?: boolean;
}) {
    return (
        <span className="inline-flex flex-wrap gap-1">
            {bronnen.map((b) => (
                <span
                    key={b}
                    className="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground"
                >
                    {bronNaam(b)}
                </span>
            ))}
            {schatting && (
                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                    Schatting
                </span>
            )}
            {ongecontroleerd && (
                <span className="rounded-full bg-violet/10 px-2 py-0.5 text-xs font-semibold text-violet">
                    Nog niet gecontroleerd
                </span>
            )}
        </span>
    );
}

/** Cijfers van één publicatie als compacte lijst. */
export function MetingLijst({
    p,
    metrics,
}: {
    p: PublicatieCijfers;
    metrics: Metrieken;
}) {
    const aanwezig = (Object.keys(metrics) as Metriek[]).filter(
        (m) => p.metrics[m],
    );

    if (aanwezig.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nog geen cijfers. De eerste cijfers komen binnen 48 uur na
                plaatsing.
            </p>
        );
    }

    return (
        <dl className="grid grid-cols-3 gap-x-3 gap-y-2 sm:grid-cols-6">
            {aanwezig.map((m) => (
                <div key={m} className="min-w-0">
                    <dt className="truncate text-xs text-muted-foreground">
                        {metrics[m]}
                    </dt>
                    <dd className="font-heading text-lg font-bold tabular-nums">
                        {p.metrics[m]?.is_estimate && (
                            <span className="text-sm">± </span>
                        )}
                        {getal(p.metrics[m]?.value)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export function Herkomst({ p }: { p: PublicatieCijfers }) {
    return (
        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <BronLabel
                bronnen={p.sources}
                schatting={p.is_estimate}
                ongecontroleerd={p.unreviewed}
            />
            {p.fetched_at && <span>Opgehaald {datumTijd(p.fetched_at)}</span>}
        </div>
    );
}

/** Totalen per platform als kerncijfers. Over platforms en bronnen heen wordt niet opgeteld. */
export function PlatformTegels({
    platforms,
    metrics,
}: {
    platforms: PlatformTotaal[];
    metrics: Metrieken;
}) {
    if (platforms.length === 0) {
        return null;
    }

    return (
        <KaartRaster kolommen={2}>
            {platforms.map((p) => (
                <Kaart key={p.platform}>
                    <section aria-label={platformNaam(p.platform)}>
                        <div className="mb-2 flex items-center justify-between gap-2">
                            <h3 className="font-heading font-bold">
                                {platformNaam(p.platform)}
                            </h3>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {p.posts} {p.posts === 1 ? 'post' : 'posts'}
                            </span>
                        </div>
                        <dl className="grid grid-cols-3 gap-x-3 gap-y-2">
                            {(Object.keys(metrics) as Metriek[]).map((m) => (
                                <div key={m} className="min-w-0">
                                    <dt className="truncate text-xs text-muted-foreground">
                                        {metrics[m]}
                                    </dt>
                                    <dd className="font-heading text-lg font-extrabold tabular-nums md:text-xl">
                                        {p.totals[m] === null ? (
                                            <span className="text-sm font-normal text-muted-foreground">
                                                geen cijfer
                                            </span>
                                        ) : (
                                            getal(p.totals[m])
                                        )}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <BronLabel
                                bronnen={p.sources}
                                schatting={p.is_estimate}
                            />
                            {p.fetched_at && (
                                <span>
                                    Laatst opgehaald {datumTijd(p.fetched_at)}
                                </span>
                            )}
                        </div>
                    </section>
                </Kaart>
            ))}
        </KaartRaster>
    );
}

/** Eenvoudige staafgrafiek in CSS (geen grafiekpakket nodig). Waarde en label staan als tekst bij elke staaf. */
export function Staven({
    data,
    kleur = 'bg-violet',
    hoogte = 'h-32',
}: {
    data: { label: string; waarde: number }[];
    kleur?: string;
    hoogte?: string;
}) {
    const max = Math.max(1, ...data.map((d) => d.waarde));

    return (
        <ul
            className={cn('grid items-end gap-1.5', hoogte)}
            style={{
                gridTemplateColumns: `repeat(${data.length}, minmax(0, 1fr))`,
            }}
        >
            {data.map((d) => (
                <li
                    key={d.label}
                    className="flex h-full min-w-0 flex-col items-center justify-end gap-1"
                >
                    <span className="text-xs font-semibold tabular-nums">
                        {d.waarde}
                    </span>
                    <span
                        className={cn(
                            'w-full max-w-10 rounded-t-md',
                            d.waarde === 0 ? 'bg-muted' : kleur,
                        )}
                        style={{
                            height: `${Math.max(4, (d.waarde / max) * 100)}%`,
                        }}
                        aria-hidden
                    />
                    <span className="w-full truncate text-center text-[11px] text-muted-foreground">
                        {d.label}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/** Liggende balk met label en waarde, voor verdelingen. */
export function Balk({
    label,
    waarde,
    max,
    rechts,
    kleur = 'bg-violet',
}: {
    label: ReactNode;
    waarde: number;
    max: number;
    rechts?: ReactNode;
    kleur?: string;
}) {
    return (
        <div className="space-y-1">
            <div className="flex items-baseline justify-between gap-2 text-sm">
                <span className="min-w-0 truncate">{label}</span>
                <span className="font-semibold tabular-nums">
                    {rechts ?? waarde}
                </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    className={cn('h-full rounded-full', kleur)}
                    style={{
                        width: `${max > 0 ? Math.max(2, (waarde / max) * 100) : 0}%`,
                    }}
                />
            </div>
        </div>
    );
}

/** Bedrag in euro met centen, voor facturen en uitbetalingen. */
export function bedrag(centen: number): string {
    return (centen / 100).toLocaleString('nl-NL', {
        style: 'currency',
        currency: 'EUR',
    });
}
