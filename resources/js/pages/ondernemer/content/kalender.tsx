import { Head } from '@inertiajs/react';
import { Maandkalender } from '@/components/content/maandkalender';
import type { Kalender as KalenderData } from '@/components/content/types';
import { samenwerking } from '@/routes/ondernemer';
import content from '@/routes/ondernemer/content';
import { Kop } from '../_onderdelen/opmaak';

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
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
                <Kop
                    onder={`${company.name}. Bezoeken, publicaties en livegangen uit je samenwerkingen, plus je eigen notities en ideeën.`}
                >
                    Contentkalender
                </Kop>
                <Maandkalender
                    kalender={kalender}
                    maandUrl={(m) =>
                        content.kalender.url({ query: { maand: m } })
                    }
                    samenwerkingUrl={(id) => samenwerking.url(id)}
                    notitieUrl={content.notities.opslaan.url()}
                    verwijderUrl={(id) => content.notities.verwijderen.url(id)}
                />
            </div>
        </>
    );
}

Kalender.layout = {
    breadcrumbs: [{ title: 'Contentkalender', href: content.kalender.url() }],
};
