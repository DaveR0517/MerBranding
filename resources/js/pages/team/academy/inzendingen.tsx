import { Head, Link } from '@inertiajs/react';
import { datumTijd } from '@/lib/datum';
import { cursusdag, cursusdagen } from '@/routes/team/academy';
import { beoordelen } from '@/routes/team/academy/inzending';
import { Ingediend, Uitslag } from '../../academy/_onderdelen/onderdelen';
import type { Criterium, Inzending } from '../../academy/_onderdelen/types';
import { Leeg, Pagina, PaginaKop } from '@/components/pagina';
import { Fouten, Label, Paneel } from '../_onderdelen/basis';
import { Beoordelen, Tabs } from './_onderdelen/academy';

type Kaart = Inzending & { student: string; assignment: string; criteria: Criterium[] };
type Props = {
    open: Kaart[];
    assessed: Kaart[];
    exams: { id: number; student: string; day: string; course_day_id: number; submitted_at: string | null; attempt: number }[];
};

export default function Inzendingen({ open, assessed, exams }: Props) {
    return (
        <>
            <Head title="Beoordelen" />
            <Pagina>
                <PaginaKop titel="Beoordelen" omschrijving="Praktijkexamens en oefenopdrachten met de beoordelingslijst." />
                <Tabs />
                <Fouten />

                <Paneel titel="Praktijkexamens te beoordelen" sub="Beoordelen gebeurt bij de cursusdag, met de video's naast de lijst.">
                    {exams.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Geen praktijkexamens open.</p>
                    ) : (
                        <ul className="divide-y rounded-xl border text-sm">
                            {exams.map((e) => (
                                <li key={e.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
                                    <span>
                                        <span className="font-semibold">{e.student}</span>
                                        <span className="block text-muted-foreground">{e.day}. Ingeleverd {datumTijd(e.submitted_at)}{e.attempt > 1 ? ', herkansing' : ''}</span>
                                    </span>
                                    <Link href={`${cursusdag.url(e.course_day_id)}#examens`} className="font-semibold text-violet underline">Beoordelen</Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Paneel>

                <section className="space-y-3">
                    <h2 className="font-heading text-lg font-bold">Oefenopdrachten ({open.length})</h2>
                    {open.length === 0 ? (
                        <Leeg titel="Alle oefenopdrachten zijn beoordeeld." />
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {open.map((s) => (
                                <Paneel key={s.id} titel={`${s.student}: ${s.assignment}`} sub={`Ingediend ${datumTijd(s.created_at)}`}>
                                    <Ingediend link={s.link} video_url={s.video_url} />
                                    {s.note && <p className="mt-2 text-sm">{s.note}</p>}
                                    <div className="mt-3">
                                        <Beoordelen criteria={s.criteria} url={beoordelen.url(s.id)} knop="Beoordeling versturen" uitslag={false} />
                                    </div>
                                </Paneel>
                            ))}
                        </div>
                    )}
                </section>

                {assessed.length > 0 && (
                    <Paneel titel="Laatst beoordeeld">
                        <ul className="space-y-3">
                            {assessed.map((s) => (
                                <li key={s.id} className="rounded-xl border p-3 text-sm">
                                    <p className="mb-2 flex flex-wrap items-center gap-2">
                                        <span className="font-semibold">{s.student}: {s.assignment}</span>
                                        <Label kleur="groen">Beoordeeld {datumTijd(s.assessed_at)}{s.assessor ? ` door ${s.assessor}` : ''}</Label>
                                    </p>
                                    {s.scores && <Uitslag criteria={s.criteria} scores={s.scores} />}
                                    {s.feedback && <p className="mt-2 text-muted-foreground">{s.feedback}</p>}
                                </li>
                            ))}
                        </ul>
                    </Paneel>
                )}
            </Pagina>
        </>
    );
}

Inzendingen.layout = { breadcrumbs: [{ title: 'Cursusdagen', href: cursusdagen.url() }] };
