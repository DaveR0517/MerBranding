import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Kleine labels in de huisstijl, één plek voor alle schermen van de module. */
export type Kleur = 'violet' | 'rood' | 'groen' | 'grijs' | 'amber';

const kleuren: Record<Kleur, string> = {
    violet: 'bg-violet/10 text-violet',
    rood: 'bg-rood/10 text-rood',
    groen: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    grijs: 'bg-muted text-muted-foreground',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
};

export function Etiket({
    kleur = 'violet',
    children,
    className,
}: {
    kleur?: Kleur;
    children: ReactNode;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
                kleuren[kleur],
                className,
            )}
        >
            {children}
        </span>
    );
}

export const koppelingStatus: Record<string, { naam: string; kleur: Kleur }> = {
    niet_gekoppeld: { naam: 'Niet gekoppeld', kleur: 'grijs' },
    handmatig: { naam: 'Handmatig', kleur: 'amber' },
    gekoppeld: { naam: 'Gekoppeld', kleur: 'groen' },
    verlopen: { naam: 'Verlopen', kleur: 'rood' },
    fout: { naam: 'Opnieuw koppelen nodig', kleur: 'rood' },
};

export const waarschuwingen: Record<string, { naam: string; kleur: Kleur }> = {
    verloopt: { naam: 'Verloopt binnenkort', kleur: 'amber' },
    verlopen: { naam: 'Token verlopen', kleur: 'rood' },
    fout: { naam: 'Fout', kleur: 'rood' },
};

/** VOORSTEL-markering voor waarden die nog niet besloten zijn. */
export function Voorstel({ children = 'VOORSTEL' }: { children?: ReactNode }) {
    return <Etiket kleur="amber">{children}</Etiket>;
}
