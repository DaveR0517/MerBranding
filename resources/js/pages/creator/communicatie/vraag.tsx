import { Head } from '@inertiajs/react';
import type { Vraag as VraagType } from '@/components/communicatie/types';
import { VraagScherm } from '@/components/communicatie/vraag-scherm';
import communicatie from '@/routes/creator/communicatie';

export default function Vraag({ ticket }: { ticket: VraagType }) {
    return (
        <>
            <Head title={`Vraag ${ticket.number}`} />
            <VraagScherm ticket={ticket} helpUrl={communicatie.help.url()} reagerenUrl={communicatie.help.reageren.url(ticket.id)} />
        </>
    );
}
