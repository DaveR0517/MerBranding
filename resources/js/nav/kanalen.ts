import { Mail, Radio } from 'lucide-react';
import type { NavModule } from '@/lib/portaal';
import kanalen from '@/routes/team/kanalen';

/** Navigatie van de module eigen kanalen: Instagram en TikTok in de afdeling MeStudio, Gmail (Mail) onder Dagelijks. */
const nav: NavModule = {
    team: [
        {
            title: 'Eigen kanalen',
            href: () => kanalen.index.url(),
            icon: Radio,
            order: 4.5,
            permission: 'kanalen.beheren',
        },
        // Gmail van MEBRANDING: wie een postvak ziet of zelf heeft (recht mail.postvak komt van de server).
        {
            title: 'Mail',
            href: () => kanalen.mail.url(),
            icon: Mail,
            order: 12,
            permission: 'mail.postvak',
        },
    ],
};

export default nav;
