import { Coins } from 'lucide-react';
import type { ReactNode } from 'react';

/** Tegoed in punten: beschikbaar en (op het overzicht) gereserveerd. */
export function Tegoed({ beschikbaar, gereserveerd, children }: { beschikbaar: number; gereserveerd?: number; children?: ReactNode }) {
    return (
        <section className="rounded-xl bg-aubergine p-4 text-white md:p-6" aria-label="Tegoed">
            <div className="flex items-center gap-2 text-sm text-white/75">
                <Coins className="size-4" aria-hidden />
                Je tegoed
            </div>
            <dl className="mt-2 flex flex-wrap gap-x-8 gap-y-2">
                <div>
                    <dt className="text-xs text-white/70">Beschikbaar</dt>
                    <dd className="font-heading text-3xl font-extrabold" data-test="beschikbaar">
                        {beschikbaar} <span className="text-base font-semibold">{beschikbaar === 1 ? 'punt' : 'punten'}</span>
                    </dd>
                </div>
                {gereserveerd !== undefined && (
                    <div>
                        <dt className="text-xs text-white/70">Gereserveerd</dt>
                        <dd className="font-heading text-3xl font-extrabold text-white/85">
                            {gereserveerd} <span className="text-base font-semibold">{gereserveerd === 1 ? 'punt' : 'punten'}</span>
                        </dd>
                    </div>
                )}
            </dl>
            <p className="mt-2 text-sm text-white/75">Gereserveerd bij de aanvraag, verbruikt na het bezoek.</p>
            {children}
        </section>
    );
}
