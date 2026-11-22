import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Leeg,
    MeerLijst,
    Pagina,
    PaginaKop,
    Rij,
    Sectie,
} from '@/components/pagina';
import { datum } from '@/lib/datum';
import * as creator from '@/routes/creator';
import * as cijfers from '@/routes/creator/cijfers';

type Klus = {
    id: number;
    title: string;
    company: string;
    location: string;
    live_at: string | null;
    measure_until: string | null;
    measuring: boolean;
    entered: boolean;
};

export default function CijfersOverzicht({
    collaborations,
}: {
    collaborations: Klus[];
}) {
    const nu = collaborations.filter((c) => c.measuring);
    const eerder = collaborations.filter((c) => !c.measuring);

    return (
        <>
            <Head title="Cijfers" />
            <Pagina smal>
                <PaginaKop
                    titel="Cijfers van je posts"
                    omschrijving="Tijdens de meetperiode van 14 dagen vul je hier je cijfers in, met een screenshot."
                />
                {collaborations.length === 0 ? (
                    <Leeg titel="Nog geen posts">
                        Zodra je een post live hebt gezet, kun je hier de
                        cijfers invullen.
                    </Leeg>
                ) : (
                    <>
                        {nu.length > 0 && (
                            <Lijst titel="Nu aan het meten" items={nu} />
                        )}
                        {eerder.length > 0 && (
                            <Lijst titel="Meetperiode voorbij" items={eerder} />
                        )}
                    </>
                )}
            </Pagina>
        </>
    );
}

function Lijst({ titel, items }: { titel: string; items: Klus[] }) {
    return (
        <Sectie titel={titel}>
            <MeerLijst>
                {items.map((c) => (
                    <li key={c.id}>
                        <Link
                            href={cijfers.invoeren.url(c.id)}
                            className="block"
                            data-test="klus-cijfers"
                        >
                            <Rij
                                className="transition hover:border-violet"
                                titel={c.title}
                                onder={`${c.company}, ${c.location}${c.measuring && c.measure_until ? `, invullen tot ${datum(c.measure_until)}` : ''}`}
                                rechts={
                                    <>
                                        {c.measuring && !c.entered && (
                                            <span className="rounded-full bg-violet px-2.5 py-0.5 text-xs font-bold text-white">
                                                Invullen
                                            </span>
                                        )}
                                        <ChevronRight
                                            className="size-4 text-muted-foreground"
                                            aria-hidden
                                        />
                                    </>
                                }
                            />
                        </Link>
                    </li>
                ))}
            </MeerLijst>
        </Sectie>
    );
}

CijfersOverzicht.layout = {
    breadcrumbs: [{ title: 'Cijfers', href: creator.cijfers() }],
};
