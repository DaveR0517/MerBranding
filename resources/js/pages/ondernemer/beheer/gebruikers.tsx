import { Head } from "@inertiajs/react";
import beheer from "@/routes/ondernemer/beheer";
import { Kop } from "../_onderdelen/opmaak";
import { GebruikersBeheer } from "./_onderdelen/gebruikers-beheer";
import type { Gebruiker } from "./_onderdelen/types";

type Props = {
    company: { name: string; locations: number };
    users: Gebruiker[];
    me: number;
    can: { beheren: boolean };
};

export default function Gebruikers({ company, users, me, can }: Props) {
    const eigenaren = users.filter((u) => u.role === "eigenaar").length;
    const open = users.filter((u) => u.invited_until).length;
    const tweestaps = users.filter((u) => u.two_factor).length;

    return (
        <>
            <Head title="Gebruikers" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
                <Kop
                    onder={`Wie van ${company.name} in het portaal mag. Eigenaren regelen alles, leden vragen aan en kijken mee.`}
                >
                    Gebruikers
                </Kop>

                <dl className="grid grid-cols-2 gap-2 md:grid-cols-4">
                    <Cijfer
                        label="Gebruikers"
                        waarde={users.length}
                        onder={`${eigenaren} ${eigenaren === 1 ? "eigenaar" : "eigenaren"}, ${users.length - eigenaren} ${users.length - eigenaren === 1 ? "lid" : "leden"}`}
                    />
                    <Cijfer
                        label="Open uitnodigingen"
                        waarde={open}
                        onder={
                            open
                                ? "Nog geen wachtwoord gekozen"
                                : "Iedereen is binnen"
                        }
                    />
                    <Cijfer
                        label="Vestigingen"
                        waarde={company.locations}
                        onder="Iedereen ziet alle vestigingen"
                    />
                    <Cijfer
                        label="Tweestaps aan"
                        waarde={`${tweestaps} van ${users.length}`}
                        onder="Aan te zetten bij Account"
                    />
                </dl>

                <GebruikersBeheer
                    users={users}
                    me={me}
                    kanBeheren={can.beheren}
                    uitnodigen={beheer.gebruikers.uitnodigen()}
                    rol={(id) => beheer.gebruikers.rol(id)}
                    verwijderen={(id) => beheer.gebruikers.verwijderen(id)}
                    opnieuw={(id) => beheer.gebruikers.opnieuw(id)}
                />
            </div>
        </>
    );
}

function Cijfer({
    label,
    waarde,
    onder,
}: {
    label: string;
    waarde: number | string;
    onder: string;
}) {
    return (
        <div className="rounded-xl border bg-card p-3 md:p-4">
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1 font-heading text-2xl font-extrabold">
                {waarde}
            </dd>
            <dd className="text-xs text-muted-foreground">{onder}</dd>
        </div>
    );
}

Gebruikers.layout = {
    breadcrumbs: [{ title: "Gebruikers", href: beheer.gebruikers() }],
};
