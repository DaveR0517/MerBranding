import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Tabs } from '@/components/pagina';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import ontwerpen from '@/routes/team/studio/ontwerpen';
import { Miniatuur } from './miniatuur';
import type { Formaat, OntwerpItem } from './typen';

/** Kies een leeg formaat of begin vanaf een sjabloon. */
export function NieuwOntwerp({ open, onOpenChange, formaten, sjablonen }: { open: boolean; onOpenChange: (o: boolean) => void; formaten: Record<string, Formaat>; sjablonen: OntwerpItem[] }) {
    const [tab, setTab] = useState<'sjabloon' | 'formaat'>('sjabloon');
    const [bezig, setBezig] = useState(false);
    const maak = (data: { formaat: string } | { sjabloon: number }) => {
        setBezig(true);
        router.post(ontwerpen.store.url(), data, { onFinish: () => setBezig(false) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[88dvh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Nieuw ontwerp</DialogTitle>
                    <DialogDescription>Begin vanaf een huisstijlsjabloon of kies een leeg formaat. Een sjabloon gebruiken maakt een kopie; het sjabloon blijft zoals het is.</DialogDescription>
                </DialogHeader>
                <Tabs
                    tabs={[
                        { waarde: 'sjabloon', label: 'Vanaf sjabloon', aantal: sjablonen.length },
                        { waarde: 'formaat', label: 'Leeg formaat' },
                    ]}
                    actief={tab}
                    onKies={setTab}
                />
                {tab === 'formaat' ? (
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {Object.entries(formaten).map(([sleutel, f]) => (
                            <button
                                key={sleutel}
                                type="button"
                                disabled={bezig}
                                data-test={`studio-formaat-${sleutel}`}
                                onClick={() => maak({ formaat: sleutel })}
                                className="flex flex-col items-center gap-2 rounded-xl border bg-card p-3 text-center hover:border-violet"
                            >
                                <span className="grid h-20 w-full place-items-center">
                                    <span className="block rounded-sm bg-brand-gradient" style={{ width: f.breedte >= f.hoogte ? 64 : (64 * f.breedte) / f.hoogte, height: f.hoogte >= f.breedte ? 64 : (64 * f.hoogte) / f.breedte }} />
                                </span>
                                <span className="text-sm font-semibold">{f.naam}</span>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {f.breedte} x {f.hoogte}
                                    {sleutel === 'carrousel' ? ", 3 pagina's" : ''}
                                </span>
                            </button>
                        ))}
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {sjablonen.map((s) => (
                            <button key={s.id} type="button" disabled={bezig} data-test="studio-sjabloon-kies" onClick={() => maak({ sjabloon: s.id })} className="space-y-1.5 rounded-xl border bg-card p-2 text-left hover:border-violet">
                                <Miniatuur item={s} />
                                <span className="block truncate text-sm font-semibold">{s.name}</span>
                                <span className="block truncate text-xs text-muted-foreground">{s.format_naam}</span>
                            </button>
                        ))}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
