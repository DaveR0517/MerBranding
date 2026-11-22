import { Gauge, Trophy } from 'lucide-react';
import * as creator from '@/routes/creator';
import * as team from '@/routes/team';
import type { NavModule } from '@/lib/portaal';

/** Navigatie van de module score-en-klassement. */
const nav: NavModule = {
    team: [{ title: 'Klassement', href: () => team.klassement.url(), icon: Trophy, order: 55, permission: 'creators.bekijken' }],
    creator: [{ title: 'Score', href: () => creator.score.url(), icon: Gauge, mobile: true, order: 40 }],
};

export default nav;
