import { Head, router } from '@inertiajs/react';
import { Kaart, Pagina, PaginaKop } from '@/components/pagina';
import {
    Check,
    MapPin,
    MessageSquareText,
    Search,
    Sparkles,
    Star,
} from 'lucide-react';
import { RadarUitleg } from '@/components/content/radar-uitleg';
import { Button } from '@/components/ui/button';
import content from '@/routes/ondernemer/content';

export default function Zichtbaarheid({
    company,
    interesse,
}: {
    company: { name: string };
    interesse: boolean;
}) {
    return (
        <>
            <Head title="Radar: binnenkort" />
            <Pagina>
                <PaginaKop
                    titel="Radar: binnenkort"
                    omschrijving={`Hoe nieuwe klanten ${company.name} vinden op Google, in Maps, bij AI en via reviews.`}
                />
                <RadarUitleg
                    onderdelen={[
                        {
                            icoon: Search,
                            titel: 'Google',
                            tekst: 'Op welke plek je staat bij de zoektermen van jouw klanten.',
                        },
                        {
                            icoon: MapPin,
                            titel: 'Maps',
                            tekst: 'Of je in de top 3 van de kaart staat, binnen een straal van 3 km.',
                        },
                        {
                            icoon: Sparkles,
                            titel: 'AI-zichtbaarheid',
                            tekst: 'Of ChatGPT en anderen jouw zaak noemen bij vragen uit je buurt.',
                        },
                        {
                            icoon: Star,
                            titel: 'Google-reviews',
                            tekst: 'Je gemiddelde, nieuwe reviews en welke nog een antwoord nodig hebben.',
                        },
                    ]}
                />
                <Kaart>
                    <section>
                        <h2 className="flex items-center gap-2 font-heading text-lg font-bold">
                            <MessageSquareText
                                className="size-5 text-violet"
                                aria-hidden
                            />
                            Interesse?
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Radar is nog in voorbereiding. Laat weten dat je
                            interesse hebt, dan neemt ons team contact met je op
                            zodra het zover is.
                        </p>
                        {interesse ? (
                            <p
                                role="status"
                                className="mt-3 inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-100"
                            >
                                <Check className="size-4" aria-hidden />
                                Je interesse is doorgegeven. We nemen contact
                                met je op.
                            </p>
                        ) : (
                            <Button
                                type="button"
                                className="mt-3 min-h-11 bg-violet hover:bg-violet/90"
                                onClick={() =>
                                    router.post(
                                        content.zichtbaarheid.interesse.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Interesse
                            </Button>
                        )}
                    </section>
                </Kaart>
            </Pagina>
        </>
    );
}

Zichtbaarheid.layout = {
    breadcrumbs: [
        { title: 'Zichtbaarheid', href: content.zichtbaarheid.url() },
    ],
};
