import { Head } from '@inertiajs/react';
import type { HelpProps } from '@/components/communicatie/help-scherm';
import { HelpScherm } from '@/components/communicatie/help-scherm';
import communicatie from '@/routes/creator/communicatie';
import { Kop } from '../_onderdelen/opmaak';

export default function Help(props: HelpProps) {
    return (
        <>
            <Head title="Help" />
            <HelpScherm
                {...props}
                kop={<div className="-mx-4 -mt-4 md:-mx-6 md:-mt-6"><Kop boven="Help" titel="Hoe kunnen we helpen?" onder="Antwoord op de meest gestelde vragen. Kom je er niet uit? Stel je vraag." /></div>}
                helpUrl={communicatie.help.url()}
                stellenUrl={communicatie.help.stellen.url()}
                vraagUrl={(id) => communicatie.help.vraag.url(id)}
            />
        </>
    );
}
