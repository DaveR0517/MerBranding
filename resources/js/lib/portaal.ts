import type { LucideIcon } from 'lucide-react';
import {
    Bell,
    Building2,
    Clapperboard,
    ClipboardList,
    History,
    Inbox,
    LayoutGrid,
    PlusCircle,
    Users,
} from 'lucide-react';
import { meldingen } from '@/routes';
import * as creator from '@/routes/creator';
import * as ondernemer from '@/routes/ondernemer';
import * as team from '@/routes/team';
import type { Portal } from '@/types/auth';

export type PortaalLink = {
    title: string;
    href: string;
    icon: LucideIcon;
    mobile?: boolean;
};

/** Navigatie per omgeving. Elke omgeving heeft eigen schermen in resources/js/pages/<omgeving>. */
export function navigatie(portal: Portal | null): PortaalLink[] {
    const melding = {
        title: 'Meldingen',
        href: meldingen.url(),
        icon: Bell,
        mobile: true,
    };

    switch (portal) {
        case 'team':
            return [
                { title: 'Werkvoorraad', href: team.werkvoorraad.url(), icon: Inbox, mobile: true },
                { title: 'Samenwerkingen', href: team.samenwerkingen.url(), icon: ClipboardList, mobile: true },
                { title: 'Bedrijven', href: team.bedrijven.url(), icon: Building2 },
                { title: 'Creators', href: team.creators.url(), icon: Users, mobile: true },
                { title: 'Logboek', href: team.logboek.url(), icon: History },
                melding,
            ];
        case 'creator':
            return [
                { title: 'Mijn klussen', href: creator.overzicht.url(), icon: Clapperboard, mobile: true },
                melding,
            ];
        case 'ondernemer':
            return [
                { title: 'Overzicht', href: ondernemer.overzicht.url(), icon: LayoutGrid, mobile: true },
                { title: 'Aanvragen', href: ondernemer.aanvragen.url(), icon: PlusCircle, mobile: true },
                melding,
            ];
        default:
            return [melding];
    }
}

/** Is deze link de huidige pagina (of een onderliggende pagina)? Startpagina's alleen bij exacte match. */
export function isActief(href: string, huidigeUrl: string): boolean {
    const doel = new URL(href, window.location.href).pathname.replace(/\/$/, '');
    const nu = new URL(huidigeUrl, window.location.href).pathname.replace(/\/$/, '');
    const startpagina = /\/(team|creator|ondernemer)$/.test(doel);

    return startpagina ? nu === doel : nu === doel || nu.startsWith(`${doel}/`);
}
