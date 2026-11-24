import { Coins } from 'lucide-react';
import type { ReactNode } from 'react';
import { Kaart } from '@/components/pagina';

function punten(n: number): string {
    return n === 1 ? 'punt' : 'punten';
}

/** Tegoed in punten: beschikbaar en (op het overzicht) gereserveerd. De ene donkere merkkaart van het scherm. */
export function Tegoed({
    beschikbaar,
    gereserveerd,
    onbeperkt = false,
    children,
}: {
    beschikbaar: number;
    gereserveerd?: number;
    /** Eigen zaak zonder puntenlimiet: geen saldo, wel verbruik in het overzicht. */
    onbeperkt?: boolean;
    children?: ReactNode;
}) {
    return (
        <Kaart nadruk>
            <section aria-label="Tegoed">
                <p className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-white/75 uppercase">
                    <Coins className="size-3.5" aria-hidden />
                    Je tegoed
                </p>
                <dl className="mt-2 grid grid-cols-2 gap-3">
                    <div>
                        <dt className="text-xs text-white/70">Beschikbaar</dt>
                        <dd
                            className="font-heading text-2xl font-extrabold tabular-nums"
                            data-test="beschikbaar"
                        >
                            {onbeperkt ? (
                                'Onbeperkt'
                            ) : (
                                <>
                                    {beschikbaar}{' '}
                                    <span className="text-sm font-semibold">
                                        {punten(beschikbaar)}
                                    </span>
                                </>
                            )}
                        </dd>
                    </div>
                    {gereserveerd !== undefined && (
                        <div>
                            <dt className="text-xs text-white/70">
                                Gereserveerd
                            </dt>
                            <dd className="font-heading text-2xl font-extrabold text-white/85 tabular-nums">
                                {gereserveerd}{' '}
                                <span className="text-sm font-semibold">
                                    {punten(gereserveerd)}
                                </span>
                            </dd>
                        </div>
                    )}
                </dl>
                <p className="mt-2 text-xs text-white/75">
                    {onbeperkt
                        ? 'Eigen zaak van MEBRANDING: aanvragen zonder puntenlimiet. Het verbruik houden we wel bij.'
                        : 'Gereserveerd bij de aanvraag, verbruikt na het bezoek.'}
                </p>
                {children}
            </section>
        </Kaart>
    );
}
