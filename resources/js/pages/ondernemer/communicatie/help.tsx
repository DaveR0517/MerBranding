import { Head } from '@inertiajs/react';
import type { HelpProps } from '@/components/communicatie/help-scherm';
import { HelpScherm } from '@/components/communicatie/help-scherm';
import communicatie from '@/routes/ondernemer/communicatie';
import { Kop } from '../_onderdelen/opmaak';

export default function Help(props: HelpProps) {
    return (
        <>
            <Head title="Help" />
            <HelpScherm
                {...props}
                kop={<Kop onder="Antwoord op de meest gestelde vragen. Kom je er niet uit? Stel je vraag.">Help</Kop>}
                helpUrl={communicatie.help.url()}
                stellenUrl={communicatie.help.stellen.url()}
                vraagUrl={(id) => communicatie.help.vraag.url(id)}
            />
        </>
    );
}
