import { Head } from '@inertiajs/react';
import { BerichtenScherm } from '@/components/communicatie/berichten-scherm';
import type { Gesprek, Gesprekregel } from '@/components/communicatie/types';
import communicatie from '@/routes/ondernemer/communicatie';
import { PaginaKop } from '@/components/pagina';

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
                omgeving="ondernemer"
                conversations={conversations}
                conversation={conversation}
                inboxUrl={communicatie.berichten.url()}
                kop={
                    <PaginaKop
                        titel="Berichten"
                        omschrijving="Eén gesprek per samenwerking, met het team van MEBRANDING."
                    />
                }
                leegTekst="Nog geen samenwerkingen. Zodra je een samenwerking aanvraagt, kun je hier met het team praten."
            />
        </>
    );
}
