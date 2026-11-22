import { Head, Link, router } from '@inertiajs/react';
import { Copy, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Leeg, MeerLijst, Pagina, PaginaKop } from '@/components/pagina';
import { Miniatuur, StudioStatus } from '@/components/studio/miniatuur';
import { NieuwOntwerp } from '@/components/studio/nieuw-ontwerp';
import type { Formaat, OntwerpItem } from '@/components/studio/typen';
import { datum } from '@/lib/datum';
import ontwerpen from '@/routes/team/studio/ontwerpen';

type Props = {
    ontwerpen: OntwerpItem[];
    filters: { formaat: string | null; status: string | null };
    formaten: Record<string, Formaat>;
    basissen: Record<string, string>;
    statussen: Record<string, string>;
    sjablonen: OntwerpItem[];
    totaal: number;
};

const kiesVeld = 'h-10 rounded-lg border bg-card px-3 text-sm';

/** MeStudio, startscherm: recente ontwerpen voor de eigen marketing, filters en een nieuw ontwerp. */
export default function Ontwerpen({ ontwerpen: lijst, filters, formaten, basissen, statussen, sjablonen, totaal }: Props) {
    const [nieuw, setNieuw] = useState(false);
    const filter = (wijziging: Partial<Props['filters']>) =>
        router.get(
            ontwerpen.index.url(),
            Object.fromEntries(Object.entries({ ...filters, ...wijziging }).filter(([, v]) => v)),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Ontwerpen" />
            <Pagina>
                <PaginaKop
                    boven="MeStudio"
                    titel="Ontwerpen"
                    omschrijving="Posts, stories en carrousels voor de eigen marketing van MEBRANDING. Begin vanaf een sjabloon, zet er een foto of video in en sla het op als geheel."
                    acties={
                        <button type="button" data-test="studio-nieuw" onClick={() => setNieuw(true)} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet px-4 text-sm font-semibold text-white hover:bg-violet/90">
                            <Plus className="size-4" aria-hidden /> Nieuw ontwerp
                        </button>
                    }
                    extra={
                        <div className="flex flex-wrap gap-2">
                            <select aria-label="Formaat" className={kiesVeld} value={filters.formaat ?? ''} onChange={(e) => filter({ formaat: e.target.value || null })}>
                                <option value="">Alle formaten</option>
                                {Object.entries(formaten).map(([k, f]) => (
                                    <option key={k} value={k}>
                                        {f.naam}
                                    </option>
                                ))}
                            </select>
                            <select aria-label="Status" className={kiesVeld} value={filters.status ?? ''} onChange={(e) => filter({ status: e.target.value || null })}>
                                <option value="">Alle statussen</option>
                                {Object.entries(statussen).map(([k, s]) => (
                                    <option key={k} value={k}>
                                        {s}
                                    </option>
                                ))}
                            </select>
                        </div>
                    }
                />

                {lijst.length === 0 ? (
                    <Leeg
                        titel={totaal === 0 ? 'Nog geen ontwerpen' : 'Geen ontwerpen met deze filters'}
                        actie={
                            totaal === 0 && (
                                <button type="button" onClick={() => setNieuw(true)} className="h-10 rounded-lg bg-violet px-4 text-sm font-semibold text-white">
                                    Maak je eerste ontwerp
                                </button>
                            )
                        }
                    >
                        {totaal === 0 ? 'Kies een huisstijlsjabloon, zoals Creator in de spotlight, en vul de vakken.' : 'Zet een filter terug op alles.'}
                    </Leeg>
                ) : (
                    <MeerLijst eerst={12} label="Meer ontwerpen" className="grid grid-cols-2 gap-3 space-y-0 sm:grid-cols-3 md:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                        {lijst.map((o) => (
                            <li key={o.id} className="group relative min-w-0 rounded-xl border bg-card p-2">
                                <Link href={ontwerpen.bewerken.url(o.id)} className="block space-y-1.5" data-test="studio-ontwerp">
                                    <Miniatuur item={o} />
                                    <span className="block truncate px-1 text-sm font-semibold">{o.name}</span>
                                </Link>
                                <div className="flex items-center gap-1 px-1">
                                    <StudioStatus status={o.status} />
                                    <span className="flex-1" />
                                    <button
                                        type="button"
                                        aria-label={`${o.name} dupliceren`}
                                        title="Dupliceren"
                                        onClick={() => router.post(ontwerpen.dupliceren.url(o.id))}
                                        className="grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                                    >
                                        <Copy className="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label={`${o.name} verwijderen`}
                                        title="Verwijderen"
                                        onClick={() => confirm(`"${o.name}" verwijderen? Exports gaan ook weg.`) && router.delete(ontwerpen.verwijderen.url(o.id))}
                                        className="grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-rood"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                                <p className="truncate px-1 text-xs text-muted-foreground">
                                    {o.format_naam}, {datum(o.updated_at ?? null)}
                                </p>
                            </li>
                        ))}
                    </MeerLijst>
                )}
            </Pagina>
            <NieuwOntwerp open={nieuw} onOpenChange={setNieuw} formaten={formaten} basissen={basissen} sjablonen={sjablonen} />
        </>
    );
}
