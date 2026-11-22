import { Head } from '@inertiajs/react';
import { BarChart3, ListChecks, MapPin, Sparkles } from 'lucide-react';
import { RadarUitleg } from '@/components/content/radar-uitleg';
import { datum } from '@/lib/datum';
import content from '@/routes/team/content';
import { Leeg, Pagina, PaginaKop } from '@/components/pagina';
import { Paneel } from '../_onderdelen/basis';

/** AdminRadar: placeholder (fase 7, alleen na akkoord). Toont wie interesse toonde; elke interesse is een taak voor sales. */
export default function Radar({
    interest,
}: {
    interest: { id: number; company: string | null; at: string }[];
}) {
    return (
        <>
            <Head title="Radar: binnenkort" />
            <Pagina>
                <PaginaKop
                    titel="Radar: binnenkort"
                    omschrijving="Fase 7: wordt pas gebouwd na akkoord. Inhoud, prijzen en weging zijn nog niet besloten."
                />
                <RadarUitleg
                    onderdelen={[
                        {
                            icoon: BarChart3,
                            titel: 'Eén score per klant',
                            tekst: 'Google, Maps, AI en reviews, elk 25% (voorstel). Meting elke week.',
                        },
                        {
                            icoon: MapPin,
                            titel: 'Meetpunten per vestiging',
                            tekst: 'Zoektermen en een straal in Maps per vestiging.',
                        },
                        {
                            icoon: Sparkles,
                            titel: 'AI-vragen',
                            tekst: 'Elke vraag 3 keer gesteld aan ChatGPT, Gemini en Perplexity.',
                        },
                        {
                            icoon: ListChecks,
                            titel: 'Acties',
                            tekst: 'Hooguit 3 per week. Een daling van 3 punten of meer geeft een taak voor de accountmanager.',
                        },
                    ]}
                />
                <Paneel
                    titel="Interesse van ondernemers"
                    sub="Elke interesse staat ook als taak voor sales in de werkvoorraad."
                >
                    {interest.length === 0 ? (
                        <Leeg titel="Nog geen interesse binnengekomen." />
                    ) : (
                        <ul className="divide-y">
                            {interest.map((i) => (
                                <li
                                    key={i.id}
                                    className="flex justify-between gap-2 py-2 text-sm"
                                >
                                    <span className="font-semibold">
                                        {i.company}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {datum(i.at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Paneel>
            </Pagina>
        </>
    );
}

Radar.layout = { breadcrumbs: [{ title: 'Radar', href: content.radar.url() }] };
