import { Radio } from 'lucide-react';
import type { NavModule } from '@/lib/portaal';
import kanalen from '@/routes/team/kanalen';

/** Navigatie van de module eigen kanalen: het Instagram- en TikTok-account van MEBRANDING, in de afdeling MeStudio. */
const nav: NavModule = {
    team: [{ title: 'Eigen kanalen', href: () => kanalen.index.url(), icon: Radio, order: 5, permission: 'kanalen.beheren' }],
};

export default nav;
