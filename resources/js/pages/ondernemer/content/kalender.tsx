import { Head } from '@inertiajs/react';
import { Pagina, PaginaKop } from '@/components/pagina';
import { Maandkalender } from '@/components/content/maandkalender';
import type { Kalender as KalenderData } from '@/components/content/types';
import { samenwerking } from '@/routes/ondernemer';
import content from '@/routes/ondernemer/content';

export default function Kalender({
    company,
    kalender,
}: {
    company: { name: string };
    kalender: KalenderData;
}) {
    return (
        <>
            <Head title="Contentkalender" />
            <Pagina>
                <PaginaKop
                    titel="Contentkalender"
                    omschrijving={`${company.name}. Bezoeken, publicaties en livegangen uit je samenwerkingen, plus je eigen notities en ideeën.`}
                />
                <Maandkalender
                    kalender={kalender}
                    maandUrl={(m) =>
                        content.kalender.url({ query: { maand: m } })
                    }
                    samenwerkingUrl={(id) => samenwerking.url(id)}
                    notitieUrl={content.notities.opslaan.url()}
                    verwijderUrl={(id) => content.notities.verwijderen.url(id)}
                />
            </Pagina>
        </>
    );
}

Kalender.layout = {
    breadcrumbs: [{ title: 'Contentkalender', href: content.kalender.url() }],
};
