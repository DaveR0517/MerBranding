import { Head } from "@inertiajs/react";
import beheer from "@/routes/ondernemer/beheer";
import { Kop } from "../_onderdelen/opmaak";
import { BedrijfsInstellingen } from "./_onderdelen/instellingen";
import type { Instellingen, Vestiging } from "./_onderdelen/types";
import { VestigingenBeheer } from "./_onderdelen/vestigingen-beheer";

type Props = {
    company: { name: string } & Instellingen;
    locations: Vestiging[];
    can: { instellingen: boolean };
};

export default function Vestigingen({ company, locations, can }: Props) {
    return (
        <>
            <Head title="Vestigingen" />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
                <Kop onder="Per vestiging stel je in wanneer een creator langs kan komen en wat hij of zij mag ervaren.">
                    Vestigingen
                </Kop>

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
            </div>
        </>
    );
}

Vestigingen.layout = {
    breadcrumbs: [{ title: "Vestigingen", href: beheer.vestigingen() }],
};
