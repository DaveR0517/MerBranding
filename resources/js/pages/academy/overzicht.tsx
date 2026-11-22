import { Head, Link, router } from '@inertiajs/react';
import {
    Award,
    BookOpen,
    CalendarDays,
    Check,
    ChevronRight,
    CircleDashed,
    ClipboardCheck,
    MapPin,
} from 'lucide-react';
import {
    Kaart,
    Leeg,
    MeerLijst,
    Pagina,
    PaginaKop,
    Rij,
    Sectie,
    TweeKolommen,
} from '@/components/pagina';
import { datumTijd } from '@/lib/datum';
import {
    cursusdag as cursusdagRoute,
    certificaat as certificaatRoute,
    examen as examenRoute,
    les as lesRoute,
    opdracht as opdrachtRoute,
    wordCreator,
} from '@/routes/academy';
import { afmelden, inchecken } from '@/routes/academy/inschrijving';
import { AanZet, groteKnop, rustigeKnop } from '../creator/_onderdelen/opmaak';
import {
    DagRegel,
    Plekken,
    Prijs,
    dagDatum,
    gemiddeld,
} from './_onderdelen/onderdelen';
import type { Dag, Inschrijving } from './_onderdelen/types';

type Props = {
    name: string;
    enrollments: Inschrijving[];
    course_days: Dag[];
    lessons: {
        id: number;
        title: string;
        minutes: number | null;
        done: boolean;
    }[];
    assignments: {
        id: number;
        title: string;
        submitted: boolean;
        assessed: boolean;
    }[];
    creator: { status: string; level: string } | null;
    can_become_creator: boolean;
};

function dagenTot(iso: string): number {
    const doel = new Date(`${iso}T00:00:00`).getTime();
    const nu = new Date(new Date().toDateString()).getTime();

    return Math.round((doel - nu) / 86400000);
}

function Ticket({ e }: { e: Inschrijving }) {
    const nog = dagenTot(e.day.date);

    return (
        <Kaart className="border-violet">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="font-heading text-lg font-bold">Je ticket</h2>
                <span className="text-sm text-muted-foreground">
                    {nog > 0
                        ? `nog ${nog} ${nog === 1 ? 'dag' : 'dagen'}`
                        : 'vandaag'}
                </span>
            </div>
            <div className="mt-3 grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                <div className="min-w-0 rounded-xl bg-aubergine-dark p-4 text-white">
                    <p className="text-sm font-semibold text-white/75">
                        Cursusdag
                    </p>
                    <p className="mt-1 font-heading text-xl font-extrabold first-letter:uppercase">
                        {dagDatum(e.day.date)}
                    </p>
                    <p className="text-sm">
                        {e.day.starts_at} - {e.day.ends_at}
                    </p>
                    <p className="mt-2 text-sm">
                        {e.day.location_name}
                        {e.day.address ? `, ${e.day.address}` : ''},{' '}
                        {e.day.city}
                    </p>
                    {e.day.teacher_name && (
                        <p className="text-sm text-white/80">
                            Docent: {e.day.teacher_name}
                        </p>
                    )}
                </div>
                {e.qr_svg && (
                    <figure className="mx-auto text-center">
                        <div
                            className="size-40 overflow-hidden rounded-xl border bg-white p-1 [&_svg]:size-full"
                            aria-label="QR-code van je ticket"
                            role="img"
                            dangerouslySetInnerHTML={{ __html: e.qr_svg }}
                        />
                        <figcaption className="mt-1 max-w-40 text-xs text-muted-foreground">
                            Laat deze QR-code zien bij binnenkomst.
                        </figcaption>
                    </figure>
                )}
            </div>

            <p className="mt-3 text-sm">
                {e.paid ? (
                    <span className="font-semibold text-emerald-700 dark:text-emerald-400">
                        Betaald
                    </span>
                ) : (
                    <>
                        <span className="font-semibold text-amber-700 dark:text-amber-400">
                            Nog te betalen
                        </span>
                        {e.offer_expires_at && (
                            <> voor {datumTijd(e.offer_expires_at)}</>
                        )}
                        . Zodra je betaald hebt, zet het team dit op betaald.
                    </>
                )}
            </p>

            {e.can_check_in && (
                <button
                    type="button"
                    className={`${groteKnop} mt-4`}
                    onClick={() =>
                        router.post(
                            inchecken.url(e.id),
                            {},
                            { preserveScroll: true },
                        )
                    }
                    data-test="ik-ben-er"
                >
                    <Check className="size-5" aria-hidden /> Ik ben er
                </button>
            )}
            {e.day.notes && (
                <div className="mt-4 border-t pt-3">
                    <h3 className="font-semibold">Wat neem je mee</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {e.day.notes}
                    </p>
                </div>
            )}
            <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <a
                    className="inline-flex min-h-10 items-center font-semibold text-violet underline-offset-2 hover:underline"
                    target="_blank"
                    rel="noreferrer"
                    href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${e.day.location_name} ${e.day.address ?? ''} ${e.day.city}`)}`}
                >
                    Route
                </a>
                <Link
                    className="inline-flex min-h-10 items-center font-semibold text-violet underline-offset-2 hover:underline"
                    href={cursusdagRoute.url(e.day.id)}
                >
                    Programma
                </Link>
                {e.can_cancel && <Afmelden id={e.id} />}
            </div>
        </Kaart>
    );
}

