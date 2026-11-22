import { Head } from '@inertiajs/react';
import {
    KaartRaster,
    Kerncijfer,
    Leeg,
    Pagina,
    PaginaKop,
} from '@/components/pagina';
import { Info } from 'lucide-react';
import { Voorstel } from '@/components/content/labels';
import { RechtenLijst, rechtStand } from '@/components/content/rechten-lijst';
import type { Recht, StandaardRechten } from '@/components/content/types';
import content from '@/routes/ondernemer/content';

type Props = {
    company: { name: string };
    rights: Recht[];
    standaard: StandaardRechten;
    mag_verlengen: boolean;
};

export default function Rechten({
    company,
    rights,
    standaard,
    mag_verlengen,
}: Props) {
    const bijna = rights.filter((r) => rechtStand(r).kleur === 'amber').length;
    const advertentie = rights.filter(
        (r) => r.kind === 'advertentie' && r.status === 'actief',
    ).length;
    const verlopen = rights.filter(
        (r) => rechtStand(r).kleur === 'rood',
    ).length;
    const tellers = [
        {
            label: "video's",
            waarde: new Set(rights.map((r) => r.collaboration.id)).size,
        },
        { label: 'mag in advertenties', waarde: advertentie },
        { label: 'verloopt binnen 30 dagen', waarde: bijna },
        { label: 'verlopen', waarde: verlopen },
    ];

    return (
        <>
            <Head title="Gebruiksrechten" />
            <Pagina>
                <PaginaKop
                    titel="Gebruiksrechten"
                    omschrijving={`${company.name}. Al je video's met de afgesproken gebruiksrechten. Zo weet je wat je waar mag gebruiken, en tot wanneer.`}
                />

                <div className="flex gap-3 rounded-xl border border-violet/20 bg-violet/5 p-4 text-sm">
                    <Info
                        className="mt-0.5 size-5 shrink-0 text-violet"
                        aria-hidden
                    />
                    <div className="space-y-1">
                        <p>
                            Rechten starten zodra de video live staat. Organisch
                            en op je website {standaard.organisch} maanden, in
                            advertenties {standaard.advertentie} maanden. Je
                            krijgt {standaard.melden.join(' en ')} dagen vooraf
                            een melding.
                        </p>
                        <p className="flex flex-wrap items-center gap-1.5 text-muted-foreground">
                            <Voorstel /> Welke rechten bij welk pakket horen is
                            nog niet besloten.
                        </p>
                    </div>
                </div>

                <KaartRaster kolommen={4} className="grid-cols-2">
                    {tellers.map((t) => (
                        <Kerncijfer
                            key={t.label}
                            label={t.label}
                            waarde={t.waarde}
                        />
                    ))}
                </KaartRaster>

                {rights.length === 0 ? (
                    <Leeg titel="Nog geen video's met gebruiksrechten">
                        Zodra een video live staat, zie je hier tot wanneer je
                        hem mag gebruiken.
                    </Leeg>
                ) : (
                    <RechtenLijst
                        rights={rights}
                        verlengUrl={
                            mag_verlengen
                                ? (id) => content.rechten.verlengen.url(id)
                                : undefined
                        }
                    />
                )}
                {mag_verlengen && rights.length > 0 && (
                    <p className="text-sm text-muted-foreground">
                        Verlengen gaat via je contactpersoon. Die vraagt de
                        creator om akkoord, mogelijk tegen een extra vergoeding.
                        Prijzen voor verlengen zijn nog niet bepaald.
                    </p>
                )}
            </Pagina>
        </>
    );
}

Rechten.layout = {
    breadcrumbs: [{ title: 'Gebruiksrechten', href: content.rechten.url() }],
};
