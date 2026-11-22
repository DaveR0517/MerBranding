import { Head } from '@inertiajs/react';
import { BerichtenScherm } from '@/components/communicatie/berichten-scherm';
import type { Gesprek, Gesprekregel } from '@/components/communicatie/types';
import communicatie from '@/routes/team/communicatie';
import { PaginaKop } from '@/components/pagina';

type Props = { conversations: Gesprekregel[]; conversation: Gesprek | null; shared_channel: boolean };

export default function Berichten({ conversations, conversation }: Props) {
    return (
        <>
            <Head title={conversation ? `Berichten: ${conversation.title}` : 'Berichten'} />
            <BerichtenScherm
                omgeving="team"
                conversations={conversations}
                conversation={conversation}
                inboxUrl={communicatie.berichten.url()}
                kop={<PaginaKop titel="Berichten" omschrijving="Gesprekken met ondernemers en creators, per samenwerking." />}
                leegTekst="Nog geen gesprekken. Open een samenwerking en stuur het eerste bericht."
            />
        </>
    );
}
