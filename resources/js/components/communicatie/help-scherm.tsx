import { Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, LifeBuoy, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Kaart, Leeg, Pagina, TweeKolommen } from '@/components/pagina';
import { Button } from '@/components/ui/button';
import { datumTijd } from '@/lib/datum';
import { Artikeltekst, VraagStatus, tekstvak } from './opmaak';
import type { Artikel, VraagKort } from './types';

export type HelpProps = {
    zoek: string;
    articles: Artikel[];
    tickets: VraagKort[];
    collaborations: { id: number; number: string; title: string }[];
    priorities: Record<string, string>;
    termijn: string;
};

type Props = HelpProps & {
    kop: ReactNode;
    helpUrl: string;
    stellenUrl: string;
    vraagUrl: (id: number) => string;
    /** Extra blok boven de vragen, bijvoorbeeld de link naar de werkbak Support. */
    extra?: ReactNode;
    /** Per artikel een knop, bijvoorbeeld Bewerken voor het team. */
    artikelActie?: (a: Artikel) => ReactNode;
    zonderVragen?: boolean;
};

/** Helpcentrum: zoeken in de artikelen, een vraag stellen en je eigen vragen volgen. */
export function HelpScherm({
    zoek,
    articles,
    tickets,
    collaborations,
    priorities,
    termijn,
    kop,
    helpUrl,
    stellenUrl,
    vraagUrl,
    extra,
    artikelActie,
    zonderVragen = false,
}: Props) {
    const [term, setTerm] = useState(zoek);
    const [open, setOpen] = useState<number | null>(
        articles.length === 1 ? articles[0].id : null,
    );
    const form = useForm({
        subject: '',
        body: '',
        priority: 'normaal',
        collaboration_id: '',
    });

    const zoeken = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(helpUrl, term.trim() ? { zoek: term.trim() } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const groepen = articles.reduce<Record<string, Artikel[]>>((g, a) => {
        (g[a.category ?? 'Overig'] ??= []).push(a);

        return g;
    }, {});

    return (
        <Pagina>
            {kop}
            <form onSubmit={zoeken} role="search" className="flex gap-2">
                <label className="relative block flex-1">
                    <span className="sr-only">Zoek in de hulp</span>
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <input
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="Zoek in de hulp, bijvoorbeeld tegoed of wachtwoord"
                        className={`${tekstvak} h-12 pl-9`}
                    />
                </label>
                <Button
                    type="submit"
                    className="h-12 bg-violet px-5 hover:bg-violet/90"
                >
                    Zoeken
                </Button>
            </form>

            <TweeKolommen
                hoofd={
                    <section
                        aria-label="Veelgestelde vragen"
                        className="space-y-5"
                    >
                        {zoek && (
                            <p className="text-sm text-muted-foreground">
                                {articles.length}{' '}
                                {articles.length === 1
                                    ? 'artikel'
                                    : 'artikelen'}{' '}
                                voor "{zoek}".{' '}
                                <Link
                                    href={helpUrl}
                                    className="font-semibold text-violet underline"
                                >
                                    Wis zoekterm
                                </Link>
                            </p>
                        )}
                        {articles.length === 0 && (
                            <Leeg titel="Geen artikel gevonden">
                                Stel je vraag, dan helpen we je verder.
                            </Leeg>
                        )}
                        {Object.entries(groepen).map(([categorie, lijst]) => (
                            <div key={categorie} className="space-y-2">
                                <h2 className="font-heading text-base font-bold">
                                    {categorie}
                                </h2>
                                <ul
                                    className="divide-y rounded-xl border bg-card"
                                    data-test="artikelen"
                                >
                                    {lijst.map((a) => (
                                        <li key={a.id}>
                                            <button
                                                type="button"
                                                aria-expanded={open === a.id}
                                                onClick={() =>
                                                    setOpen(
                                                        open === a.id
                                                            ? null
                                                            : a.id,
                                                    )
                                                }
                                                className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left font-semibold"
                                            >
                                                {a.title}
                                                <ChevronDown
                                                    className={`size-5 shrink-0 text-muted-foreground transition ${open === a.id ? 'rotate-180' : ''}`}
                                                    aria-hidden
                                                />
                                            </button>
                                            {open === a.id && (
                                                <div className="space-y-3 px-4 pb-4">
                                                    <Artikeltekst
                                                        html={a.html}
                                                    />
                                                    {artikelActie?.(a)}
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </section>
                }
                zij={
                    <>
                        {extra}
                        {!zonderVragen && (
                            <>
                                <Kaart>
                                    <section aria-labelledby="vraag-stellen">
                                        <h2
                                            id="vraag-stellen"
                                            className="flex items-center gap-2 font-heading text-base font-bold"
                                        >
                                            <LifeBuoy
                                                className="size-5 text-violet"
                                                aria-hidden
                                            />{' '}
                                            Kom je er niet uit?
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Stel je vraag. {termijn}
                                        </p>
                                        <form
                                            className="mt-3 space-y-3"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                form.post(stellenUrl);
                                            }}
                                            data-test="vraag-formulier"
                                        >
                                            <div className="space-y-1.5">
                                                <label
                                                    htmlFor="v-subject"
                                                    className="text-sm font-semibold"
                                                >
                                                    Onderwerp
                                                </label>
                                                <input
                                                    id="v-subject"
                                                    required
                                                    maxLength={160}
                                                    value={form.data.subject}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'subject',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={tekstvak}
                                                />
                                                {form.errors.subject && (
                                                    <p className="text-sm text-rood">
                                                        {form.errors.subject}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="space-y-1.5">
                                                <label
                                                    htmlFor="v-body"
                                                    className="text-sm font-semibold"
                                                >
                                                    Je vraag
                                                </label>
                                                <textarea
                                                    id="v-body"
                                                    required
                                                    rows={4}
                                                    maxLength={5000}
                                                    value={form.data.body}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'body',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={tekstvak}
                                                />
                                                {form.errors.body && (
                                                    <p className="text-sm text-rood">
                                                        {form.errors.body}
                                                    </p>
                                                )}
                                            </div>
                                            {collaborations.length > 0 && (
                                                <div className="space-y-1.5">
                                                    <label
                                                        htmlFor="v-collab"
                                                        className="text-sm font-semibold"
                                                    >
                                                        Over welke samenwerking?
                                                        (optioneel)
                                                    </label>
                                                    <select
                                                        id="v-collab"
                                                        value={
                                                            form.data
                                                                .collaboration_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'collaboration_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={tekstvak}
                                                    >
                                                        <option value="">
                                                            Geen of weet ik niet
                                                        </option>
                                                        {collaborations.map(
                                                            (c) => (
                                                                <option
                                                                    key={c.id}
                                                                    value={c.id}
                                                                >
                                                                    {c.number}{' '}
                                                                    {c.title}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </div>
                                            )}
                                            <fieldset className="space-y-1.5">
                                                <legend className="text-sm font-semibold">
                                                    Hoe dringend?
                                                </legend>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {Object.entries(
                                                        priorities,
                                                    ).map(([key, naam]) => (
                                                        <label
                                                            key={key}
                                                            className="inline-flex min-h-10 cursor-pointer items-center rounded-full border px-3 py-1.5 text-sm font-semibold has-checked:border-violet has-checked:bg-violet has-checked:text-white"
                                                        >
                                                            <input
                                                                type="radio"
                                                                name="priority"
                                                                value={key}
                                                                checked={
                                                                    form.data
                                                                        .priority ===
                                                                    key
                                                                }
                                                                onChange={() =>
                                                                    form.setData(
                                                                        'priority',
                                                                        key,
                                                                    )
                                                                }
                                                                className="sr-only"
                                                            />
                                                            {naam}
                                                        </label>
                                                    ))}
                                                </div>
                                            </fieldset>
                                            <Button
                                                type="submit"
                                                disabled={form.processing}
                                                className="h-11 w-full bg-violet hover:bg-violet/90"
                                            >
                                                Vraag versturen
                                            </Button>
                                        </form>
                                    </section>
                                </Kaart>

                                <Kaart>
                                    <section aria-labelledby="mijn-vragen">
                                        <h2
                                            id="mijn-vragen"
                                            className="font-heading text-base font-bold"
                                        >
                                            Mijn vragen
                                        </h2>
                                        {tickets.length === 0 ? (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Je hebt nog geen vragen gesteld.
                                            </p>
                                        ) : (
                                            <ul
                                                className="mt-2 divide-y"
                                                data-test="mijn-vragen"
                                            >
                                                {tickets.map((t) => (
                                                    <li key={t.id}>
                                                        <Link
                                                            href={vraagUrl(
                                                                t.id,
                                                            )}
                                                            className="flex items-start justify-between gap-3 py-2.5 hover:text-violet"
                                                        >
                                                            <span className="min-w-0">
                                                                <span className="block truncate font-semibold">
                                                                    {t.subject}
                                                                </span>
                                                                <span className="block text-xs text-muted-foreground">
                                                                    {t.number} ·{' '}
                                                                    {datumTijd(
                                                                        t.updated_at,
                                                                    )}
                                                                </span>
                                                            </span>
                                                            <VraagStatus
                                                                vraag={t}
                                                            />
                                                        </Link>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </section>
                                </Kaart>
                            </>
                        )}
                    </>
                }
            />
        </Pagina>
    );
}
