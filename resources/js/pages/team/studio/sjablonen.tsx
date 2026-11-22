import { Head, Link, router } from '@inertiajs/react';
import { Archive, ArchiveRestore, Copy, ImagePlus, Pencil, Type } from 'lucide-react';
import { useState } from 'react';
import { Leeg, MeerLijst, Pagina, PaginaKop, Tabs } from '@/components/pagina';
import { UploadKnop } from '@/components/studio/bronkiezer';
import { Miniatuur } from '@/components/studio/miniatuur';
import type { Formaat, Media, OntwerpItem } from '@/components/studio/typen';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import sjablonen from '@/routes/team/studio/sjablonen';

type Props = {
    sjablonen: OntwerpItem[];
    archief: boolean;
    aantalGearchiveerd: number;
    formaten: Record<string, Formaat>;
    fotos: Media[];
    maxUploadMb: number;
};

const veld = 'h-10 w-full rounded-lg border bg-background px-3 text-sm';
const knopKlein = 'grid size-9 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground';

/** MeStudio, Sjablonen: huisstijlsjablonen en eigen sjablonen met vakken; gebruiken maakt een nieuw ontwerp. */
export default function Sjablonen({ sjablonen: lijst, archief, aantalGearchiveerd, formaten, fotos, maxUploadMb }: Props) {
    const [formaat, setFormaat] = useState('');
    const [hernoem, setHernoem] = useState<OntwerpItem | null>(null);
    const [nieuweNaam, setNieuweNaam] = useState('');
    const [achtergrond, setAchtergrond] = useState(false);
    const [achtergrondFormaat, setAchtergrondFormaat] = useState('instagram_post');
    const zichtbaar = lijst.filter((s) => !formaat || s.format === formaat);

    return (
        <>
            <Head title="Sjablonen" />
            <Pagina>
                <PaginaKop
                    boven="MeStudio"
                    titel="Sjablonen"
                    omschrijving="Ontwerpen met vakken voor een foto, video of tekst. Gebruik een sjabloon en je krijgt een nieuw ontwerp om in te vullen."
                    acties={
                        <button type="button" onClick={() => setAchtergrond(true)} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet px-4 text-sm font-semibold text-white hover:bg-violet/90">
                            <ImagePlus className="size-4" aria-hidden /> Vanaf eigen achtergrond
                        </button>
                    }
                    extra={
                        <div className="space-y-3">
                            <Tabs
                                tabs={[
                                    { waarde: 'actief', label: 'In gebruik', aantal: archief ? undefined : lijst.length },
                                    { waarde: 'archief', label: 'Archief', aantal: aantalGearchiveerd },
                                ]}
                                actief={archief ? 'archief' : 'actief'}
                                onKies={(t) => router.get(sjablonen.index.url(), t === 'archief' ? { archief: 1 } : {}, { preserveScroll: true })}
                            />
                            <select aria-label="Formaat" className="h-10 rounded-lg border bg-card px-3 text-sm" value={formaat} onChange={(e) => setFormaat(e.target.value)}>
                                <option value="">Alle formaten</option>
                                {Object.entries(formaten).map(([k, f]) => (
                                    <option key={k} value={k}>
                                        {f.naam}
                                    </option>
                                ))}
                            </select>
                        </div>
                    }
                />

                {zichtbaar.length === 0 ? (
                    <Leeg titel={archief ? 'Niets in het archief' : 'Geen sjablonen in dit formaat'}>
                        {archief ? 'Gearchiveerde sjablonen staan hier en kun je terugzetten.' : 'Kies een ander formaat of maak er een vanaf een eigen achtergrond.'}
                    </Leeg>
                ) : (
                    <MeerLijst eerst={12} label="Meer sjablonen" className="grid grid-cols-2 gap-3 space-y-0 sm:grid-cols-3 md:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                        {zichtbaar.map((s) => (
                            <li key={s.id} className="min-w-0 space-y-1.5 rounded-xl border bg-card p-2">
                                <Miniatuur item={s} />
                                <div className="px-1">
                                    <p className="truncate text-sm font-semibold">{s.name}</p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {s.format_naam}
                                        {s.aantal_paginas > 1 ? `, ${s.aantal_paginas} pagina's` : ''}, {s.vakken} {s.vakken === 1 ? 'vak' : 'vakken'}
                                        {s.is_builtin ? ', huisstijl' : s.maker ? `, ${s.maker}` : ''}
                                    </p>
                                </div>
                                {!archief && (
                                    <button
                                        type="button"
                                        data-test="studio-sjabloon-gebruiken"
                                        onClick={() => router.post(sjablonen.gebruiken.url(s.id))}
                                        className="h-10 w-full rounded-lg bg-violet text-sm font-semibold text-white hover:bg-violet/90"
                                    >
                                        Sjabloon gebruiken
                                    </button>
                                )}
                                <div className="flex justify-between">
                                    <Link href={sjablonen.bewerken.url(s.id)} className={knopKlein} aria-label={`${s.name} bewerken`} title="Sjabloon bewerken">
                                        <Pencil className="size-4" />
                                    </Link>
                                    <button type="button" className={knopKlein} aria-label={`${s.name} hernoemen`} title="Hernoemen" onClick={() => { setHernoem(s); setNieuweNaam(s.name); }}>
                                        <Type className="size-4" />
                                    </button>
                                    <button type="button" className={knopKlein} aria-label={`${s.name} dupliceren`} title="Dupliceren" onClick={() => router.post(sjablonen.dupliceren.url(s.id), {}, { preserveScroll: true })}>
                                        <Copy className="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        className={knopKlein}
                                        aria-label={archief ? `${s.name} terugzetten` : `${s.name} archiveren`}
                                        title={archief ? 'Terugzetten' : 'Archiveren'}
                                        onClick={() => router.post(sjablonen.archiveren.url(s.id), {}, { preserveScroll: true })}
                                    >
                                        {archief ? <ArchiveRestore className="size-4" /> : <Archive className="size-4" />}
                                    </button>
                                </div>
                            </li>
                        ))}
                    </MeerLijst>
                )}
            </Pagina>

            <Dialog open={!!hernoem} onOpenChange={(o) => !o && setHernoem(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Sjabloon hernoemen</DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (hernoem) {
                                router.patch(sjablonen.hernoemen.url(hernoem.id), { name: nieuweNaam }, { preserveScroll: true, onSuccess: () => setHernoem(null) });
                            }
                        }}
                    >
                        <input aria-label="Nieuwe naam" className={veld} value={nieuweNaam} onChange={(e) => setNieuweNaam(e.target.value)} maxLength={120} required />
                        <button type="submit" className="h-10 w-full rounded-lg bg-violet text-sm font-semibold text-white">
                            Opslaan
                        </button>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={achtergrond} onOpenChange={setAchtergrond}>
                <DialogContent className="max-h-[88dvh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Sjabloon vanaf eigen achtergrond</DialogTitle>
                        <DialogDescription>
                            Bijvoorbeeld een ontwerp uit je bestaande Instagram-kit. De afbeelding komt vergrendeld onderin; daarna zet je er een fotovak, videovak of tekstvak op.
                        </DialogDescription>
                    </DialogHeader>
                    <label className="block space-y-1">
                        <span className="text-xs font-semibold text-muted-foreground">Formaat</span>
                        <select className={veld} value={achtergrondFormaat} onChange={(e) => setAchtergrondFormaat(e.target.value)}>
                            {Object.entries(formaten).map(([k, f]) => (
                                <option key={k} value={k}>
                                    {f.naam} ({f.breedte} x {f.hoogte})
                                </option>
                            ))}
                        </select>
                    </label>
                    <UploadKnop soort="foto" maxUploadMb={maxUploadMb} label="Achtergrond uploaden" onKlaar={(m) => router.post(sjablonen.achtergrond.url(), { formaat: achtergrondFormaat, media: m.id })} />
                    {fotos.length > 0 && (
                        <div className="space-y-2">
                            <p className="text-sm font-semibold">Of kies uit de mediatheek</p>
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                {fotos.map((m) => (
                                    <button
                                        key={m.id}
                                        type="button"
                                        title={m.name}
                                        onClick={() => router.post(sjablonen.achtergrond.url(), { formaat: achtergrondFormaat, media: m.id })}
                                        className="overflow-hidden rounded-lg border hover:border-violet"
                                    >
                                        <img src={m.url} alt="" loading="lazy" className="aspect-square w-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
