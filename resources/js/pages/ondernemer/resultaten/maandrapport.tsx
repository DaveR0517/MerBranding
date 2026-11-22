import { Head } from "@inertiajs/react";
import { MaandrapportWeergave } from "@/components/cijfers/maandrapport";
import { maandrapport } from "@/routes/ondernemer";
import { afdrukken } from "@/routes/ondernemer/maandrapport";
import type { Maand, Maandrapport, Metrieken } from "@/types/cijfers";
import { Kop } from "../_onderdelen/opmaak";

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
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
                <Kop onder="Wat er deze maand gebeurde: samenwerkingen, cijfers en je tegoed.">
                    Maandrapport
                </Kop>
                <MaandrapportWeergave
                    r={r}
                    maanden={maanden}
                    metrics={metrics}
                    maandUrl={(m) => maandrapport.url({ maand: m })}
                    afdrukUrl={afdrukken.url({ maand: r.maand })}
                />
            </div>
        </>
    );
}

OndernemerMaandrapport.layout = {
    breadcrumbs: [{ title: "Maandrapport", href: maandrapport() }],
};
