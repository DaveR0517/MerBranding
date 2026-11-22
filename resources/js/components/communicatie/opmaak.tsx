import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';
import type { VraagKort } from './types';

/** Kleine bouwstenen die berichten, help en support delen. */

export const tekstvak =
    'w-full rounded-xl border border-input bg-background px-3 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-violet focus-visible:ring-2 focus-visible:ring-violet/30 md:text-sm';

export function Initialen({ naam, className }: { naam: string; className?: string }) {
    const letters = naam
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join('');

    return (
        <span aria-hidden className={cn('grid size-9 shrink-0 place-items-center rounded-full bg-violet/10 text-xs font-bold text-violet', className)}>
            {letters}
        </span>
    );
}

export function Teller({ className, ...props }: ComponentProps<'span'>) {
    return <span className={cn('inline-grid min-w-5 place-items-center rounded-full bg-rood px-1.5 text-[11px] leading-5 font-bold text-white', className)} {...props} />;
}

const vraagKleur: Record<VraagKort['status'], string> = {
    open: 'bg-amber-100 text-amber-800',
    beantwoord: 'bg-violet/10 text-violet',
    gesloten: 'bg-emerald-100 text-emerald-800',
};

export function VraagStatus({ vraag }: { vraag: Pick<VraagKort, 'status' | 'status_label'> }) {
    return (
        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ${vraagKleur[vraag.status]}`}>{vraag.status_label}</span>
    );
}

/** Uitgewerkte markdown van een helpartikel (op de server omgezet, html ontsnapt). */
export function Artikeltekst({ html }: { html: string }) {
    return (
        <div
            className="space-y-3 text-[15px] leading-relaxed [&_a]:text-violet [&_a]:underline [&_li]:mt-1 [&_ol]:list-decimal [&_ol]:pl-5 [&_strong]:font-semibold [&_ul]:list-disc [&_ul]:pl-5"
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
