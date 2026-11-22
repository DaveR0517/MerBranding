/**
 * Bouwdoos voor elk portaalscherm (zie docs/ontwerpregels-portaal.md).
 * Eén breedte, één kop, één ritme van ruimte. Gebruik deze onderdelen in plaats van eigen opmaak,
 * zodat alle omgevingen (team, ondernemer, creator, academy) strak en gelijk uitgelijnd zijn.
 */
import { ChevronDown } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Children, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Paginacontainer. Standaard de vaste brede maat; `smal` voor formulieren en lezen (max 768 px). */
export function Pagina({ children, smal = false, className }: { children: ReactNode; smal?: boolean; className?: string }) {
    return (
        <div className={cn('mx-auto w-full space-y-5 px-4 py-5 md:space-y-6 md:px-6 md:py-6', smal ? 'max-w-3xl' : 'max-w-7xl', className)}>
            {children}
        </div>
    );
}

/** Paginakop: titel, korte omschrijving, acties rechts (op de telefoon eronder, volle breedte). */
export function PaginaKop({
    titel,
    omschrijving,
    boven,
    acties,
    extra,
}: {
    titel: ReactNode;
    omschrijving?: ReactNode;
    /** Kleine regel boven de titel, bijvoorbeeld een nummer of de naam van het bedrijf. */
    boven?: ReactNode;
    acties?: ReactNode;
    /** Ruimte onder de kop, bijvoorbeeld een statuslabel of filters. */
    extra?: ReactNode;
}) {
    return (
        <header className="space-y-3">
            <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div className="min-w-0 space-y-1">
                    {boven && <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{boven}</p>}
                    <h1 className="font-heading text-2xl leading-tight font-extrabold text-balance md:text-3xl">{titel}</h1>
                    {omschrijving && <p className="max-w-prose text-sm text-muted-foreground md:text-base">{omschrijving}</p>}
                </div>
                {acties && <div className="flex shrink-0 flex-wrap gap-2 *:max-md:flex-1">{acties}</div>}
            </div>
            {extra}
        </header>
    );
}

/** Sectie met kop (en optionele actie rechts). */
export function Sectie({
    titel,
    omschrijving,
    actie,
    children,
    className,
}: {
    titel?: ReactNode;
    omschrijving?: ReactNode;
    actie?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={cn('space-y-3', className)}>
            {(titel || actie) && (
                <div className="flex items-end justify-between gap-3">
                    <div className="min-w-0">
                        {titel && <h2 className="font-heading text-lg font-bold">{titel}</h2>}
                        {omschrijving && <p className="text-sm text-muted-foreground">{omschrijving}</p>}
                    </div>
                    {actie && <div className="shrink-0">{actie}</div>}
                </div>
            )}
            {children}
        </section>
    );
}

/** Kaart: één vlak met vaste rand, hoek en binnenruimte. `nadruk` geeft de donkere merkkaart. */
export function Kaart({
    children,
    className,
    nadruk = false,
    compact = false,
}: {
    children: ReactNode;
    className?: string;
    nadruk?: boolean;
    compact?: boolean;
}) {
    return (
        <div
            className={cn(
                'rounded-xl border',
                compact ? 'p-3 md:p-4' : 'p-4 md:p-5',
                nadruk ? 'border-transparent bg-aubergine text-white' : 'bg-card',
                className,
            )}
        >
            {children}
        </div>
    );
}

/** Raster van kaarten: 1 kolom op de telefoon, 2 op tablet, `kolommen` (2, 3 of 4) op een groot scherm. */
export function KaartRaster({ children, kolommen = 3, className }: { children: ReactNode; kolommen?: 2 | 3 | 4; className?: string }) {
    const lg = { 2: 'lg:grid-cols-2', 3: 'lg:grid-cols-3', 4: 'lg:grid-cols-4' }[kolommen];

    return <div className={cn('grid grid-cols-1 gap-3 *:min-w-0 sm:grid-cols-2 md:gap-4', lg, className)}>{children}</div>;
}

/** Kerncijfer: label, groot getal, toelichting. Past in een KaartRaster. */
export function Kerncijfer({
    label,
    waarde,
    toelichting,
    icoon: Icoon,
    nadruk = false,
}: {
    label: ReactNode;
    waarde: ReactNode;
    toelichting?: ReactNode;
    icoon?: LucideIcon;
    nadruk?: boolean;
}) {
    return (
        <Kaart nadruk={nadruk} compact>
            <p className={cn('flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase', nadruk ? 'text-white/75' : 'text-muted-foreground')}>
                {Icoon && <Icoon className="size-3.5" aria-hidden />}
                {label}
            </p>
            <p className="mt-1 font-heading text-2xl font-extrabold tabular-nums">{waarde}</p>
            {toelichting && <p className={cn('mt-0.5 text-xs', nadruk ? 'text-white/75' : 'text-muted-foreground')}>{toelichting}</p>}
        </Kaart>
    );
}

