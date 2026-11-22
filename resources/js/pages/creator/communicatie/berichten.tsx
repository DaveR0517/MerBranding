import { Head } from '@inertiajs/react';
import { BerichtenScherm } from '@/components/communicatie/berichten-scherm';
import type { Gesprek, Gesprekregel } from '@/components/communicatie/types';
import communicatie from '@/routes/creator/communicatie';
import { Kop } from '../_onderdelen/opmaak';

type Props = { conversations: Gesprekregel[]; conversation: Gesprek | null; shared_channel: boolean };

export default function Berichten({ conversations, conversation }: Props) {
    return (
        <>
            <Head title={conversation ? `Berichten: ${conversation.title}` : 'Berichten'} />
            <BerichtenScherm
                omgeving="creator"
                conversations={conversations}
                conversation={conversation}
                inboxUrl={communicatie.berichten.url()}
                kop={<div className="-mx-4 -mt-4 md:-mx-6 md:-mt-6"><Kop boven="Berichten" titel="Berichten" onder="Eén gesprek per klus, met het team van MEBRANDING." /></div>}
                leegTekst="Nog geen klussen. Zodra je een klus hebt, kun je hier met het team praten."
            />
        </>
    );
}
