import { Head, router } from "@inertiajs/react";
import { MaandrapportWeergave } from "@/components/cijfers/maandrapport";
import * as team from "@/routes/team";
import { afdrukken } from "@/routes/team/maandrapport";
import type { Maand, Maandrapport, Metrieken } from "@/types/cijfers";
import { Kop, Pagina, invoer } from "../_onderdelen/basis";

type Props = {
    rapport: Maandrapport;
    maanden: Maand[];
    companies: { id: number; name: string }[];
    metrics: Metrieken;
};

export default function TeamMaandrapport({
    rapport: r,
    maanden,
    companies,
    metrics,
}: Props) {
    return (
        <>
            <Head title={`Maandrapport ${r.bedrijf.name}`} />
            <Pagina>
                <Kop
                    titel="Maandrapport"
                    sub="Per bedrijf per maand: samenwerkingen, cijfers en tegoed. Zo ziet de ondernemer het ook."
                    acties={
                        <label className="text-sm">
                            <span className="sr-only">Bedrijf</span>
                            <select
                                className={invoer}
                                value={r.bedrijf.id}
                                onChange={(e) =>
                                    router.visit(
                                        team.maandrapport.url({
                                            bedrijf: Number(e.target.value),
                                            maand: r.maand,
                                        }),
                                    )
                                }
                                data-test="bedrijf"
                            >
                                {companies.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    }
                />
                <MaandrapportWeergave
                    r={r}
                    maanden={maanden}
                    metrics={metrics}
                    maandUrl={(m) =>
                        team.maandrapport.url({
                            bedrijf: r.bedrijf.id,
                            maand: m,
                        })
                    }
                    afdrukUrl={afdrukken.url({
                        bedrijf: r.bedrijf.id,
                        maand: r.maand,
                    })}
                />
            </Pagina>
        </>
    );
}

TeamMaandrapport.layout = {
    breadcrumbs: [
        { title: "Cijfers", href: team.cijfers() },
        { title: "Maandrapport", href: "#" },
    ],
};
