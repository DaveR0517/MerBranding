import { Head } from '@inertiajs/react';
import { UitnodigenScherm } from '@/components/communicatie/uitnodigen-scherm';
import { Kop } from '../_onderdelen/opmaak';

export default function Uitnodigen({ link, stats }: { link: string; code: string; stats: { aangemeld: number; klant: number | null } }) {
    return (
        <>
            <Head title="Vriend uitnodigen" />
            <div className="mx-auto w-full max-w-5xl px-4 pt-4 md:px-6 md:pt-6">
                <Kop>Uitnodigen</Kop>
            </div>
            <UitnodigenScherm omgeving="ondernemer" link={link} stats={stats} />
        </>
    );
}
