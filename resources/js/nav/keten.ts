import {
    Building2,
    Clapperboard,
    ClipboardList,
    History,
    Inbox,
    LayoutGrid,
    Map as MapIcon,
    PlusCircle,
    Users,
} from 'lucide-react';
import * as creator from '@/routes/creator';
import * as ondernemer from '@/routes/ondernemer';
import * as team from '@/routes/team';
import type { NavModule } from '@/lib/portaal';

/** Navigatie van de keten (samenwerkingen). Elke module levert zo een bestand in resources/js/nav. */
const nav: NavModule = {
    team: [
        {
            title: 'Werkvoorraad',
            href: () => team.werkvoorraad.url(),
            icon: Inbox,
            mobile: true,
            order: 10,
        },
        {
            title: 'Samenwerkingen',
            href: () => team.samenwerkingen.url(),
            icon: ClipboardList,
            mobile: true,
            order: 20,
            permission: 'samenwerkingen.bekijken',
        },
        {
            title: 'Bedrijven',
            href: () => team.bedrijven.url(),
            icon: Building2,
            order: 40,
            permission: 'bedrijven.bekijken',
        },
        {
            title: 'Kaart',
            href: () => team.kaart.url(),
            icon: MapIcon,
            order: 41,
            permission: 'bedrijven.bekijken',
        },
        {
            title: 'Creators',
            href: () => team.creators.url(),
            icon: Users,
            mobile: true,
            order: 50,
            permission: 'creators.bekijken',
        },
        {
            title: 'Logboek',
            href: () => team.logboek.url(),
            icon: History,
            order: 90,
            permission: 'logboek.bekijken',
        },
    ],
    creator: [
        {
            title: 'Mijn klussen',
            href: () => creator.overzicht.url(),
            icon: Clapperboard,
            mobile: true,
            order: 10,
        },
    ],
    ondernemer: [
        {
            title: 'Overzicht',
            href: () => ondernemer.overzicht.url(),
            icon: LayoutGrid,
            mobile: true,
            order: 10,
        },
        {
            title: 'Aanvragen',
            href: () => ondernemer.aanvragen.url(),
            icon: PlusCircle,
            mobile: true,
            order: 20,
        },
    ],
};

export default nav;
