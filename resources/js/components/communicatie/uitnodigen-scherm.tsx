import { Check, Copy, Mail, MessageCircle, Share2 } from 'lucide-react';
import { useState } from 'react';
import {
    Kaart,
    Kerncijfer,
    Pagina,
    PaginaKop,
    TweeKolommen,
} from '@/components/pagina';
import { Button } from '@/components/ui/button';

type Props = {
    omgeving: 'creator' | 'ondernemer';
    link: string;
    stats: { aangemeld: number; klant: number | null };
    /** Titel voor de paginakop; de omschrijving komt uit de tekst van de omgeving. Zonder titel toont de pagina zelf een kop. */
    titel?: string;
};

/**
 * Vriend uitnodigen (vriend-uitnodigen.html). De beloning is nog niet vastgesteld: alle bedragen zijn VOORSTEL en staan zo op het scherm.
 */
const teksten = {
    creator: {
        intro: "Ken je iemand die goede video's maakt? Nodig diegene uit met je persoonlijke link.",
        beloning: '€ 50',
        beloningSub: 'bonus per creator, voorbeeld',
        uitleg: 'Je krijgt de bonus zodra je vriend is goedgekeurd en de eerste opdracht heeft afgerond. Het bedrag is een voorbeeld en nog niet vastgesteld.',
        bericht:
            "Hoi! Ik maak video's via MEBRANDING voor lokale zaken in het Noorden. Leuk werk. Meld je aan via mijn link, dan help ik je op weg.",
        stappen: [
            ['Deel je link', 'Bijvoorbeeld in je story of bij een cursusdag.'],
            [
                'Je vriend meldt zich aan',
                'Het team beoordeelt de aanmelding en de proefvideo.',
            ],
            [
                'Eerste opdracht afgerond',
                'Dan krijg jij je bonus (voorbeeld, nog niet vastgesteld).',
            ],
        ],
    },
    ondernemer: {
        intro: 'Ken je een ondernemer die ook meer zichtbaar wil worden? Nodig hem of haar uit met je persoonlijke link.',
        beloning: '1 maand',
        beloningSub: 'gratis, voorbeeld',
        uitleg: 'Je collega komt bij ons binnen met jouw naam erbij en krijgt een gratis kennismaking. Jouw beloning is een voorbeeld en nog niet vastgesteld.',
        bericht:
            "Hoi! Wij werken met MEBRANDING: creators uit de buurt maken video's van onze zaak voor TikTok en Instagram. Scheelt mij veel tijd. Via mijn link krijg je een gratis kennismaking.",
        stappen: [
            ['Deel je link', 'Bijvoorbeeld bij de ondernemersvereniging.'],
            [
                'Wij nemen contact op',
                'Een gratis kennismaking voor die ondernemer.',
            ],
            [
                'Klant geworden',
                'Dan krijg jij je beloning (voorbeeld, nog niet vastgesteld).',
            ],
        ],
    },
} as const;

