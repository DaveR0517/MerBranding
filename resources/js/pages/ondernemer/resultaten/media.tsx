import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useMemo, useState } from 'react';
import { platformNaam } from '@/components/cijfers/cijfers';
import { KaartRaster, Leeg, Pagina, PaginaKop } from '@/components/pagina';
import { Button } from '@/components/ui/button';
import { datum } from '@/lib/datum';
import { mediabibliotheek } from '@/routes/ondernemer';

type Video = {
    id: number;
    title: string;
    number: string;
    collaboration_id: number;
    location: string;
    creator: string | null;
    platforms: string[];
    live_at: string | null;
    approved_at: string;
    size: number;
    url: string;
    download_url: string;
};

export default function Mediabibliotheek({
    company,
    videos,
}: {
    company: string;
    videos: Video[];
}) {
    const [vestiging, setVestiging] = useState('');
    const [zoek, setZoek] = useState('');
    const vestigingen = useMemo(
        () => [...new Set(videos.map((v) => v.location))].sort(),
        [videos],
    );
    const zichtbaar = videos.filter(
        (v) =>
            (!vestiging || v.location === vestiging) &&
            (!zoek ||
                `${v.title} ${v.creator ?? ''}`
                    .toLowerCase()
                    .includes(zoek.toLowerCase())),
    );

    return (
        <>
            <Head title="Mediabibliotheek" />
            <Pagina>
                <PaginaKop
                    titel="Mediabibliotheek"
                    omschrijving={`Alle goedgekeurde video's van ${company} op één plek. Download het origineel, zonder watermerk.`}
                />

                {videos.length === 0 ? (
                    <Leeg titel="Nog geen video's">
                        Zodra het team een video goedkeurt, staat hij hier klaar
                        om te bekijken en te downloaden.
                    </Leeg>
                ) : (
                    <>
                        <div className="flex flex-wrap items-end gap-3">
                            <label className="min-w-0 flex-1 text-sm sm:max-w-xs">
                                <span className="mb-1 block font-semibold">
                                    Zoeken
                                </span>
                                <input
                                    type="search"
                                    value={zoek}
                                    onChange={(e) => setZoek(e.target.value)}
                                    placeholder="Titel of creator"
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                />
                            </label>
                            {vestigingen.length > 1 && (
                                <label className="text-sm">
                                    <span className="mb-1 block font-semibold">
                                        Vestiging
                                    </span>
                                    <select
                                        value={vestiging}
                                        onChange={(e) =>
                                            setVestiging(e.target.value)
                                        }
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">
                                            Alle vestigingen
                                        </option>
                                        {vestigingen.map((v) => (
                                            <option key={v}>{v}</option>
                                        ))}
                                    </select>
                                </label>
                            )}
                            <p
                                className="text-sm text-muted-foreground"
                                aria-live="polite"
                            >
                                {zichtbaar.length} van {videos.length} video's
                            </p>
                        </div>

                        <KaartRaster kolommen={4}>
                            {zichtbaar.map((v) => (
                                <div
                                    key={v.id}
                                    className="overflow-hidden rounded-xl border bg-card"
                                    data-test="video"
                                >
                                    <div className="aspect-[9/16] max-h-[60vh] w-full bg-black">
                                        <video
                                            src={v.url}
                                            controls
                                            playsInline
                                            preload="metadata"
                                            className="size-full object-contain"
                                        >
                                            <track kind="captions" />
                                        </video>
                                    </div>
                                    <div className="space-y-2 p-3">
                                        <div>
                                            <h2 className="truncate font-semibold">
                                                {v.title}
                                            </h2>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {v.creator} · {v.location}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {v.platforms
                                                    .map(platformNaam)
                                                    .join(' en ') ||
                                                    'Nog niet geplaatst'}
                                                {v.live_at && (
                                                    <>
                                                        {' '}
                                                        · live sinds{' '}
                                                        {datum(v.live_at)}
                                                    </>
                                                )}
                                            </p>
                                        </div>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="w-full"
                                        >
                                            <a href={v.download_url}>
                                                <Download /> Origineel
                                                downloaden
                                                {v.size > 0 && (
                                                    <span className="text-xs text-muted-foreground">
                                                        (
                                                        {Math.max(
                                                            1,
                                                            Math.round(
                                                                v.size /
                                                                    1048576,
                                                            ),
                                                        )}{' '}
                                                        MB)
                                                    </span>
                                                )}
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </KaartRaster>
                        <p className="text-xs text-muted-foreground">
                            Wat je met een video mag (eigen kanalen,
                            advertenties, hoe lang) staat per samenwerking in
                            het contract. De gebruiksrechten per pakket zijn nog
                            niet vastgesteld.
                        </p>
                    </>
                )}
            </Pagina>
        </>
    );
}

Mediabibliotheek.layout = {
    breadcrumbs: [{ title: 'Mediabibliotheek', href: mediabibliotheek() }],
};
