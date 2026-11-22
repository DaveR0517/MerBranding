import { router } from '@inertiajs/react';
import { Kaart, KaartRaster } from '@/components/pagina';
import { CalendarClock, Clapperboard } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { datum } from '@/lib/datum';
import { Etiket, Voorstel } from './labels';
import type { Recht } from './types';

export const soortNaam: Record<Recht['kind'], string> = {
    organisch: 'Organisch en website',
    advertentie: 'Advertenties',
};

/** Hoe dringend: verlopen, binnen 30 dagen of nog ruim. */
export function rechtStand(r: Recht): {
    naam: string;
    kleur: 'rood' | 'amber' | 'groen' | 'grijs';
} {
    if (r.status === 'ingetrokken') {
        return { naam: 'Ingetrokken', kleur: 'grijs' };
    }
    if (r.status === 'verlopen' || r.days_left < 0) {
        return { naam: 'Verlopen', kleur: 'rood' };
    }
    if (r.days_left <= 30) {
        return {
            naam:
                r.days_left === 0
                    ? 'Laatste dag'
                    : `Nog ${r.days_left} ${r.days_left === 1 ? 'dag' : 'dagen'}`,
            kleur: 'amber',
        };
    }

    return { naam: 'Loopt', kleur: 'groen' };
}

/** Rechten gegroepeerd per samenwerking (gebruiksrechten.html). */
export function RechtenLijst({
    rights,
    verlengUrl,
    actie,
}: {
    rights: Recht[];
    verlengUrl?: (id: number) => string;
    actie?: (r: Recht) => ReactNode;
}) {
    const groepen = Object.values(
        rights.reduce<Record<number, Recht[]>>((acc, r) => {
            (acc[r.collaboration.id] ??= []).push(r);

            return acc;
        }, {}),
    );

    return (
        <KaartRaster kolommen={2}>
            {groepen.map((groep) => {
                const c = groep[0].collaboration;

                return (
                    <Kaart key={c.id}>
                        <div data-test="recht-video">
                            <div className="flex items-start gap-3">
                                <span
                                    className="grid size-10 shrink-0 place-items-center rounded-lg bg-aubergine text-white"
                                    aria-hidden
                                >
                                    <Clapperboard className="size-5" />
                                </span>
                                <div className="min-w-0">
                                    <h3 className="font-heading font-bold break-words">
                                        {c.title}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {c.number}
                                        {c.creator ? `, ${c.creator}` : ''}
                                    </p>
                                </div>
                            </div>
                            <ul className="mt-3 divide-y">
                                {groep.map((r) => {
                                    const stand = rechtStand(r);

                                    return (
                                        <li
                                            key={r.id}
                                            className="flex flex-wrap items-center justify-between gap-2 py-2.5"
                                        >
                                            <div className="min-w-0">
                                                <p className="flex flex-wrap items-center gap-1.5 text-sm font-semibold">
                                                    {soortNaam[r.kind]}
                                                    {r.is_proposal && (
                                                        <Voorstel>
                                                            Standaardduur
                                                            VOORSTEL
                                                        </Voorstel>
                                                    )}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {datum(r.starts_on)} tot en
                                                    met {datum(r.ends_on)}
                                                    {r.ad_code
                                                        ? `, code ${r.ad_code}`
                                                        : ''}
                                                </p>
                                                {r.extension_requested_at && (
                                                    <p className="mt-0.5 flex items-center gap-1 text-xs text-violet">
                                                        <CalendarClock
                                                            className="size-3.5"
                                                            aria-hidden
                                                        />
                                                        Verlenging aangevraagd
                                                        op{' '}
                                                        {datum(
                                                            r.extension_requested_at,
                                                        )}
                                                        . Prijs nog niet
                                                        bepaald.
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Etiket kleur={stand.kleur}>
                                                    {stand.naam}
                                                </Etiket>
                                                {verlengUrl &&
                                                    r.status !==
                                                        'ingetrokken' &&
                                                    !r.extension_requested_at && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="max-md:min-h-10"
                                                            onClick={() =>
                                                                router.post(
                                                                    verlengUrl(
                                                                        r.id,
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                            aria-label={`${soortNaam[r.kind]} van ${c.title} verlengen`}
                                                        >
                                                            Verlengen
                                                        </Button>
                                                    )}
                                                {actie?.(r)}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    </Kaart>
                );
            })}
        </KaartRaster>
    );
}
