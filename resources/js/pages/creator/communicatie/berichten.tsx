import { Head } from '@inertiajs/react';
import { BerichtenScherm } from '@/components/communicatie/berichten-scherm';
import type { Gesprek, Gesprekregel } from '@/components/communicatie/types';
import { PaginaKop } from '@/components/pagina';
import communicatie from '@/routes/creator/communicatie';

type Props = {
    conversations: Gesprekregel[];
    conversation: Gesprek | null;
    shared_channel: boolean;
};

export default function Berichten({ conversations, conversation }: Props) {
    return (
        <>
            <Head
                title={
                    conversation
                        ? `Berichten: ${conversation.title}`
                        : 'Berichten'
                }
            />
            <BerichtenScherm
                omgeving="creator"
                conversations={conversations}
                conversation={conversation}
                inboxUrl={communicatie.berichten.url()}
                kop={
                    <PaginaKop
                        titel="Berichten"
                        omschrijving="Eén gesprek per klus, met het team van MEBRANDING."
                    />
                }
                leegTekst="Nog geen klussen. Zodra je een klus hebt, kun je hier met het team praten."
            />
        </>
    );
}