/** Lege staat: rustig, met uitleg en eventueel een actie. */
export function Leeg({ titel, children, actie }: { titel: ReactNode; children?: ReactNode; actie?: ReactNode }) {
    return (
        <div className="rounded-xl border border-dashed bg-card/50 p-6 text-center">
            <p className="font-semibold">{titel}</p>
            {children && <p className="mx-auto mt-1 max-w-prose text-sm text-muted-foreground">{children}</p>}
            {actie && <div className="mt-3">{actie}</div>}
        </div>
    );
}

/**
 * Lijst die begint met `eerst` items en de rest achter "Toon meer" zet. Voorkomt eindeloos scrollen.
 * Geef de items als kinderen; `als` bepaalt of het een <ul> of een tabel-body is.
 */
export function MeerLijst({ children, eerst = 8, className, label = 'Toon meer' }: { children: ReactNode; eerst?: number; className?: string; label?: string }) {
    const items = Children.toArray(children);
    const [alles, setAlles] = useState(false);
    const zichtbaar = alles ? items : items.slice(0, eerst);

    return (
        <div className="space-y-2">
            <ul className={cn('space-y-2', className)}>{zichtbaar}</ul>
            {items.length > eerst && (
                <button
                    type="button"
                    onClick={() => setAlles(!alles)}
                    className="w-full rounded-lg border bg-card py-2 text-sm font-semibold text-violet hover:border-violet"
                >
                    {alles ? 'Toon minder' : `${label} (${items.length - eerst})`}
                </button>
            )}
        </div>
    );
}

/** Rij in een lijst: vaste hoogte en uitlijning, links de hoofdzaak, rechts status of actie. */
export function Rij({
    titel,
    onder,
    rechts,
    links,
    className,
}: {
    titel: ReactNode;
    onder?: ReactNode;
    rechts?: ReactNode;
    links?: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex items-center gap-3 rounded-xl border bg-card px-3 py-2.5 md:px-4', className)}>
            {links && <div className="shrink-0">{links}</div>}
            <div className="min-w-0 flex-1">
                <p className="truncate font-semibold">{titel}</p>
                {onder && <p className="truncate text-sm text-muted-foreground">{onder}</p>}
            </div>
            {rechts && <div className="flex shrink-0 items-center gap-2">{rechts}</div>}
        </div>
    );
}

/** Uitklapblok voor details die niet altijd nodig zijn (houdt lange pagina's kort). */
export function Uitklap({ titel, children, open = false, rechts }: { titel: ReactNode; children: ReactNode; open?: boolean; rechts?: ReactNode }) {
    const [isOpen, setOpen] = useState(open);

    return (
        <div className="rounded-xl border bg-card">
            <button type="button" onClick={() => setOpen(!isOpen)} aria-expanded={isOpen} className="flex w-full items-center gap-3 px-4 py-3 text-left">
                <span className="min-w-0 flex-1 font-semibold">{titel}</span>
                {rechts}
                <ChevronDown className={cn('size-4 shrink-0 transition', isOpen && 'rotate-180')} aria-hidden />
            </button>
            {isOpen && <div className="border-t px-4 py-3">{children}</div>}
        </div>
    );
}

/** Tabbladen om een lange pagina op te delen. Op de telefoon horizontaal scrollbaar. */
export function Tabs<T extends string>({
    tabs,
    actief,
    onKies,
}: {
    tabs: { waarde: T; label: ReactNode; aantal?: number }[];
    actief: T;
    onKies: (t: T) => void;
}) {
    return (
        <div role="tablist" className="-mx-4 flex gap-1 overflow-x-auto border-b px-4 md:mx-0 md:px-0">
            {tabs.map((t) => (
                <button
                    key={t.waarde}
                    role="tab"
                    type="button"
                    aria-selected={t.waarde === actief}
                    onClick={() => onKies(t.waarde)}
                    className={cn(
                        '-mb-px shrink-0 border-b-2 px-3 py-2 text-sm font-semibold whitespace-nowrap',
                        t.waarde === actief ? 'border-violet text-violet' : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {t.label}
                    {t.aantal !== undefined && <span className="ml-1.5 rounded-full bg-muted px-1.5 text-xs tabular-nums">{t.aantal}</span>}
                </button>
            ))}
        </div>
    );
}

/** Twee kolommen op een groot scherm: hoofdinhoud links (2/3), zijkolom rechts (1/3). Op de telefoon onder elkaar. */
export function TweeKolommen({ hoofd, zij }: { hoofd: ReactNode; zij: ReactNode }) {
    return (
        <div className="grid gap-5 lg:grid-cols-3 lg:gap-6">
            <div className="min-w-0 space-y-5 lg:col-span-2">{hoofd}</div>
            <aside className="min-w-0 space-y-5">{zij}</aside>
        </div>
    );
}
