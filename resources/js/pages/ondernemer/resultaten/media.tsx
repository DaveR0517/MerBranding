import { Head } from "@inertiajs/react";
import { Download, Film } from "lucide-react";
import { useMemo, useState } from "react";
import { platformNaam } from "@/components/cijfers/cijfers";
import { Button } from "@/components/ui/button";
import { datum } from "@/lib/datum";
import { mediabibliotheek } from "@/routes/ondernemer";
import { Blok, Kop } from "../_onderdelen/opmaak";

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
    const [vestiging, setVestiging] = useState("");
    const [zoek, setZoek] = useState("");
    const vestigingen = useMemo(
        () => [...new Set(videos.map((v) => v.location))].sort(),
        [videos],
    );
    const zichtbaar = videos.filter(
        (v) =>
            (!vestiging || v.location === vestiging) &&
            (!zoek ||
                `${v.title} ${v.creator ?? ""}`
                    .toLowerCase()
                    .includes(zoek.toLowerCase())),
    );

    return (
        <>
            <Head title="Mediabibliotheek" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
                <Kop
                    onder={`Alle goedgekeurde video's van ${company} op één plek. Download het origineel, zonder watermerk.`}
                >
                    Mediabibliotheek
                </Kop>

                {videos.length === 0 ? (
                    <Blok className="py-10 text-center">
                        <Film
                            className="mx-auto size-8 text-violet"
                            aria-hidden
                        />
                        <h2 className="mt-2 font-heading text-xl font-bold">
                            Nog geen video's
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-muted-foreground">
                            Zodra het team een video goedkeurt, staat hij hier
                            klaar om te bekijken en te downloaden.
                        </p>
                    </Blok>
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

                        <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {zichtbaar.map((v) => (
                                <li
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
                                                    .join(" en ") ||
                                                    "Nog niet geplaatst"}
                                                {v.live_at && (
                                                    <>
                                                        {" "}
                                                        · live sinds{" "}
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
                                                        )}{" "}
                                                        MB)
                                                    </span>
                                                )}
                                            </a>
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                        <p className="text-xs text-muted-foreground">
                            Wat je met een video mag (eigen kanalen,
                            advertenties, hoe lang) staat per samenwerking in
                            het contract. De gebruiksrechten per pakket zijn nog
                            niet vastgesteld.
                        </p>
                    </>
                )}
            </div>
        </>
    );
}

Mediabibliotheek.layout = {
    breadcrumbs: [{ title: "Mediabibliotheek", href: mediabibliotheek() }],
};
