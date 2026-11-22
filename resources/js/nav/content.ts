import {
    CalendarDays,
    CalendarRange,
    FileBadge,
    Lightbulb,
    Link2,
    Plug,
    Radar,
    Telescope,
} from 'lucide-react';
import type { NavModule } from '@/lib/portaal';
import creator from '@/routes/creator/content';
import ondernemer from '@/routes/ondernemer/content';
import team from '@/routes/team/content';

/** Navigatie van de module content, koppelingen en planning. */
const nav: NavModule = {
    team: [
        {
            title: 'Planning',
            href: () => team.planning.url(),
            icon: CalendarRange,
            order: 30,
            permission: 'planning.beheren',
        },
        {
            title: 'Content per bedrijf',
            href: () => team.bedrijf.url(),
            icon: CalendarDays,
            order: 45,
            permission: 'bedrijven.bekijken',
        },
        {
            title: 'API-status',
            href: () => team.koppelingen.url(),
            icon: Plug,
            order: 70,
            permission: 'koppelingen.beheren',
        },
        {
            title: 'Radar',
            href: () => team.radar.url(),
            icon: Radar,
            order: 80,
        },
    ],
    creator: [
        {
            title: 'Koppelingen',
            href: () => creator.koppelingen.url(),
            icon: Link2,
            order: 60,
        },
    ],
    ondernemer: [
        {
            title: 'Contentkalender',
            href: () => ondernemer.kalender.url(),
            icon: CalendarDays,
            order: 30,
        },
        {
            title: 'Video-ideeën',
            href: () => ondernemer.ideeen.url(),
            icon: Lightbulb,
            order: 40,
        },
        {
            title: 'Gebruiksrechten',
            href: () => ondernemer.rechten.url(),
            icon: FileBadge,
            order: 50,
        },
        {
            title: 'Koppelingen',
            href: () => ondernemer.koppelingen.url(),
            icon: Link2,
            order: 60,
        },
        {
            title: 'Zichtbaarheid',
            href: () => ondernemer.zichtbaarheid.url(),
            icon: Telescope,
            order: 70,
        },
    ],
};

export default nav;
