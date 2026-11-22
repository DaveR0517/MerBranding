import { Head, Link } from '@inertiajs/react';
import { CalendarClock, ChevronRight, Eye, Plus } from 'lucide-react';
import {
    KaartRaster,
    Leeg,
    MeerLijst,
    Pagina,
    PaginaKop,
    Rij,
    Sectie,
    Uitklap,
} from '@/components/pagina';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { Button } from '@/components/ui/button';
import { datum, datumTijd } from '@/lib/datum';
import { aanvragen, overzicht, samenwerking } from '@/routes/ondernemer';
import type { SamenwerkingKort } from '@/types/samenwerking';
import { Tegoed } from './_onderdelen/tegoed';

type Props = {
    company: {
        name: string;
        available_points: number;
        reserved_points: number;
    };
    collaborations: SamenwerkingKort[];
};

const afgesloten = ['afgerond', 'geannuleerd', 'niet_verschenen'];

function uur(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleTimeString('nl-NL', {
              timeZone: 'Europe/Amsterdam',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '';
}

/** Wat er onder "Bezoek" staat: het moment, of waar de samenwerking op wacht. */
function bezoekTekst(c: SamenwerkingKort): string {
    if (c.visit_at) {
        return datumTijd(c.visit_at);
    }
    if (c.status.value === 'creator_gevonden') {
        return 'Creator kiest een tijdvak';
    }

    return afgesloten.includes(c.status.value)
        ? 'Geen bezoek'
        : 'Nog niet gepland';
}

export default function Overzicht({ company, collaborations }: Props) {
    const lopend = collaborations.filter(
        (c) => !afgesloten.includes(c.status.value),
    );
    const klaar = collaborations.filter((c) =>
        afgesloten.includes(c.status.value),
    );
    const terControle = collaborations.filter(
        (c) => c.status.value === 'ter_controle',
    );
    const bezoeken = collaborations
        .filter((c) => c.status.value === 'bezoek_gepland' && c.visit_at)
        .sort((a, b) => (a.visit_at ?? '').localeCompare(b.visit_at ?? ''))
        .slice(0, 2);

    return (
        <>
            <Head title="Overzicht" />
            <Pagina>
                <PaginaKop
                    titel="Overzicht"
                    omschrijving={company.name}
                    acties={
                        <Button asChild size="lg">
                            <Link href={aanvragen()} data-test="aanvragen">
                                <Plus /> Samenwerking aanvragen
                            </Link>
                        </Button>
                    }
                />

                <KaartRaster kolommen={3}>
                    <Tegoed
                        beschikbaar={company.available_points}
                        gereserveerd={company.reserved_points}
                    />
                    {terControle.map((c) => (
                        <Actie
                            key={c.id}
                            id={c.id}
                            icoon={
                                <Eye
                                    className="size-5 text-violet"
                                    aria-hidden
                                />
                            }
                            titel="Video ter controle"
                            onder={`${c.creator ?? 'Creator'} voor ${c.location}`}
                            label="Meekijken"
                        />
                    ))}
                    {bezoeken.map((c) => (
                        <Actie
                            key={c.id}
                            id={c.id}
                            icoon={
                                <CalendarClock
                                    className="size-5 text-rood"
                                    aria-hidden
                                />
                            }
                            titel={`Bezoek ${datum(c.visit_at)}`}
                            onder={`${c.creator} bij ${c.location}, ${uur(c.visit_at)}`}
                            label="Details"
                        />
                    ))}
                </KaartRaster>

                {collaborations.length === 0 ? (
                    <Leeg
                        titel="Nog geen samenwerkingen"
                        actie={
                            <Button asChild size="lg">
                                <Link href={aanvragen()}>
                                    <Plus /> Eerste samenwerking aanvragen
                                </Link>
                            </Button>
                        }
                    >
                        Je tegoed staat klaar. Vraag je eerste samenwerking aan:
                        jij vertelt wat je wilt laten zien, wij zoeken de
                        creator die bij je zaak past. De creator komt langs,
                        maakt een video en wij controleren hem voordat hij
                        online gaat.
                    </Leeg>
                ) : (
                    <>
                        <Sectie
                            titel="Lopende samenwerkingen"
                            actie={
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {lopend.length}
                                </span>
                            }
                        >
                            {lopend.length === 0 ? (
                                <Leeg titel="Er loopt nu niets">
                                    Vraag een nieuwe samenwerking aan.
                                </Leeg>
                            ) : (
                                <Lijst items={lopend} />
                            )}
                        </Sectie>
                        {klaar.length > 0 && (
                            <Uitklap
                                titel="Afgerond en gestopt"
                                rechts={
                                    <span className="text-sm text-muted-foreground tabular-nums">
                                        {klaar.length}
                                    </span>
                                }
                            >
                                <Lijst items={klaar} />
                            </Uitklap>
                        )}
                    </>
                )}
            </Pagina>
        </>
    );
}

/** Een blok dat om actie vraagt: in hetzelfde raster als het tegoed, even hoog. */
function Actie({
    id,
    icoon,
    titel,
    onder,
    label,
}: {
    id: number;
    icoon: React.ReactNode;
    titel: string;
    onder: string;
    label: string;
}) {
    return (
        <Link
            href={samenwerking(id)}
            className="flex h-full items-center gap-3 rounded-xl border border-l-4 border-l-violet bg-card p-4 transition hover:border-violet md:p-5"
        >
            <span className="shrink-0">{icoon}</span>
            <span className="min-w-0 flex-1">
                <span className="block text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Actie nodig
                </span>
                <span className="block font-semibold">{titel}</span>
                <span className="block text-sm text-muted-foreground">
                    {onder}
                </span>
            </span>
            <span className="shrink-0 font-semibold text-violet">{label}</span>
        </Link>
    );
}

/** Samenwerkingen als compacte rijen: op een groot scherm twee naast elkaar, eerst 8. */
function Lijst({ items }: { items: SamenwerkingKort[] }) {
    return (
        <MeerLijst className="grid grid-cols-1 gap-2 space-y-0 lg:grid-cols-2">
            {items.map((c) => (
                <li key={c.id}>
                    <Link
                        href={samenwerking(c.id)}
                        data-test="samenwerking"
                        className="block rounded-xl transition hover:ring-2 hover:ring-violet/30"
                    >
                        <Rij
                            titel={c.title}
                            onder={`${c.creator ?? 'Nog geen creator'} · ${c.location} · ${c.number}`}
                            rechts={
                                <>
                                    <span className="flex flex-col items-end gap-0.5">
                                        <StatusBadge status={c.status} />
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {bezoekTekst(c)}
                                        </span>
                                    </span>
                                    <ChevronRight
                                        className="size-4 text-muted-foreground"
                                        aria-hidden
                                    />
                                </>
                            }
                        />
                    </Link>
                </li>
            ))}
        </MeerLijst>
    );
}

Overzicht.layout = { breadcrumbs: [{ title: 'Overzicht', href: overzicht() }] };
