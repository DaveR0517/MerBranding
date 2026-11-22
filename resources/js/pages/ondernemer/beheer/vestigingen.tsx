import { Head } from '@inertiajs/react';
import { Pagina, PaginaKop } from '@/components/pagina';
import beheer from '@/routes/ondernemer/beheer';
import { BedrijfsInstellingen } from './_onderdelen/instellingen';
import type { Instellingen, Vestiging } from './_onderdelen/types';
import { VestigingenBeheer } from './_onderdelen/vestigingen-beheer';

type Props = {
    company: { name: string } & Instellingen;
    locations: Vestiging[];
    can: { instellingen: boolean };
};

export default function Vestigingen({ company, locations, can }: Props) {
    return (
        <>
            <Head title="Vestigingen" />
            <Pagina>
                <PaginaKop
                    titel="Vestigingen"
                    omschrijving="Per vestiging stel je in wanneer een creator langs kan komen en wat hij of zij mag ervaren."
                />

                <VestigingenBeheer
                    locations={locations}
                    toevoegen={beheer.vestigingen.toevoegen()}
                    bijwerken={(id) => beheer.vestigingen.bijwerken(id)}
                />

                <BedrijfsInstellingen
                    waarden={company}
                    actie={beheer.instellingen()}
                    kanWijzigen={can.instellingen}
                    bedrijf={company.name}
                />
            </Pagina>
        </>
    );
}

Vestigingen.layout = {
    breadcrumbs: [{ title: 'Vestigingen', href: beheer.vestigingen() }],
};
