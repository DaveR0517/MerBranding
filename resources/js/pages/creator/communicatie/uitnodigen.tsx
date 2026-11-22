import { Head } from '@inertiajs/react';
import { UitnodigenScherm } from '@/components/communicatie/uitnodigen-scherm';
import { Kop } from '../_onderdelen/opmaak';

export default function Uitnodigen({ link, stats }: { link: string; code: string; stats: { aangemeld: number; klant: number | null } }) {
    return (
        <>
            <Head title="Vriend uitnodigen" />
            <Kop boven="Uitnodigen" titel="Vriend uitnodigen" onder="Creator nodigt creator uit" />
            <UitnodigenScherm omgeving="creator" link={link} stats={stats} />
        </>
    );
}