export function UitnodigenScherm({ omgeving, link, stats, titel }: Props) {
    const t = teksten[omgeving];
    const [bericht, setBericht] = useState<string>(t.bericht);
    const [gekopieerd, setGekopieerd] = useState(false);
    const volledig = `${bericht} ${link}`;

    const kopieer = async () => {
        try {
            await navigator.clipboard.writeText(link);
            setGekopieerd(true);
            setTimeout(() => setGekopieerd(false), 2500);
        } catch {
            setGekopieerd(false);
        }
    };

    const delen = async () => {
        if (navigator.share) {
            await navigator
                .share({ text: bericht, url: link })
                .catch(() => undefined);
        } else {
            await kopieer();
        }
    };

    const knop =
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border-2 bg-card px-3 text-sm font-semibold transition hover:border-violet';

    return (
        <Pagina>
            {titel && <PaginaKop titel={titel} omschrijving={t.intro} />}
            <TweeKolommen
                hoofd={
                    <>
                        {!titel && (
                            <p className="text-[15px] text-muted-foreground">
                                {t.intro}
                            </p>
                        )}

                        <Kaart>
                            <section
                                className="space-y-3"
                                aria-labelledby="link-kop"
                            >
                                <h2
                                    id="link-kop"
                                    className="font-heading text-base font-bold"
                                >
                                    Jouw persoonlijke link
                                </h2>
                                <div className="flex items-center gap-2 rounded-xl bg-muted p-2 pl-3">
                                    <span
                                        className="min-w-0 flex-1 truncate font-mono text-sm"
                                        data-test="uitnodigingslink"
                                    >
                                        {link.replace(/^https?:\/\//, '')}
                                    </span>
                                    <Button
                                        type="button"
                                        onClick={kopieer}
                                        className="shrink-0 bg-violet hover:bg-violet/90"
                                    >
                                        {gekopieerd ? (
                                            <Check
                                                className="size-4"
                                                aria-hidden
                                            />
                                        ) : (
                                            <Copy
                                                className="size-4"
                                                aria-hidden
                                            />
                                        )}
                                        {gekopieerd ? 'Gekopieerd' : 'Kopieer'}
                                    </Button>
                                </div>
                                <div className="grid grid-cols-3 gap-2">
                                    <a
                                        className={knop}
                                        href={`https://wa.me/?text=${encodeURIComponent(volledig)}`}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <MessageCircle
                                            className="size-4"
                                            aria-hidden
                                        />{' '}
                                        WhatsApp
                                    </a>
                                    <a
                                        className={knop}
                                        href={`mailto:?subject=${encodeURIComponent('Uitnodiging voor MEBRANDING')}&body=${encodeURIComponent(volledig)}`}
                                    >
                                        <Mail className="size-4" aria-hidden />{' '}
                                        E-mail
                                    </a>
                                    <button
                                        type="button"
                                        className={knop}
                                        onClick={delen}
                                    >
                                        <Share2
                                            className="size-4"
                                            aria-hidden
                                        />{' '}
                                        Meer
                                    </button>
                                </div>
                                <span role="status" className="sr-only">
                                    {gekopieerd ? 'De link is gekopieerd.' : ''}
                                </span>
                            </section>
                        </Kaart>

                        <Kaart>
                            <section className="space-y-2">
                                <label
                                    htmlFor="voorbeeldbericht"
                                    className="font-heading text-base font-bold"
                                >
                                    Voorbeeldbericht
                                </label>
                                <p className="text-sm text-muted-foreground">
                                    Pas het aan zoals jij het zou zeggen. De
                                    link komt er vanzelf achter.
                                </p>
                                <textarea
                                    id="voorbeeldbericht"
                                    rows={4}
                                    value={bericht}
                                    onChange={(e) => setBericht(e.target.value)}
                                    className="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-base md:text-sm"
                                />
                            </section>
                        </Kaart>

                        <Kaart>
                            <section>
                                <h2 className="font-heading text-base font-bold">
                                    Zo werkt het
                                </h2>
                                <ol className="mt-3 space-y-3">
                                    {t.stappen.map(([kop, sub], i) => (
                                        <li key={kop} className="flex gap-3">
                                            <span className="grid size-8 shrink-0 place-items-center rounded-full bg-brand-gradient text-sm font-bold text-white">
                                                {i + 1}
                                            </span>
                                            <span>
                                                <span className="block font-semibold">
                                                    {kop}
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {sub}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            </section>
                        </Kaart>
                    </>
                }
                zij={
                    <>
                        <Kaart nadruk>
                            <p className="text-xs font-semibold tracking-wide text-white/75 uppercase">
                                Beloning (voorstel)
                            </p>
                            <p className="mt-1 font-heading text-3xl font-extrabold">
                                {t.beloning}
                            </p>
                            <p className="text-sm text-white/80">
                                {t.beloningSub}
                            </p>
                            <p className="mt-3 text-sm text-white/80">
                                {t.uitleg}
                            </p>
                        </Kaart>
                        <div
                            className={`grid gap-3 ${stats.klant !== null ? 'grid-cols-2' : ''}`}
                        >
                            <Kerncijfer
                                label="Aangemeld"
                                waarde={
                                    <span data-test="aangemeld">
                                        {stats.aangemeld}
                                    </span>
                                }
                            />
                            {stats.klant !== null && (
                                <Kerncijfer
                                    label="Klant"
                                    waarde={stats.klant}
                                />
                            )}
                        </div>
                    </>
                }
            />
        </Pagina>
    );
}
