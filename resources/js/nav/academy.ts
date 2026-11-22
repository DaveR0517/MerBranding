import { GraduationCap } from 'lucide-react';
import { overzicht } from '@/routes/academy';
import { cursusdagen } from '@/routes/team/academy';
import type { NavModule } from '@/lib/portaal';

/** Navigatie van de academy: Mijn academy voor studenten en creators, Cursusdagen (met tabbladen) voor academybeheer. */
const nav: NavModule = {
    academy: [{ title: 'Mijn academy', href: () => overzicht.url(), icon: GraduationCap, mobile: true, order: 10 }],
    creator: [{ title: 'Mijn academy', href: () => overzicht.url(), icon: GraduationCap, order: 85 }],
    team: [{ title: 'Cursusdagen', href: () => cursusdagen.url(), icon: GraduationCap, order: 58, permission: 'academy.beheren' }],
};

export default nav;
