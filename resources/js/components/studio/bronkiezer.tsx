import { AlertTriangle, Upload } from 'lucide-react';
import { useState } from 'react';
import { Tabs } from '@/components/pagina';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import mediatheek from '@/routes/team/studio/mediatheek';
import type { Bron, CreatorVideo, Logo, Media } from './typen';
import { verstuur } from './typen';

export type BronSoort = 'foto' | 'video' | 'logo';

/** Uploadknop voor de mediatheek; geeft het nieuwe bestand terug. */
export function UploadKnop({
    soort,
    maxUploadMb,
    onKlaar,
    label = 'Uploaden',
    className = '',
}: {
    soort?: BronSoort;
    maxUploadMb: number;
    onKlaar: (m: Media) => void;
    label?: string;
    className?: string;
}) {
    const [bezig, setBezig] = useState(false);
    const [fout, setFout] = useState<string | null>(null);
    const accept = soort === 'video' ? 'video/mp4,video/quicktime,video/webm' : soort === 'logo' ? 'image/svg+xml,image/png' : soort === 'foto' ? 'image/jpeg,image/png,image/webp' : 'image/*,video/*';

    return (
        <div className={className}>
            <label className="inline-flex h-10 cursor-pointer items-center gap-2 rounded-lg bg-violet px-4 text-sm font-semibold text-white hover:bg-violet/90">
                <Upload className="size-4" aria-hidden />
                {bezig ? 'Bezig met uploaden...' : label}
                <input
                    type="file"
                    accept={accept}
                    className="sr-only"
                    data-test="studio-upload"
                    disabled={bezig}
                    onChange={async (e) => {
                        const bestand = e.target.files?.[0];
                        e.target.value = '';

                        if (!bestand) {
                            return;
                        }

                        if (bestand.size > maxUploadMb * 1024 * 1024) {
                            setFout(`Dit bestand is groter dan ${maxUploadMb} MB.`);

                            return;
                        }

                        setBezig(true);
                        setFout(null);
                        const fd = new FormData();
                        fd.append('bestand', bestand);

                        if (soort) {
                            fd.append('soort', soort);
                        }

                        try {
                            const { media } = await verstuur<{ media: Media }>(mediatheek.store.url(), { formulier: fd });
                            onKlaar(media);
                        } catch (err) {
                            setFout((err as Error).message);
                        } finally {
                            setBezig(false);
                        }
                    }}
                />
            </label>
            {fout && (
                <p role="alert" className="mt-2 text-sm text-rood">
                    {fout}
                </p>
            )}
        </div>
    );
}

/** Waarschuwing bij video's van creators: gebruik voor eigen marketing vraagt toestemming (open punt 7). */
export function RechtenWaarschuwing({ video }: { video?: CreatorVideo }) {
    return (
        <div className="flex gap-2 rounded-lg border border-rood/30 bg-rood/5 p-3 text-sm">
            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-rood" aria-hidden />
            <div className="space-y-1">
                <p>
                    Een video van een creator gebruiken voor de eigen marketing van MEBRANDING vraagt toestemming en gebruiksrechten van de creator en de ondernemer. Wat
                    hier mag, is nog niet besloten (open punt 7). Vraag het eerst na.
                </p>
                {video && (
                    <p className="text-muted-foreground">
                        {video.gebruiksrechten.length === 0
                            ? 'Geen gebruiksrechten vastgelegd bij deze video.'
                            : `Vastgelegd: ${video.gebruiksrechten
                                  .map((r) => `${r.kind} ${r.starts_on ?? ''} tot ${r.ends_on ?? ''} (${r.status}${r.is_proposal ? ', VOORSTEL' : ''})`)
                                  .join('; ')}. Dit gaat over gebruik door de ondernemer, niet over MEBRANDING zelf.`}
                    </p>
                )}
            </div>
        </div>
    );
}

