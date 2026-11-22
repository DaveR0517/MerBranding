import { Head } from '@inertiajs/react';
import { KoppelingenLijst } from '@/components/content/koppelingen-scherm';
import { Pagina, PaginaKop } from '@/components/pagina';
import type { Account, Toestemmingstekst } from '@/components/content/types';
import content from '@/routes/creator/content';

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
            <Pagina smal>
                <PaginaKop
                    titel="Koppelingen"
                    omschrijving="Een professioneel of creator-account is nodig voor cijfers per regio. Influencers hebben minimaal één gekoppeld account nodig."
                />
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
            </Pagina>
        </>
    );
}

Koppelingen.layout = {
    breadcrumbs: [{ title: 'Koppelingen', href: content.koppelingen.url() }],
};
