import { CalendarHeart, FolderOpen, LayoutTemplate, Palette } from 'lucide-react';
import type { NavModule } from '@/lib/portaal';
import studio from '@/routes/team/studio';

/** Navigatie van MeStudio: eigen marketing van MEBRANDING. Eigen afdeling bovenaan het teammenu (zie SECTIES in lib/portaal). */
const nav: NavModule = {
    team: [
        { title: 'Ontwerpen', href: () => studio.ontwerpen.index.url(), icon: Palette, order: 1, permission: 'studio.gebruiken' },
        { title: 'Sjablonen', href: () => studio.sjablonen.index.url(), icon: LayoutTemplate, order: 2, permission: 'studio.gebruiken' },
        { title: 'Feedplanner', href: () => studio.feedplanner.index.url(), icon: CalendarHeart, order: 3, permission: 'studio.gebruiken' },
        { title: 'Mediatheek', href: () => studio.mediatheek.index.url(), icon: FolderOpen, order: 4, permission: 'studio.gebruiken' },
    ],
};

export default nav;
