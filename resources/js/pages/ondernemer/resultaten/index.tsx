import { Head, Link } from '@inertiajs/react';
import { ExternalLink, FileText, Film } from 'lucide-react';
import {
    Herkomst,
    MetingLijst,
    PlatformTegels,
    platformNaam,
} from '@/components/cijfers/cijfers';
import {
    Leeg,
    MeerLijst,
    Pagina,
    PaginaKop,
    Sectie,
    Uitklap,
} from '@/components/pagina';
import { Button } from '@/components/ui/button';
import { datum } from '@/lib/datum';
import {
    maandrapport,
    mediabibliotheek,
    resultaten,
    samenwerking,
} from '@/routes/ondernemer';
import type {
    Metrieken,
    PlatformTotaal,
    PublicatieCijfers,
} from '@/types/cijfers';

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
            <Pagina>
                <PaginaKop
                    titel="Resultaten"
                    omschrijving={`${company}. Cijfers van de posts die creators voor je maakten, per platform.`}
                    acties={
                        <>
                            <Button asChild variant="outline">
                                <Link href={maandrapport.url()}>
                                    <FileText /> Maandrapport
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={mediabibliotheek.url()}>
                                    <Film /> Mediabibliotheek
                                </Link>
                            </Button>
                        </>
                    }
                />

                {collaborations.length === 0 ? (
                    <Leeg titel="Nog geen resultaten">
                        Zodra een creator de video plaatst, verschijnen hier de
                        cijfers. We meten 14 dagen; cijfers van Instagram kunnen
                        tot 48 uur achterlopen.
                    </Leeg>
                ) : (
                    <>
                        <Sectie
                            titel="Totaal per platform"
                            omschrijving="Alle samenwerkingen samen. Platforms tellen views verschillend, daarom tellen we ze niet bij elkaar op. Je ziet alleen de cijfers van je eigen posts."
                        >
                            <PlatformTegels
                                platforms={platforms}
                                metrics={metrics}
                            />
                        </Sectie>

                        <Sectie
                            titel="Per samenwerking"
                            omschrijving="Klik een samenwerking open voor de cijfers per post."
                            actie={
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {collaborations.length}
                                </span>
                            }
                        >
                            <MeerLijst>
                                {collaborations.map((c) => (
                                    <li key={c.id} data-test="resultaat">
                                        <Uitklap
                                            titel={
                                                <>
                                                    <span className="block truncate">
                                                        {c.title}
                                                    </span>
                                                    <span className="block truncate text-sm font-normal text-muted-foreground">
                                                        {c.creator ?? 'Creator'}{' '}
                                                        · {c.location}
                                                        {c.live_at &&
                                                            ` · live sinds ${datum(c.live_at)}`}
                                                    </span>
                                                </>
                                            }
                                            rechts={
                                                <span className="hidden shrink-0 text-sm text-muted-foreground sm:inline">
                                                    {c.measuring &&
                                                    c.measure_until
                                                        ? `We meten tot ${datum(c.measure_until)}`
                                                        : 'Eindcijfers'}
                                                </span>
                                            }
                                        >
                                            <div className="space-y-3">
                                                <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                                    <span className="text-muted-foreground sm:hidden">
                                                        {c.measuring &&
                                                        c.measure_until
                                                            ? `We meten tot ${datum(c.measure_until)}`
                                                            : 'Eindcijfers'}
                                                    </span>
                                                    <Link
                                                        href={samenwerking.url(
                                                            c.id,
                                                        )}
                                                        className="font-semibold text-violet"
                                                    >
                                                        Open samenwerking
                                                    </Link>
                                                </div>
                                                {c.publications.map((p) => (
                                                    <div
                                                        key={p.id}
                                                        className="space-y-2 border-t pt-3"
                                                    >
                                                        <div className="flex flex-wrap items-center gap-2">
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
                                                                Post bekijken{' '}
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
                                                        <Herkomst p={p} />
                                                    </div>
                                                ))}
                                            </div>
                                        </Uitklap>
                                    </li>
                                ))}
                            </MeerLijst>
                        </Sectie>
                    </>
                )}
            </Pagina>
        </>
    );
}

Resultaten.layout = {
    breadcrumbs: [{ title: 'Resultaten', href: resultaten() }],
};
