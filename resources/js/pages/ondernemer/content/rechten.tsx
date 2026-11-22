import { Head } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { Voorstel } from '@/components/content/labels';
import { RechtenLijst, rechtStand } from '@/components/content/rechten-lijst';
import type { Recht, StandaardRechten } from '@/components/content/types';
import content from '@/routes/ondernemer/content';
import { Kop } from '../_onderdelen/opmaak';

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
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
                <Kop
                    onder={`${company.name}. Al je video's met de afgesproken gebruiksrechten. Zo weet je wat je waar mag gebruiken, en tot wanneer.`}
                >
                    Gebruiksrechten
                </Kop>

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

                <dl className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {tellers.map((t) => (
                        <div
                            key={t.label}
                            className="rounded-xl border bg-card p-3"
                        >
                            <dd className="font-heading text-2xl font-extrabold">
                                {t.waarde}
                            </dd>
                            <dt className="text-sm text-muted-foreground">
                                {t.label}
                            </dt>
                        </div>
                    ))}
                </dl>

                {rights.length === 0 ? (
                    <p className="rounded-2xl border border-dashed bg-card p-6 text-center text-sm text-muted-foreground">
                        Nog geen video's met gebruiksrechten. Zodra een video
                        live staat, zie je hier tot wanneer je hem mag
                        gebruiken.
                    </p>
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
            </div>
        </>
    );
}

Rechten.layout = {
    breadcrumbs: [{ title: 'Gebruiksrechten', href: content.rechten.url() }],
};
