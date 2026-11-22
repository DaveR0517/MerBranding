import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Tabs } from '@/components/pagina';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import ontwerpen from '@/routes/team/studio/ontwerpen';
import { Miniatuur } from './miniatuur';
import type { Formaat, OntwerpItem } from './typen';

/** Nooit blanco: begin vanaf een huisstijlsjabloon of vanaf een merkbasis (licht, donker of verloop) in een formaat. */
export function NieuwOntwerp({
    open,
    onOpenChange,
    formaten,
    basissen,
    sjablonen,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    formaten: Record<string, Formaat>;
    basissen: Record<string, string>;
    sjablonen: OntwerpItem[];
}) {
    const [tab, setTab] = useState<'sjabloon' | 'formaat'>('sjabloon');
    const [formaat, setFormaat] = useState('instagram_post');
    const [bezig, setBezig] = useState(false);
    const maak = (data: { formaat: string; basis: string } | { sjabloon: number }) => {
        setBezig(true);
        router.post(ontwerpen.store.url(), data, { onFinish: () => setBezig(false) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[88dvh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Nieuw ontwerp</DialogTitle>
                    <DialogDescription>Begin vanaf een huisstijlsjabloon of een merkbasis: altijd met logo, merkkleuren en tekststijlen. Een sjabloon gebruiken maakt een kopie; het sjabloon blijft zoals het is.</DialogDescription>
                </DialogHeader>
                <Tabs
                    tabs={[
                        { waarde: 'sjabloon', label: 'Vanaf sjabloon', aantal: sjablonen.length },
                        { waarde: 'formaat', label: 'Merkbasis' },
                    ]}
                    actief={tab}
                    onKies={setTab}
                />
                {tab === 'formaat' ? (
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <p className="text-sm font-semibold">1. Formaat</p>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Formaat">
                                {Object.entries(formaten).map(([sleutel, f]) => (
                                    <button
                                        key={sleutel}
                                        type="button"
                                        role="radio"
                                        aria-checked={formaat === sleutel}
                                        data-test={`studio-formaat-${sleutel}`}
                                        onClick={() => setFormaat(sleutel)}
                                        className={`flex items-center gap-3 rounded-xl border bg-card p-2.5 text-left ${formaat === sleutel ? 'border-violet ring-2 ring-violet/30' : 'hover:border-violet'}`}
                                    >
                                        <span className="grid size-10 shrink-0 place-items-center">
                                            <span className="block rounded-sm bg-lila" style={{ width: f.breedte >= f.hoogte ? 36 : (36 * f.breedte) / f.hoogte, height: f.hoogte >= f.breedte ? 36 : (36 * f.hoogte) / f.breedte }} />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-semibold">{f.naam}</span>
                                            <span className="block text-xs text-muted-foreground tabular-nums">
                                                {f.breedte} x {f.hoogte}
                                                {sleutel === 'carrousel' ? ", 3 pagina's" : ''}
                                            </span>
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div className="space-y-2">
                            <p className="text-sm font-semibold">2. Merkbasis</p>
                            <div className="grid grid-cols-3 gap-2">
                                {Object.entries(basissen).map(([sleutel, label]) => (
                                    <button
                                        key={sleutel}
                                        type="button"
                                        disabled={bezig}
                                        data-test={`studio-basis-${sleutel}`}
                                        onClick={() => maak({ formaat, basis: sleutel })}
                                        className="space-y-1.5 rounded-xl border bg-card p-2 text-center hover:border-violet"
                                    >
                                        <span
                                            className={`flex aspect-square flex-col justify-between rounded-lg p-2 text-left ${sleutel === 'licht' ? 'bg-[#F6F5F9]' : sleutel === 'donker' ? 'bg-aubergine-dark' : 'bg-brand-gradient'}`}
                                        >
                                            {sleutel !== 'verloop' && <span className="-mx-2 -mt-2 block h-1.5 rounded-t-lg bg-brand-gradient" />}
                                            <span className={`block text-xs font-extrabold ${sleutel === 'licht' ? 'text-aubergine' : 'text-white'}`}>Jouw kop</span>
                                            <span className={`block h-1.5 w-10 rounded ${sleutel === 'licht' ? 'bg-aubergine' : 'bg-white'}`} />
                                        </span>
                                        <span className="block text-sm font-semibold">{label}</span>
                                    </button>
                                ))}
                            </div>
                        </div>
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
