import { Head, Link } from "@inertiajs/react";
import { BarChart3, ExternalLink, FileText, Film } from "lucide-react";
import {
    Herkomst,
    MetingLijst,
    PlatformTegels,
    platformNaam,
} from "@/components/cijfers/cijfers";
import { Button } from "@/components/ui/button";
import { datum } from "@/lib/datum";
import {
    maandrapport,
    mediabibliotheek,
    resultaten,
    samenwerking,
} from "@/routes/ondernemer";
import type {
    Metrieken,
    PlatformTotaal,
    PublicatieCijfers,
} from "@/types/cijfers";
import { Blok, BlokKop, Kop } from "../_onderdelen/opmaak";

type Props = {
    company: string;
    platforms: PlatformTotaal[];
    metrics: Metrieken;
    collaborations: {
        id: number;
        title: string;
        number: string;
        location: string;
        creator: string | null;
        status: string;
        live_at: string | null;
        measure_until: string | null;
        measuring: boolean;
        publications: PublicatieCijfers[];
    }[];
};

export default function Resultaten({
    company,
    platforms,
    metrics,
    collaborations,
}: Props) {
    return (
        <>
            <Head title="Resultaten" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Kop
                        onder={`${company}. Cijfers van de posts die creators voor je maakten, per platform.`}
                    >
                        Resultaten
                    </Kop>
                    <div className="flex w-full flex-wrap gap-2 sm:w-auto">
                        <Button
                            asChild
                            variant="outline"
                            className="flex-1 sm:flex-none"
                        >
                            <Link href={maandrapport.url()}>
                                <FileText /> Maandrapport
                            </Link>
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            className="flex-1 sm:flex-none"
                        >
                            <Link href={mediabibliotheek.url()}>
                                <Film /> Mediabibliotheek
                            </Link>
                        </Button>
                    </div>
                </div>

                {collaborations.length === 0 ? (
                    <Blok className="py-10 text-center">
                        <BarChart3
                            className="mx-auto size-8 text-violet"
                            aria-hidden
                        />
                        <h2 className="mt-2 font-heading text-xl font-bold">
                            Nog geen resultaten
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-muted-foreground">
                            Zodra een creator de video plaatst, verschijnen hier
                            de cijfers. We meten 14 dagen; cijfers van Instagram
                            kunnen tot 48 uur achterlopen.
                        </p>
                    </Blok>
                ) : (
                    <>
                        <section aria-labelledby="totaal" className="space-y-2">
                            <h2
                                id="totaal"
                                className="font-heading text-lg font-bold"
                            >
                                Totaal per platform
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Alle samenwerkingen samen, per platform.
                                Platforms tellen views verschillend, daarom
                                tellen we ze niet bij elkaar op. Je ziet alleen
                                de cijfers van je eigen posts, niet van het hele
                                account van de creator.
                            </p>
                            <PlatformTegels
                                platforms={platforms}
                                metrics={metrics}
                            />
                        </section>

                        <section aria-labelledby="per" className="space-y-3">
                            <h2
                                id="per"
                                className="font-heading text-lg font-bold"
                            >
                                Per samenwerking
                            </h2>
                            {collaborations.map((c) => (
                                <Blok key={c.id} data-test="resultaat">
                                    <BlokKop
                                        rechts={
                                            <span className="text-sm text-muted-foreground">
                                                {c.measuring && c.measure_until
                                                    ? `We meten tot ${datum(c.measure_until)}`
                                                    : "Eindcijfers"}
                                            </span>
                                        }
                                    >
                                        <Link
                                            href={samenwerking.url(c.id)}
                                            className="hover:text-violet"
                                        >
                                            {c.title}
                                        </Link>
                                    </BlokKop>
                                    <p className="-mt-2 mb-3 text-sm text-muted-foreground">
                                        {c.creator ?? "Creator"} · {c.location}
                                        {c.live_at && (
                                            <>
                                                {" "}
                                                · live sinds {datum(c.live_at)}
                                            </>
                                        )}
                                    </p>
                                    <div className="space-y-3">
                                        {c.publications.map((p) => (
                                            <div
                                                key={p.id}
                                                className="rounded-xl bg-muted/40 p-3"
                                            >
                                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                                    <span className="font-heading font-bold">
                                                        {platformNaam(
                                                            p.platform,
                                                        )}
                                                    </span>
                                                    <a
                                                        href={p.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1 text-sm text-violet"
                                                    >
                                                        Post bekijken{" "}
                                                        <ExternalLink
                                                            className="size-3.5"
                                                            aria-hidden
                                                        />
                                                    </a>
                                                </div>
                                                <MetingLijst
                                                    p={p}
                                                    metrics={metrics}
                                                />
                                                <div className="mt-2">
                                                    <Herkomst p={p} />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </Blok>
                            ))}
                        </section>
                    </>
                )}
            </div>
        </>
    );
}

Resultaten.layout = {
    breadcrumbs: [{ title: "Resultaten", href: resultaten() }],
};
