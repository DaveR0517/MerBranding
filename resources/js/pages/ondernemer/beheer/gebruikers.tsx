import { Head } from '@inertiajs/react';
import {
    KaartRaster,
    Kerncijfer,
    Pagina,
    PaginaKop,
} from '@/components/pagina';
import beheer from '@/routes/ondernemer/beheer';
import { GebruikersBeheer } from './_onderdelen/gebruikers-beheer';
import type { Gebruiker } from './_onderdelen/types';

type Props = {
    company: { name: string; locations: number };
    users: Gebruiker[];
    me: number;
    can: { beheren: boolean };
};

export default function Gebruikers({ company, users, me, can }: Props) {
    const eigenaren = users.filter((u) => u.role === 'eigenaar').length;
    const open = users.filter((u) => u.invited_until).length;
    const tweestaps = users.filter((u) => u.two_factor).length;

    return (
        <>
            <Head title="Gebruikers" />
            <Pagina>
                <PaginaKop
                    titel="Gebruikers"
                    omschrijving={`Wie van ${company.name} in het portaal mag. Eigenaren regelen alles, leden vragen aan en kijken mee.`}
                />

                <KaartRaster kolommen={4} className="grid-cols-2">
                    <Kerncijfer
                        label="Gebruikers"
                        waarde={users.length}
                        toelichting={`${eigenaren} ${eigenaren === 1 ? 'eigenaar' : 'eigenaren'}, ${users.length - eigenaren} ${users.length - eigenaren === 1 ? 'lid' : 'leden'}`}
                    />
                    <Kerncijfer
                        label="Open uitnodigingen"
                        waarde={open}
                        toelichting={
                            open
                                ? 'Nog geen wachtwoord gekozen'
                                : 'Iedereen is binnen'
                        }
                    />
                    <Kerncijfer
                        label="Vestigingen"
                        waarde={company.locations}
                        toelichting="Iedereen ziet alle vestigingen"
                    />
                    <Kerncijfer
                        label="Tweestaps aan"
                        waarde={`${tweestaps} van ${users.length}`}
                        toelichting="Aan te zetten bij Account"
                    />
                </KaartRaster>

                <GebruikersBeheer
                    users={users}
                    me={me}
                    kanBeheren={can.beheren}
                    uitnodigen={beheer.gebruikers.uitnodigen()}
                    rol={(id) => beheer.gebruikers.rol(id)}
                    verwijderen={(id) => beheer.gebruikers.verwijderen(id)}
                    opnieuw={(id) => beheer.gebruikers.opnieuw(id)}
                />
            </Pagina>
        </>
    );
}

Gebruikers.layout = {
    breadcrumbs: [{ title: 'Gebruikers', href: beheer.gebruikers() }],
};
