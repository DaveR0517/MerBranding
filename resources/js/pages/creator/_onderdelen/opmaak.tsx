import type { ReactNode } from 'react';

/** Bouwstenen voor de creatorschermen: telefoon eerst, grote knoppen, witte kaarten op een donkere kop. */

export const groteKnop =
    'inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-violet px-5 py-3 text-base font-bold text-white shadow-sm transition hover:bg-violet/90 focus-visible:ring-4 focus-visible:ring-violet/30 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

export const rustigeKnop =
    'inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border-2 border-border bg-card px-5 py-3 text-base font-semibold transition hover:border-violet focus-visible:ring-4 focus-visible:ring-violet/30 focus-visible:outline-none disabled:opacity-50';

export const veld =
    'w-full rounded-xl border-2 border-input bg-background px-3 py-3 text-base outline-none focus:border-violet focus:ring-4 focus:ring-violet/20';

/** Donkere kop bovenaan elk creatorscherm. */
export function Kop({
    boven,
    titel,
    onder,
    children,
}: {
    boven?: ReactNode;
    titel: ReactNode;
    onder?: ReactNode;
    children?: ReactNode;
}) {
    return (
        <header className="relative overflow-hidden bg-aubergine-dark text-white">
            <div
                aria-hidden
                className="absolute inset-0 bg-[radial-gradient(120%_90%_at_100%_0%,rgba(97,78,217,0.55),transparent_60%)]"
            />
            <div className="relative mx-auto w-full max-w-3xl px-4 pt-5 pb-6 md:px-6">
                {boven && (
                    <p className="text-xs font-bold tracking-wider text-white/70 uppercase">
                        {boven}
                    </p>
                )}
                <span
                    aria-hidden
                    className="mt-3 mb-2 block h-1 w-10 rounded-full bg-brand-gradient"
                />
                <h1 className="font-heading text-2xl leading-tight font-extrabold break-words md:text-3xl">
                    {titel}
                </h1>
                {onder && (
                    <div className="mt-1 text-sm text-white/80">{onder}</div>
                )}
                {children && <div className="mt-3">{children}</div>}
            </div>
        </header>
    );
}

export function Kaart({
    titel,
    children,
    accent = false,
    className = '',
}: {
    titel?: ReactNode;
    children: ReactNode;
    accent?: boolean;
    className?: string;
}) {
    return (
        <section
            className={`rounded-2xl border bg-card p-4 shadow-xs md:p-5 ${accent ? 'border-l-4 border-l-violet' : ''} ${className}`}
        >
            {titel && (
                <h2 className="mb-2 font-heading text-lg font-extrabold">
                    {titel}
                </h2>
            )}
            {children}
        </section>
    );
}

/** Rode of groene uitkomst, voor fouten en controles. */
export function Uitkomst({
    goed,
    children,
}: {
    goed: boolean;
    children: ReactNode;
}) {
    return (
        <div
            role={goed ? 'status' : 'alert'}
            className={`rounded-xl border-2 px-3 py-2.5 text-sm font-medium ${goed ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-rood/40 bg-rood/5 text-rood'}`}
        >
            {children}
        </div>
    );
}

export function Fout({ tekst }: { tekst?: string | null }) {
    return tekst ? <Uitkomst goed={false}>{tekst}</Uitkomst> : null;
}

/** Wie nu aan zet is, zoals in het prototype. */
export function AanZet({ children }: { children: ReactNode }) {
    return (
        <p className="mb-2 text-xs font-bold tracking-wider text-violet uppercase">
            {children}
        </p>
    );
}
