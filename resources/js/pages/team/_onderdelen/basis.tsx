import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Bouwstenen voor de teamschermen. Eén plek voor kop, paneel, velden en labels. */

export function Kop({
    titel,
    sub,
    acties,
}: {
    titel: string;
    sub?: ReactNode;
    acties?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-3">
            <div className="min-w-0">
                <h1 className="font-heading text-2xl font-extrabold text-aubergine md:text-3xl dark:text-white">
                    {titel}
                </h1>
                {sub && <p className="mt-1 text-sm text-muted-foreground">{sub}</p>}
            </div>
            {acties && <div className="flex flex-wrap gap-2">{acties}</div>}
        </div>
    );
}

export function Pagina({ children, breed = false }: { children: ReactNode; breed?: boolean }) {
    return (
        <div className={cn('mx-auto w-full space-y-5 p-4 md:p-6', breed ? 'max-w-7xl' : 'max-w-5xl')}>
            {children}
        </div>
    );
}

export function Paneel({
    titel,
    sub,
    children,
    className,
    acties,
    id,
}: {
    titel?: ReactNode;
    sub?: ReactNode;
    children: ReactNode;
    className?: string;
    acties?: ReactNode;
    id?: string;
}) {
    return (
        <section id={id} className={cn('rounded-2xl border bg-card p-4 md:p-5', className)}>
            {(titel || acties) && (
                <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        {titel && <h2 className="font-heading text-base font-bold">{titel}</h2>}
                        {sub && <p className="mt-0.5 text-sm text-muted-foreground">{sub}</p>}
                    </div>
                    {acties}
                </div>
            )}
            {children}
        </section>
    );
}

export function Leeg({ children }: { children: ReactNode }) {
    return (
        <p className="rounded-2xl border border-dashed bg-card p-6 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

export function Label({
    children,
    kleur = 'violet',
}: {
    children: ReactNode;
    kleur?: 'violet' | 'rood' | 'groen' | 'grijs' | 'amber';
}) {
    const kleuren = {
        violet: 'bg-violet/10 text-violet',
        rood: 'bg-rood/10 text-rood',
        groen: 'bg-emerald-100 text-emerald-800',
        grijs: 'bg-muted text-muted-foreground',
        amber: 'bg-amber-100 text-amber-800',
    };

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ${kleuren[kleur]}`}
        >
            {children}
        </span>
    );
}

/** Formulierveld met label en foutmelding. */
export function Veld({
    label,
    fout,
    hint,
    children,
    className,
    htmlFor,
}: {
    label: string;
    fout?: string;
    hint?: ReactNode;
    children: ReactNode;
    className?: string;
    htmlFor: string;
}) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <label htmlFor={htmlFor} className="text-sm font-semibold">
                {label}
            </label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {fout && <p className="text-sm text-rood">{fout}</p>}
        </div>
    );
}

export const invoer =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-violet focus-visible:ring-2 focus-visible:ring-violet/30';

/** Foutmeldingen van de server die bij geen veld horen (bijvoorbeeld uit de keten). */
export function Fouten({ negeer = [] }: { negeer?: string[] }) {
    const { errors } = usePage().props as unknown as { errors: Record<string, string> };
    const lijst = Object.entries(errors ?? {}).filter(([k]) => !negeer.includes(k));

    if (lijst.length === 0) {
        return null;
    }

    return (
        <div role="alert" className="rounded-xl border border-rood/30 bg-rood/5 p-3 text-sm text-rood">
            {lijst.map(([k, v]) => (
                <p key={k}>{v}</p>
            ))}
        </div>
    );
}

/** Leesbare naam van een logboekgebeurtenis. */
export function gebeurtenis(event: string): string {
    const namen: Record<string, string> = {
        status: 'Status gewijzigd',
        aangevraagd: 'Aangevraagd',
        uitgenodigd: 'Creator uitgenodigd',
        uitnodiging_geweigerd: 'Uitnodiging geweigerd',
        uitnodiging_verlopen: 'Uitnodiging verlopen',
        opmerking_ondernemer: 'Opmerking van de ondernemer',
        vergoeding_opeisbaar: 'Vergoeding opeisbaar',
        beoordeeld: 'Beoordeeld door de ondernemer',
    };

    return namen[event] ?? event.replaceAll('_', ' ');
}

/** Datum van morgen als JJJJ-MM-DD in Nederlandse tijd. */
export function morgen(): string {
    const d = new Date(Date.now() + 24 * 3600 * 1000);

    return d.toLocaleDateString('sv-SE', { timeZone: 'Europe/Amsterdam' });
}

export function vandaag(): string {
    return new Date().toLocaleDateString('sv-SE', { timeZone: 'Europe/Amsterdam' });
}

export function initialen(naam: string): string {
    return naam
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join('');
}
