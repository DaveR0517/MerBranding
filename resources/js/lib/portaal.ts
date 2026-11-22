import type { LucideIcon } from 'lucide-react';
import { Bell } from 'lucide-react';
import { meldingen } from '@/routes';
import type { Portal } from '@/types/auth';

export type PortaalLink = {
    title: string;
    href: string;
    icon: LucideIcon;
    mobile?: boolean;
};

export type NavItem = {
    title: string;
    href: () => string;
    icon: LucideIcon;
    /** Ook in de onderbalk op de telefoon (maximaal 4 per omgeving plus Meldingen). */
    mobile?: boolean;
    /** Volgorde in het menu: lager staat hoger. */
    order: number;
    /** Alleen tonen met dit recht (Spatie-permissie) of deze rol. */
    permission?: string;
};

export type NavModule = Partial<Record<Exclude<Portal, 'geen'>, NavItem[]>>;

/** Alle modules in resources/js/nav/*.ts. Een nieuwe module voegt alleen een eigen bestand toe. */
const modules = import.meta.glob<{ default: NavModule }>('../nav/*.ts', { eager: true });

/** Navigatie per omgeving. Elke omgeving heeft eigen schermen in resources/js/pages/<omgeving>. */
export function navigatie(portal: Portal | null, permissions: string[] = []): PortaalLink[] {
    const items = portal && portal !== 'geen'
        ? Object.values(modules).flatMap((m) => m.default[portal] ?? [])
        : [];

    const zichtbaar = items
        .filter((i) => !i.permission || permissions.includes(i.permission))
        .sort((a, b) => a.order - b.order);
    let mobiel = 0;

    return [
        ...zichtbaar.map((i) => ({
            title: i.title,
            href: i.href(),
            icon: i.icon,
            mobile: i.mobile && ++mobiel <= 4,
        })),
        { title: 'Meldingen', href: meldingen.url(), icon: Bell, mobile: true },
    ];
}

/** Is deze link de huidige pagina (of een onderliggende pagina)? Startpagina's alleen bij exacte match. */
export function isActief(href: string, huidigeUrl: string): boolean {
    const doel = new URL(href, window.location.href).pathname.replace(/\/$/, '');
    const nu = new URL(huidigeUrl, window.location.href).pathname.replace(/\/$/, '');
    const startpagina = /\/(team|creator|ondernemer|academy)$/.test(doel);

    return startpagina ? nu === doel : nu === doel || nu.startsWith(`${doel}/`);
}
