import { Head } from '@inertiajs/react';
import type { HelpProps } from '@/components/communicatie/help-scherm';
import { HelpScherm } from '@/components/communicatie/help-scherm';
import communicatie from '@/routes/ondernemer/communicatie';
import { PaginaKop } from '@/components/pagina';

export default function Help(props: HelpProps) {
    return (
        <>
            <Head title="Help" />
            <HelpScherm
                {...props}
                kop={
                    <PaginaKop
                        titel="Help"
                        omschrijving="Antwoord op de meest gestelde vragen. Kom je er niet uit? Stel je vraag."
                    />
                }
                helpUrl={communicatie.help.url()}
                stellenUrl={communicatie.help.stellen.url()}
                vraagUrl={(id) => communicatie.help.vraag.url(id)}
            />
        </>
    );
}
