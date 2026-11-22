import { Head } from '@inertiajs/react';
import { KoppelingenLijst } from '@/components/content/koppelingen-scherm';
import type { Account, Toestemmingstekst } from '@/components/content/types';
import content from '@/routes/creator/content';
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
            <Kop
                boven="Account"
                titel="Koppelingen"
                onder="Een professioneel of creator-account is nodig voor cijfers per regio. Influencers hebben minimaal één gekoppeld account nodig."
            />
            <div className="mx-auto w-full max-w-3xl p-4 md:p-6">
                <KoppelingenLijst
                    accounts={accounts}
                    toestemming={toestemming}
                    magBeheren={mag_beheren}
                    voor="creator"
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