function Afmelden({ id }: { id: number }) {
    return (
        <button
            type="button"
            className="inline-flex min-h-10 items-center font-semibold text-rood underline-offset-2 hover:underline"
            onClick={() =>
                confirm(
                    'Weet je zeker dat je je afmeldt? Je plek gaat naar de wachtlijst.',
                ) && router.post(afmelden.url(id), {}, { preserveScroll: true })
            }
        >
            Afmelden
        </button>
    );
}

function Status({ e }: { e: Inschrijving }) {
    if (e.status === 'wachtlijst') {
        return (
            <Kaart>
                <AanZet>Op de wachtlijst, plek {e.place}</AanZet>
                <h2 className="font-heading text-lg font-bold">
                    {e.day.title} is vol
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    <DagRegel dag={e.day} />
                </p>
                <p className="mt-2 text-sm">
                    Je betaalt nog niets. Komt er een plek vrij, dan krijg je
                    direct bericht.
                </p>
                <div className="mt-3 text-sm">
                    <Afmelden id={e.id} />
                </div>
            </Kaart>
        );
    }

    if (e.status === 'ingeschreven') {
        return <Ticket e={e} />;
    }

    if (e.status === 'afwezig') {
        return (
            <Kaart>
                <AanZet>Niet verschenen</AanZet>
                <p className="text-sm">
                    Je was er niet op {dagDatum(e.day.date)} in {e.day.city}.
                    Daarom is er geen certificaat. Schrijf je in voor een
                    volgende dag hieronder.
                </p>
            </Kaart>
        );
    }

    return (
        <>
            {e.checked_in_at && !e.certificate && (
                <Kaart className="border-violet">
                    <AanZet>Aanwezig {datumTijd(e.checked_in_at)}</AanZet>
                    <p className="text-sm">
                        Bedankt voor je komst naar {e.day.location_name}.
                        {e.day.exam_required &&
                            !e.exam &&
                            ' Na het afsluiten van de dag staat je praktijkexamen klaar.'}
                    </p>
                    {e.exam && <Examenstap e={e} />}
                </Kaart>
            )}
            {e.certificate && (
                <Kaart className="border-violet">
                    <AanZet>
                        Certificaat behaald {datumTijd(e.certificate.issued_at)}
                    </AanZet>
                    <p className="flex items-center gap-2 font-heading text-xl font-extrabold break-all">
                        <Award
                            className="size-6 shrink-0 text-violet"
                            aria-hidden
                        />{' '}
                        {e.certificate.number}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {e.exam?.average != null && (
                            <>
                                Praktijkexamen voldoende, gemiddeld{' '}
                                {gemiddeld(e.exam.average)}.{' '}
                            </>
                        )}
                        Controlelink:{' '}
                        <a
                            className="break-all text-violet underline"
                            href={e.certificate.check_url}
                        >
                            {e.certificate.check_url}
                        </a>
                    </p>
                    <span className="mt-2 inline-flex rounded-full bg-violet/10 px-2.5 py-0.5 text-xs font-bold text-violet">
                        Academy-gecertificeerd
                    </span>
                    <a
                        className={`${rustigeKnop} mt-4`}
                        href={certificaatRoute.url(e.certificate.id)}
                    >
                        Certificaat bekijken en downloaden
                    </a>
                </Kaart>
            )}
        </>
    );
}

