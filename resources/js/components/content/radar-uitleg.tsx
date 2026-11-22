import type { LucideIcon } from 'lucide-react';
import { Etiket } from './labels';

/** Uitleg van Radar uit het ontwerp (OndernemerZichtbaarheid, AdminRadar). Fase 7: alleen na akkoord, nog geen functionaliteit. */
export function RadarUitleg({
    onderdelen,
}: {
    onderdelen: { icoon: LucideIcon; titel: string; tekst: string }[];
}) {
    return (
        <section className="relative overflow-hidden rounded-2xl bg-aubergine-dark p-5 text-white md:p-8">
            <div
                aria-hidden
                className="absolute inset-0 bg-[radial-gradient(120%_90%_at_100%_0%,rgba(97,78,217,0.55),transparent_60%)]"
            />
            <div className="relative space-y-4">
                <Etiket kleur="amber">Binnenkort, werknaam</Etiket>
                <h2 className="font-heading text-2xl font-extrabold md:text-3xl">
                    Hoe vindbaar ben je?
                </h2>
                <p className="max-w-2xl text-white/85">
                    Radar meet elke week hoe makkelijk nieuwe klanten je vinden:
                    op Google, in Maps, bij AI-assistenten en via je reviews. Je
                    krijgt één score van 0 tot 100 en hooguit 3 acties per week
                    die echt verschil maken.
                </p>
                <ul className="grid gap-3 sm:grid-cols-2">
                    {onderdelen.map(({ icoon: Icoon, titel, tekst }) => (
                        <li
                            key={titel}
                            className="flex gap-3 rounded-xl bg-white/10 p-3"
                        >
                            <Icoon
                                className="mt-0.5 size-5 shrink-0 text-white"
                                aria-hidden
                            />
                            <span>
                                <span className="block font-semibold">
                                    {titel}
                                </span>
                                <span className="text-sm text-white/80">
                                    {tekst}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
                <p className="text-sm text-white/70">
                    AI-assistenten geven niet elke keer hetzelfde antwoord. Een
                    vermelding kan niemand garanderen, wel vergroten met goede
                    reviews en een duidelijk Google-profiel. Inhoud en prijs van
                    Radar zijn nog niet besloten.
                </p>
            </div>
        </section>
    );
}