export function BronKiezer({
    open,
    onOpenChange,
    soort,
    media,
    creatorVideos,
    logos,
    maxUploadMb,
    onMediaErbij,
    onKies,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    soort: BronSoort;
    media: Media[];
    creatorVideos: CreatorVideo[];
    logos: Logo[];
    maxUploadMb: number;
    onMediaErbij: (m: Media) => void;
    onKies: (bron: Bron) => void;
}) {
    const [tab, setTab] = useState<'eigen' | 'creators'>('eigen');
    const eigen = media.filter((m) => (soort === 'logo' ? m.kind === 'logo' : m.kind === soort));
    const kies = (b: Bron) => {
        onKies(b);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{soort === 'video' ? 'Kies een video' : soort === 'logo' ? 'Kies een logo' : 'Kies een foto'}</DialogTitle>
                    <DialogDescription>Uit de mediatheek van MeStudio, of upload een nieuw bestand.</DialogDescription>
                </DialogHeader>

                {soort === 'video' && (
                    <Tabs
                        tabs={[
                            { waarde: 'eigen', label: 'Mediatheek', aantal: eigen.length },
                            { waarde: 'creators', label: "Video's van creators", aantal: creatorVideos.length },
                        ]}
                        actief={tab}
                        onKies={setTab}
                    />
                )}

                {soort === 'logo' && (
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {logos.map((l) => (
                            <button
                                key={l.naam}
                                type="button"
                                onClick={() => kies({ soort: 'logo', naam: l.naam })}
                                className="flex flex-col items-center gap-2 rounded-lg border p-3 text-sm hover:border-violet"
                            >
                                <span className="grid h-14 w-full place-items-center rounded bg-[repeating-conic-gradient(#e5e5e5_0_25%,#fff_0_50%)] bg-[length:16px_16px] p-2">
                                    <img src={l.url} alt="" className="max-h-10 max-w-full" />
                                </span>
                                {l.label}
                            </button>
                        ))}
                    </div>
                )}

                {(soort !== 'video' || tab === 'eigen') && (
                    <div className="space-y-3">
                        <UploadKnop soort={soort} maxUploadMb={maxUploadMb} onKlaar={(m) => {
                            onMediaErbij(m);
                            kies({ soort: 'media', id: m.id });
                        }} />
                        {eigen.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Nog niets in de mediatheek van deze soort.</p>
                        ) : (
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                {eigen.map((m) => (
                                    <button
                                        key={m.id}
                                        type="button"
                                        data-test="studio-media-kies"
                                        onClick={() => kies({ soort: 'media', id: m.id })}
                                        className="group overflow-hidden rounded-lg border text-left hover:border-violet"
                                        title={m.name}
                                    >
                                        {m.kind === 'video' ? (
                                            <video src={m.url} muted preload="metadata" className="aspect-square w-full bg-black object-cover" />
                                        ) : (
                                            <img src={m.url} alt="" loading="lazy" className="aspect-square w-full bg-muted object-cover" />
                                        )}
                                        <span className="block truncate px-2 py-1 text-xs">{m.name}</span>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {soort === 'video' && tab === 'creators' && (
                    <div className="space-y-3">
                        <RechtenWaarschuwing />
                        {creatorVideos.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Nog geen goedgekeurde video's uit samenwerkingen.</p>
                        ) : (
                            <ul className="space-y-2">
                                {creatorVideos.map((v) => (
                                    <li key={v.id} className="flex items-center gap-3 rounded-lg border p-2">
                                        <video src={v.url} muted preload="metadata" className="size-16 shrink-0 rounded bg-black object-cover" />
                                        <div className="min-w-0 flex-1 text-sm">
                                            <p className="truncate font-semibold">{v.titel ?? v.nummer}</p>
                                            <p className="truncate text-muted-foreground">
                                                {v.creator} bij {v.bedrijf}, {v.gebruiksrechten.length ? `${v.gebruiksrechten.length} gebruiksrecht(en)` : 'geen gebruiksrechten vastgelegd'}
                                            </p>
                                        </div>
                                        <button type="button" onClick={() => kies({ soort: 'creator', id: v.id })} className="h-10 shrink-0 rounded-lg border px-3 text-sm font-semibold hover:border-violet">
                                            Gebruiken
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
