import { Head, Link } from '@inertiajs/react';
import { euro } from '@/lib/datum';
import { cursusdag, cursusdagen, inzendingen } from '@/routes/team/academy';
import { dagDatum } from '../../academy/_onderdelen/onderdelen';
import type { Dag } from '../../academy/_onderdelen/types';
import { Leeg, Pagina, PaginaKop } from '@/components/pagina';
import { Paneel } from '../_onderdelen/basis';
import { DagStatus, Tabs } from './_onderdelen/academy';
import { DagFormulier } from './_onderdelen/dag-formulier';

type Rij = Dag & { certified: number; paid: number };
type Props = {
    days: Rij[];
    open_exams: number;
    open_submissions: number;
    defaults: { price_cents: number; max_participants: number; exam_required: boolean };
};

export default function Cursusdagen({ days, open_exams, open_submissions, defaults }: Props) {
    return (
        <>
            <Head title="Cursusdagen" />
            <Pagina>
                <PaginaKop titel="Cursusdagen" omschrijving="MEBRANDING Academy: cursusdagen voor creators op locatie bij ondernemers." />
                <Tabs />
                {(open_exams > 0 || open_submissions > 0) && (
                    <p className="rounded-xl border border-violet/30 bg-violet/5 p-3 text-sm">
                        Te beoordelen: {open_exams} {open_exams === 1 ? 'praktijkexamen' : 'praktijkexamens'} en {open_submissions} {open_submissions === 1 ? 'oefenopdracht' : 'oefenopdrachten'}.{' '}
                        <Link href={inzendingen.url()} className="font-semibold text-violet underline">Naar beoordelen</Link>
                    </p>
                )}

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
                    <div>
                        {days.length === 0 ? (
                            <Leeg titel="Nog geen cursusdagen. Plan de eerste hiernaast." />
                        ) : (
                            <>
                                <ul className="space-y-2 md:hidden">
                                    {days.map((d) => (
                                        <li key={d.id}>
                                            <Link href={cursusdag.url(d.id)} className="block rounded-xl border bg-card p-4 hover:border-violet">
                                                <p className="flex flex-wrap items-center gap-2 font-semibold">{d.title} <DagStatus dag={d} /></p>
                                                <p className="text-sm text-muted-foreground first-letter:uppercase">{dagDatum(d.date, false)}, {d.location_name}, {d.city}</p>
                                                <p className="mt-1 text-sm">Bezetting {d.taken}/{d.max_participants} · wachtlijst {d.waitlist} · betaald {d.paid}</p>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                                <div className="hidden overflow-hidden rounded-xl border bg-card md:block">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/60 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3 font-semibold">Titel</th>
                                                <th className="px-4 py-3 font-semibold">Datum</th>
                                                <th className="px-4 py-3 font-semibold">Locatie</th>
                                                <th className="px-4 py-3 font-semibold">Docent</th>
                                                <th className="px-4 py-3 text-right font-semibold">Bezetting</th>
                                                <th className="px-4 py-3 text-right font-semibold">Wachtlijst</th>
                                                <th className="px-4 py-3 text-right font-semibold">Omzet</th>
                                                <th className="px-4 py-3 font-semibold">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {days.map((d) => (
                                                <tr key={d.id} className="hover:bg-muted/40">
                                                    <td className="px-4 py-3">
                                                        <Link href={cursusdag.url(d.id)} className="font-semibold text-violet hover:underline">{d.title}</Link>
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap first-letter:uppercase">{dagDatum(d.date, false)}</td>
                                                    <td className="px-4 py-3">{d.location_name}, {d.city}</td>
                                                    <td className="px-4 py-3">{d.teacher_name ?? '-'}</td>
                                                    <td className="px-4 py-3 text-right">{d.taken}/{d.max_participants}</td>
                                                    <td className="px-4 py-3 text-right">{d.waitlist}</td>
                                                    <td className="px-4 py-3 text-right whitespace-nowrap" title="Betaalde inschrijvingen maal de prijs">{euro(d.paid * d.price_cents)}</td>
                                                    <td className="px-4 py-3"><DagStatus dag={d} /></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </div>
                    <Paneel titel="Nieuwe cursusdag" id="nieuw">
                        <DagFormulier standaard={defaults} />
                    </Paneel>
                </div>
            </Pagina>
        </>
    );
}

Cursusdagen.layout = { breadcrumbs: [{ title: 'Cursusdagen', href: cursusdagen.url() }] };
