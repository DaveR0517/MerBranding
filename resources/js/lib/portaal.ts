import type { LucideIcon } from 'lucide-react';
import { Bell } from 'lucide-react';
import { meldingen } from '@/routes';
import type { Portal } from '@/types/auth';

export type PortaalLink = {
    title: string;
    href: string;
    icon: LucideIcon;
    mobile?: boolean;
    sectie: string;
};

/**
 * Menu-indeling per omgeving: welke link in welke sectie staat. Modules leveren de links (resources/js/nav),
 * dit is de ene plek die de structuur bepaalt. Een link die hier niet staat, komt onder "Overig".
 */
const SECTIES: Partial<Record<Exclude<Portal, 'geen'>, Record<string, string[]>>> = {
    ondernemer: {
        Samenwerkingen: ['Overzicht', 'Aanvragen', 'Berichten', 'Resultaten'],
        Content: ['Contentkalender', 'Mediabibliotheek', 'Video-ideeën', 'Gebruiksrechten', 'Maandrapport'],
        Bedrijf: ['Vestigingen', 'Gebruikers', 'Abonnement', 'Facturen', 'Koppelingen', 'Zichtbaarheid'],
        'Hulp en account': ['Aan de slag', 'Uitnodigen', 'Help', 'Account'],
    },
    team: {
        Dagelijks: ['Dashboard', 'Werkvoorraad', 'Samenwerkingen', 'Berichten', 'Planning'],
        Relaties: ['Bedrijven', 'Content per bedrijf', 'Creators', 'Screening', 'Klassement', 'Cursusdagen', 'Sales'],
        'Cijfers en geld': ['Cijfers', 'Financieel'],
        Beheer: ['Support', 'API-status', 'Radar', 'Logboek', 'Team en rechten', 'Instellingen', 'Pakketten en prijzen', 'Berichtsjablonen', 'Help'],
    },
    creator: {
        Werk: ['Mijn klussen', 'Agenda', 'Berichten', 'Cijfers'],
        'Over mij': ['Score', 'Vergoedingen', 'Profiel', 'Account en uitbetaling', 'Koppelingen', 'Mijn academy'],
        Hulp: ['Vriend uitnodigen', 'Help'],
    },
};

function sectieVan(portal: Portal | null, titel: string): string {
    const indeling = portal && portal !== 'geen' ? SECTIES[portal] : undefined;

    return Object.entries(indeling ?? {}).find(([, titels]) => titels.includes(titel))?.[0] ?? 'Overig';
}

/** Secties in vaste volgorde, met hun links. */
export function secties(links: PortaalLink[], portal: Portal | null): { naam: string; links: PortaalLink[] }[] {
    const volgorde = [...Object.keys((portal && portal !== 'geen' && SECTIES[portal]) || {}), 'Overig'];

    return volgorde
        .map((naam) => ({ naam, links: links.filter((l) => l.sectie === naam) }))
        .filter((g) => g.links.length > 0);
}

export type NavItem = {
    title: string;
    href: () => string;
    icon: LucideIcon;
    /** Ook in de onderbalk op de telefoon (de eerste 3 op volgorde, plus Meldingen). */
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
            mobile: i.mobile && ++mobiel <= 3,
            sectie: sectieVan(portal, i.title),
        })),
        { title: 'Meldingen', href: meldingen.url(), icon: Bell, mobile: true, sectie: 'Meldingen' },
    ];
}

/** Is deze link de huidige pagina (of een onderliggende pagina)? Startpagina's alleen bij exacte match. */
export function isActief(href: string, huidigeUrl: string): boolean {
    const doel = new URL(href, window.location.href).pathname.replace(/\/$/, '');
    const nu = new URL(huidigeUrl, window.location.href).pathname.replace(/\/$/, '');
    const startpagina = /\/(team|creator|ondernemer|academy)$/.test(doel);

    return startpagina ? nu === doel : nu === doel || nu.startsWith(`${doel}/`);
}
