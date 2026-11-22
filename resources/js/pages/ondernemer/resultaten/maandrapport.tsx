import { Head } from '@inertiajs/react';
import { MaandrapportWeergave } from '@/components/cijfers/maandrapport';
import { Pagina, PaginaKop } from '@/components/pagina';
import { maandrapport } from '@/routes/ondernemer';
import { afdrukken } from '@/routes/ondernemer/maandrapport';
import type { Maand, Maandrapport, Metrieken } from '@/types/cijfers';

export default function OndernemerMaandrapport({
    rapport: r,
    maanden,
    metrics,
}: {
    rapport: Maandrapport;
    maanden: Maand[];
    metrics: Metrieken;
}) {
    return (
        <>
            <Head title={`Maandrapport ${r.maand_label}`} />
            <Pagina>
                <PaginaKop
                    titel="Maandrapport"
                    omschrijving="Wat er deze maand gebeurde: samenwerkingen, cijfers en je tegoed."
                />
                <MaandrapportWeergave
                    r={r}
                    maanden={maanden}
                    metrics={metrics}
                    maandUrl={(m) => maandrapport.url({ maand: m })}
                    afdrukUrl={afdrukken.url({ maand: r.maand })}
                />
            </Pagina>
        </>
    );
}

OndernemerMaandrapport.layout = {
    breadcrumbs: [{ title: 'Maandrapport', href: maandrapport() }],
};
