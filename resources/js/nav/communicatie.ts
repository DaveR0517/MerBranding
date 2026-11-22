import { Gift, HandCoins, LifeBuoy, MessagesSquare, CircleHelp } from 'lucide-react';
import * as creator from '@/routes/creator/communicatie';
import * as ondernemer from '@/routes/ondernemer/communicatie';
import * as team from '@/routes/team/communicatie';
import type { NavModule } from '@/lib/portaal';

/** Navigatie van de module communicatie en sales: berichten, support, help, sales-pipeline en vriend uitnodigen. */
const nav: NavModule = {
    team: [
        { title: 'Berichten', href: () => team.berichten.url(), icon: MessagesSquare, order: 25, permission: 'samenwerkingen.bekijken' },
        { title: 'Sales', href: () => team.sales.url(), icon: HandCoins, order: 60, permission: 'sales.beheren' },
        { title: 'Support', href: () => team.support.url(), icon: LifeBuoy, order: 70, permission: 'support.beheren' },
        { title: 'Help', href: () => team.help.url(), icon: CircleHelp, order: 95 },
    ],
    ondernemer: [
        { title: 'Berichten', href: () => ondernemer.berichten.url(), icon: MessagesSquare, mobile: true, order: 30 },
        { title: 'Uitnodigen', href: () => ondernemer.uitnodigen.url(), icon: Gift, order: 80 },
        { title: 'Help', href: () => ondernemer.help.url(), icon: CircleHelp, order: 95 },
    ],
    creator: [
        { title: 'Berichten', href: () => creator.berichten.url(), icon: MessagesSquare, mobile: true, order: 25 },
        { title: 'Vriend uitnodigen', href: () => creator.uitnodigen.url(), icon: Gift, order: 80 },
        { title: 'Help', href: () => creator.help.url(), icon: CircleHelp, order: 95 },
    ],
};

export default nav;