function Examenstap({ e }: { e: Inschrijving }) {
    const x = e.exam!;
    const tekst: Record<string, string> = {
        open: `Lever je praktijkexamen in voor ${datumTijd(x.due_at)}.`,
        ingeleverd:
            'Je examen ligt bij je docent. Je krijgt een melding zodra de beoordeling klaar is.',
        verbeteren: `Bijna. Lever je herkansing in voor ${datumTijd(x.due_at)}.`,
        onvoldoende:
            'Het praktijkexamen is niet gehaald. Je krijgt een deelnamebewijs (voorstel).',
        voldoende: 'Voldoende.',
    };

    return (
        <div className="mt-3 space-y-3">
            <p className="text-sm font-semibold">{tekst[x.status]}</p>
            <Link
                className={
                    x.status === 'open' || x.status === 'verbeteren'
                        ? groteKnop
                        : rustigeKnop
                }
                href={examenRoute.url(x.id)}
            >
                {x.status === 'open'
                    ? 'Praktijkexamen inleveren'
                    : x.status === 'verbeteren'
                      ? 'Herkansing inleveren'
                      : 'Bekijk je examen'}
            </Link>
        </div>
    );
}

export default function Overzicht({
    name,
    enrollments,
    course_days,
    lessons,
    assignments,
    creator,
    can_become_creator,
}: Props) {
    const voornaam = name.split(' ')[0];
    const volgende = lessons.find((l) => !l.done);

    const hoofd = (
        <>
            {enrollments.length === 0 && (
                <Leeg titel="Nog niet ingeschreven">
                    Kies een cursusdag uit de lijst.
                </Leeg>
            )}
            {enrollments.map((e) => (
                <Status key={e.id} e={e} />
            ))}

            {can_become_creator && (
                <Kaart className="border-violet">
                    <AanZet>Word creator</AanZet>
                    <h2 className="font-heading text-lg font-bold">
                        Je creatorprofiel staat al klaar
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Naam, contact, woonplaats en kanalen uit je inschrijving
                        nemen we over. Je praktijkexamen telt als voorbeeldwerk.
                        Daarna screenen we je profiel.
                    </p>
                    <button
                        type="button"
                        className={`${groteKnop} mt-4`}
                        onClick={() =>
                            router.post(
                                wordCreator.url(),
                                {},
                                { preserveScroll: true },
                            )
                        }
                        data-test="word-creator"
                    >
                        Word creator
                    </button>
                </Kaart>
            )}
            {creator && (
                <Kaart>
                    <AanZet>Word creator</AanZet>
                    <p className="text-sm">
                        {creator.status === 'screening'
                            ? 'Je aanmelding is binnen en ligt bij het team voor screening. Je start op Bronze met het label Academy-gecertificeerd.'
                            : `Je bent creator (${creator.level}).`}
                    </p>
                </Kaart>
            )}

            <Sectie
                titel={
                    <span className="flex items-center gap-2">
                        <BookOpen className="size-5 text-violet" aria-hidden />{' '}
                        Online leeromgeving
                    </span>
                }
                omschrijving="Optioneel: korte lessen om je voor te bereiden of verder te oefenen."
            >
                {lessons.length === 0 ? (
                    <Leeg titel="Nog geen lessen" />
                ) : (
                    <MeerLijst>
                        {lessons.map((l, i) => (
                            <li key={l.id}>
                                <Link
                                    href={lesRoute.url(l.id)}
                                    className="block"
                                >
                                    <Rij
                                        className="transition hover:border-violet"
                                        links={
                                            l.done ? (
                                                <Check
                                                    className="size-5 text-emerald-600"
                                                    aria-label="Afgerond"
                                                />
                                            ) : (
                                                <CircleDashed
                                                    className="size-5 text-muted-foreground"
                                                    aria-label="Nog niet gestart"
                                                />
                                            )
                                        }
                                        titel={`${i + 1}. ${l.title}`}
                                        rechts={
                                            <>
                                                {l.minutes && (
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {l.minutes} min
                                                    </span>
                                                )}
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
                )}
                {volgende && (
                    <Link
                        className={rustigeKnop}
                        href={lesRoute.url(volgende.id)}
                    >
                        Ga verder met les {lessons.indexOf(volgende) + 1}
                    </Link>
                )}
            </Sectie>

            {assignments.length > 0 && (
                <Sectie
                    titel={
                        <span className="flex items-center gap-2">
                            <ClipboardCheck
                                className="size-5 text-violet"
                                aria-hidden
                            />{' '}
                            Oefenopdrachten
                        </span>
                    }
                >
                    <MeerLijst>
                        {assignments.map((a) => (
                            <li key={a.id}>
                                <Link
                                    href={opdrachtRoute.url(a.id)}
                                    className="block"
                                >
                                    <Rij
                                        className="transition hover:border-violet"
                                        titel={a.title}
                                        onder={
                                            a.assessed
                                                ? 'Feedback binnen'
                                                : a.submitted
                                                  ? 'Ingediend'
                                                  : 'Nog niet gedaan'
                                        }
                                        rechts={
                                            <ChevronRight
                                                className="size-4 text-muted-foreground"
                                                aria-hidden
                                            />
                                        }
                                    />
                                </Link>
                            </li>
                        ))}
                    </MeerLijst>
                </Sectie>
            )}
        </>
    );

    const zij = (
        <Sectie
            titel={
                <span className="flex items-center gap-2">
                    <CalendarDays className="size-5 text-violet" aria-hidden />{' '}
                    {enrollments.length
                        ? 'Andere cursusdagen'
                        : 'Komende cursusdagen'}
                </span>
            }
        >
            {course_days.length === 0 ? (
                <Leeg titel="Geen cursusdagen">
                    Er zijn nu geen andere cursusdagen gepland.
                </Leeg>
            ) : (
                <MeerLijst eerst={5}>
                    {course_days.map((d) => (
                        <li key={d.id}>
                            <Link
                                href={cursusdagRoute.url(d.id)}
                                className="block rounded-xl border bg-card p-3 transition hover:border-violet md:p-4"
                            >
                                <p className="font-semibold first-letter:uppercase">
                                    {dagDatum(d.date)}, {d.starts_at} -{' '}
                                    {d.ends_at}
                                </p>
                                <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                    <MapPin
                                        className="size-4 shrink-0"
                                        aria-hidden
                                    />{' '}
                                    {d.city}, {d.location_name}
                                </p>
                                <p className="mt-1 flex flex-wrap justify-between gap-2 text-sm">
                                    <Prijs dag={d} /> <Plekken dag={d} />
                                </p>
                            </Link>
                        </li>
                    ))}
                </MeerLijst>
            )}
        </Sectie>
    );

    return (
        <>
            <Head title="Mijn academy" />
            <Pagina>
                <PaginaKop
                    titel={`Welkom bij de academy, ${voornaam}`}
                    omschrijving="Je ticket, je lessen en je certificaat staan hier bij elkaar."
                />
                <TweeKolommen hoofd={hoofd} zij={zij} />
            </Pagina>
        </>
    );
}
