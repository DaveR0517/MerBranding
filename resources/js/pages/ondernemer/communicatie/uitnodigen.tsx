import { Head } from '@inertiajs/react';
import { UitnodigenScherm } from '@/components/communicatie/uitnodigen-scherm';

export default function Uitnodigen({
    link,
    stats,
}: {
    link: string;
    code: string;
    stats: { aangemeld: number; klant: number | null };
}) {
    return (
        <>
            <Head title="Vriend uitnodigen" />
            <UitnodigenScherm
                omgeving="ondernemer"
                titel="Uitnodigen"
                link={link}
                stats={stats}
            />
        </>
    );
}
