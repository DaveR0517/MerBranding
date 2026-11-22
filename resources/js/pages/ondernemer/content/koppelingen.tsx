import { Head } from '@inertiajs/react';
import { Pagina, PaginaKop } from '@/components/pagina';
import { KoppelingenLijst } from '@/components/content/koppelingen-scherm';
import type { Account, Toestemmingstekst } from '@/components/content/types';
import content from '@/routes/ondernemer/content';

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
            <Pagina>
                <PaginaKop
                    titel="Koppelingen"
                    omschrijving="Koppel je eigen accounts, zodat we profielbezoeken en nieuwe volgers op je account kunnen laten zien naast de video's van creators."
                />
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
            </Pagina>
        </>
    );
}

Koppelingen.layout = {
    breadcrumbs: [{ title: 'Koppelingen', href: content.koppelingen.url() }],
};
