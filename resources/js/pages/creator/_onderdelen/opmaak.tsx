import type { ReactNode } from 'react';
import { Kaart, Sectie } from '@/components/pagina';
import { cn } from '@/lib/utils';

/**
 * Formulieronderdelen voor de creator- en academyschermen: telefoon eerst, grote knoppen en velden.
 * Paginakop, kaarten en lijsten komen uit de bouwdoos (@/components/pagina).
 */

export const groteKnop =
    'inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-violet px-5 py-3 text-base font-bold text-white shadow-sm transition hover:bg-violet/90 focus-visible:ring-4 focus-visible:ring-violet/30 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

export const rustigeKnop =
    'inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border-2 border-border bg-card px-5 py-3 text-base font-semibold transition hover:border-violet focus-visible:ring-4 focus-visible:ring-violet/30 focus-visible:outline-none disabled:opacity-50';

export const veld =
    'w-full rounded-xl border-2 border-input bg-background px-3 py-3 text-base outline-none focus:border-violet focus:ring-4 focus:ring-violet/20';

/** Kaart met een kop, uit de bouwdoos samengesteld. `aanZet` markeert het blok waar de gebruiker nu iets moet doen. */
export function TitelKaart({
    titel,
    aanZet = false,
    className,
    children,
}: {
    titel?: ReactNode;
    aanZet?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Kaart className={cn(aanZet && 'border-violet', className)}>
            <Sectie titel={titel}>{children}</Sectie>
        </Kaart>
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
    return <p className="mb-2 text-sm font-semibold text-violet">{children}</p>;
}
