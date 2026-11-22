import { Head } from '@inertiajs/react';
import { KoppelingenLijst } from '@/components/content/koppelingen-scherm';
import type { Account, Toestemmingstekst } from '@/components/content/types';
import content from '@/routes/ondernemer/content';
import { Kop } from '../_onderdelen/opmaak';

type Props = {
    accounts: Account[];
    toestemming: Toestemmingstekst;
    mag_beheren: boolean;
};

export default function Koppelingen({
    accounts,
    toestemming,
    mag_beheren,
}: Props) {
    return (
        <>
            <Head title="Koppelingen" />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
                <Kop onder="Koppel je eigen accounts, zodat we profielbezoeken en nieuwe volgers op je account kunnen laten zien naast de video's van creators.">
                    Koppelingen
                </Kop>
                <KoppelingenLijst
                    accounts={accounts}
                    toestemming={toestemming}
                    magBeheren={mag_beheren}
                    voor="ondernemer"
                    routes={{
                        toestemming: (p) =>
                            content.koppelingen.toestemming.url(p),
                        handmatig: (p) => content.koppelingen.handmatig.url(p),
                        verbreken: (p) => content.koppelingen.verbreken.url(p),
                    }}
                />
            </div>
        </>
    );
}

Koppelingen.layout = {
    breadcrumbs: [{ title: 'Koppelingen', href: content.koppelingen.url() }],
};
