import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Paginakop zoals in het prototype: een kort verloopstreepje boven een zware kop. */
export function Kop({ children, naast, onder }: { children: ReactNode; naast?: ReactNode; onder?: ReactNode }) {
    return (
        <header className="space-y-2">
            <span aria-hidden className="block h-1 w-9 rounded-full bg-brand-gradient" />
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                <h1 className="font-heading text-2xl leading-tight font-extrabold tracking-tight break-words uppercase md:text-4xl">
                    {children}
                </h1>
                {naast}
            </div>
            {onder && <div className="text-sm text-muted-foreground">{onder}</div>}
        </header>
    );
}

/** Wit vlak met rand, de bouwsteen van elk scherm. */
export function Blok({ className, ...props }: ComponentProps<'section'>) {
    return <section className={cn('rounded-xl border bg-card p-4 md:p-6', className)} {...props} />;
}

export function BlokKop({ children, rechts }: { children: ReactNode; rechts?: ReactNode }) {
    return (
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 className="font-heading text-lg font-bold">{children}</h2>
            {rechts}
        </div>
    );
}

/** Meerregelig tekstveld in de stijl van de Input van shadcn. */
export function Tekstvak({ className, ...props }: ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(
                'flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm',
                className,
            )}
            {...props}
        />
    );
}

/** Een rij gegevens: label boven, waarde eronder. */
export function Gegeven({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{label}</dt>
            <dd className="mt-0.5 break-words">{children}</dd>
        </div>
    );
}
