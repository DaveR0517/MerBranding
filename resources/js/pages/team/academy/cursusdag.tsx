import { Head, router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { datumTijd, euro } from '@/lib/datum';
import { cursusdagen } from '@/routes/team/academy';
import { afsluiten, inschrijven } from '@/routes/team/academy/cursusdag';
import { beoordelen } from '@/routes/team/academy/examen';
import { afmelden, betaald, inchecken } from '@/routes/team/academy/inschrijving';
import { Ingediend, Uitslag, dagDatum, gemiddeld } from '../../academy/_onderdelen/onderdelen';
import type { Certificaat, Criterium, Dag, Examen, InschrijvingStatus } from '../../academy/_onderdelen/types';
import { Leeg, Pagina, PaginaKop } from '@/components/pagina';
import { Fouten, InloggenAls, Label, Paneel, initialen, invoer } from '../_onderdelen/basis';
import { Beoordelen, DagStatus, Tabs } from './_onderdelen/academy';
import { DagFormulier } from './_onderdelen/dag-formulier';

type Deelnemer = {
    id: number;
    user_id: number;
    name: string;
    email: string;
    handle: string | null;
    city: string | null;
    phone: string | null;
    status: InschrijvingStatus;
    status_name: string;
    paid: boolean;
    paid_at: string | null;
    offer_expires_at: string | null;
    enrolled_at: string;
    checked_in_at: string | null;
    checkin_reason: string | null;
    exam: Examen | null;
    certificate: Certificaat | null;
    creator_status: string | null;
};
type Props = {
    day: Dag;
    participants: Deelnemer[];
    waitlist: Deelnemer[];
    cancelled: Deelnemer[];
    criteria: Criterium[];
    pass_average: number;
    students: { id: number; name: string; email: string }[];
};

const statusKleur: Record<InschrijvingStatus, 'violet' | 'groen' | 'grijs' | 'rood' | 'amber'> = {
    ingeschreven: 'violet',
    wachtlijst: 'amber',
    aanwezig: 'groen',
    geslaagd: 'groen',
    afwezig: 'rood',
    afgemeld: 'grijs',
};

const examenNaam: Record<Examen['status'], string> = {
    open: 'Nog niet ingeleverd',
    ingeleverd: 'Te beoordelen',
    voldoende: 'Voldoende',
    verbeteren: 'Herkansing',
    onvoldoende: 'Onvoldoende',
};

const handle = (h: string | null) => (h ? `@${h.replace(/^@/, '')}` : null);

function Betaald({ d }: { d: Deelnemer }) {
    return (
        <label className="inline-flex items-center gap-2 text-sm">
            <input
                type="checkbox"
                className="size-4 accent-violet"
                checked={d.paid}
                onChange={(e) => router.post(betaald.url(d.id), { paid: e.target.checked }, { preserveScroll: true })}
                aria-label={`${d.name} betaald`}
            />
            {d.paid ? 'Betaald' : 'Nog te betalen'}
        </label>
    );
}

function Acties({ d, dag }: { d: Deelnemer; dag: Dag }) {
    const kanInchecken = dag.is_today && dag.status === 'open' && !d.checked_in_at && d.status === 'ingeschreven';

    return (
        <div className="flex flex-wrap gap-1.5">
            {kanInchecken && (
                <Button
                    size="sm"
                    className="bg-violet hover:bg-violet/90"
                    onClick={() => {
                        const reden = prompt(`${d.name} handmatig inchecken. Reden (optioneel):`, 'handmatig');
                        if (reden !== null) {
                            router.post(inchecken.url(d.id), { reason: reden }, { preserveScroll: true });
                        }
                    }}
                >
                    Inchecken
                </Button>
            )}
            {dag.status === 'open' && d.status === 'ingeschreven' && (
                <Button size="sm" variant="outline" onClick={() => confirm(`${d.name} afmelden? De plek gaat naar de wachtlijst.`) && router.post(afmelden.url(d.id), {}, { preserveScroll: true })}>
                    Afmelden
                </Button>
            )}
            <InloggenAls userId={d.user_id} naam={d.name} />
        </div>
    );
}

function Toevoegen({ dag, students }: { dag: Dag; students: Props['students'] }) {
    const form = useForm({ user_id: '' });

    if (students.length === 0) {
        return <p className="text-sm text-muted-foreground">Alle studenten staan al op deze dag. Nieuwe student? Voeg hem toe bij Studenten.</p>;
    }

    return (
        <form
            className="flex flex-wrap items-end gap-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(inschrijven.url(dag.id), { preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <div className="min-w-0 flex-1">
                <label htmlFor="student" className="mb-1 block text-sm font-semibold">Student inschrijven</label>
                <select id="student" required value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} className={invoer}>
                    <option value="">Kies een student</option>
                    {students.map((s) => (
                        <option key={s.id} value={s.id}>{s.name} ({s.email})</option>
                    ))}
                </select>
            </div>
            <Button type="submit" disabled={form.processing} className="bg-violet hover:bg-violet/90">{dag.free > 0 ? 'Inschrijven' : 'Op de wachtlijst'}</Button>
        </form>
    );
}

export default function Cursusdag({ day, participants, waitlist, cancelled, criteria, pass_average, students }: Props) {
    const [zoek, setZoek] = useState('');
    const aanwezig = participants.filter((p) => p.checked_in_at).length;
    const gevonden = participants.filter((p) => `${p.name} ${p.handle ?? ''} ${p.email}`.toLowerCase().includes(zoek.toLowerCase()));
    const examens = participants.filter((p) => p.exam);
    const gecertificeerd = participants.filter((p) => p.certificate);

    return (
        <>
            <Head title={`Cursusdag ${day.city}`} />
            <Pagina>
                <PaginaKop
                    titel={`${day.city}, ${dagDatum(day.date, false)}`}
                    omschrijving={
                        <span className="flex flex-wrap items-center gap-2">
                            <DagStatus dag={day} />
                            {day.title}, {day.location_name}, {day.starts_at} - {day.ends_at}
                            {day.teacher_name ? `, docent ${day.teacher_name}` : ''}
                        </span>
                    }
                    acties={
                        day.status === 'open' && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    confirm(
                                        day.exam_required
                                            ? 'Dag afsluiten? Wie niet incheckte krijgt Niet verschenen. Daarna staat het praktijkexamen open.'
                                            : 'Dag afsluiten? Wie niet incheckte krijgt Niet verschenen. Wie aanwezig was, krijgt direct het certificaat.',
                                    ) && router.post(afsluiten.url(day.id), {}, { preserveScroll: true })
                                }
                            >
                                Dag afsluiten
                            </Button>
                        )
                    }
                />
                <Tabs />
                <Fouten />

                <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {[
                        ['Bezetting', `${day.taken}/${day.max_participants}`],
                        ['Wachtlijst', String(waitlist.length)],
                        ['Aanwezig', `${aanwezig} van ${participants.length}`],
                        ['Gecertificeerd', String(gecertificeerd.length)],
                    ].map(([k, v]) => (
                        <div key={k} className="rounded-xl border bg-card p-3">
                            <dt className="text-xs font-semibold text-muted-foreground uppercase">{k}</dt>
                            <dd className="font-heading text-2xl font-extrabold">{v}</dd>
                        </div>
                    ))}
                </dl>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,400px)]">
                    <div className="space-y-5">
                        <Paneel
                            titel="Deelnemers en check-in"
                            sub={
                                day.is_today && day.status === 'open'
                                    ? 'Vandaag: scan de QR-code op het ticket met de camera van je telefoon, of check handmatig in met een reden.'
                                    : 'Inchecken kan alleen op de dag zelf.'
                            }
                        >
                            <div className="relative mb-3">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
                                <label htmlFor="zoek" className="sr-only">Zoek deelnemer</label>
                                <input id="zoek" type="search" placeholder="Zoek deelnemer" value={zoek} onChange={(e) => setZoek(e.target.value)} className={`${invoer} pl-9`} />
                            </div>
                            {participants.length === 0 ? (
                                <Leeg titel="Nog geen inschrijvingen." />
                            ) : (
                                <ul className="divide-y rounded-xl border" data-test="deelnemers">
                                    {gevonden.map((d) => (
                                        <li key={d.id} className="flex flex-wrap items-start gap-3 p-3">
                                            <span className="grid size-9 shrink-0 place-items-center rounded-full bg-brand-gradient font-heading text-xs font-bold text-white" aria-hidden>{initialen(d.name)}</span>
                                            <div className="min-w-0 flex-1 text-sm">
                                                <p className="flex flex-wrap items-center gap-1.5">
                                                    <span className="font-semibold">{d.name}</span>
                                                    <Label kleur={statusKleur[d.status]}>{d.status_name}{d.checked_in_at ? ` ${new Date(d.checked_in_at).toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' })}` : ''}</Label>
                                                </p>
                                                <p className="text-muted-foreground break-all">{[handle(d.handle), d.email, d.phone].filter(Boolean).join(' · ')}</p>
                                                {d.checkin_reason && <p className="text-xs text-muted-foreground">Check-in: {d.checkin_reason}</p>}
                                                {d.offer_expires_at && !d.paid && <p className="text-xs text-amber-800 dark:text-amber-300">Van de wachtlijst, betalen voor {datumTijd(d.offer_expires_at)}</p>}
                                                <div className="mt-1.5"><Betaald d={d} /></div>
                                            </div>
                                            <Acties d={d} dag={day} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {day.status === 'open' && (
                                <div className="mt-4 border-t pt-4">
                                    <Toevoegen dag={day} students={students} />
                                </div>
                            )}
                        </Paneel>

                        {examens.length > 0 && (
                            <Paneel id="examens" titel="Praktijkexamens" sub={`Beoordelen binnen 3 werkdagen na inleveren. Voldoende bij gemiddeld ${gemiddeld(pass_average)} of hoger en geen enkele 1.`}>
                                <ul className="space-y-4">
                                    {examens.map((d) => {
                                        const x = d.exam!;

                                        return (
                                            <li key={d.id} className="rounded-xl border p-3">
                                                <p className="flex flex-wrap items-center gap-2 text-sm">
                                                    <span className="font-semibold">{d.name}</span>
                                                    <Label kleur={x.status === 'voldoende' ? 'groen' : x.status === 'ingeleverd' ? 'violet' : x.status === 'onvoldoende' ? 'rood' : 'amber'}>{examenNaam[x.status]}</Label>
                                                    <span className="text-muted-foreground">
                                                        {x.submitted_at ? `Ingeleverd ${datumTijd(x.submitted_at)}` : `Open tot ${datumTijd(x.due_at)}`}
                                                        {x.attempt > 1 ? ', herkansing' : ''}
                                                    </span>
                                                </p>
                                                {x.status === 'ingeleverd' && (
                                                    <div className="mt-3 space-y-3">
                                                        <div className="grid gap-2 sm:grid-cols-3">
                                                            {x.videos.map((v) => <Ingediend key={v.id} label={v.label} link={v.link} video_url={v.video_url} />)}
                                                        </div>
                                                        <Beoordelen criteria={criteria} url={beoordelen.url(x.id)} grens={pass_average} knop="Beoordeling opslaan" />
                                                    </div>
                                                )}
                                                {x.scores && x.status !== 'ingeleverd' && (
                                                    <div className="mt-3">
                                                        <p className="mb-2 text-sm">Gemiddeld {gemiddeld(x.average)}{x.assessor ? `, beoordeeld door ${x.assessor}` : ''}</p>
                                                        <Uitslag criteria={criteria} scores={x.scores} />
                                                    </div>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            </Paneel>
                        )}

                        {gecertificeerd.length > 0 && (
                            <Paneel titel="Doorstroomlijst">
                                <ul className="divide-y rounded-xl border text-sm">
                                    {gecertificeerd.map((d) => (
                                        <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
                                            <span className="font-semibold">{d.name}</span>
                                            <span className="text-muted-foreground break-all">{d.certificate!.number}</span>
                                            <Label kleur={d.creator_status ? 'groen' : 'grijs'}>{d.creator_status === 'screening' ? 'In screening' : d.creator_status ? `Creator, ${d.creator_status}` : 'Word creator: niet gestart'}</Label>
                                        </li>
                                    ))}
                                </ul>
                            </Paneel>
                        )}
                    </div>

                    <div className="space-y-5">
                        <Paneel titel="Wachtlijst" sub={`${waitlist.length} ${waitlist.length === 1 ? 'wachtende' : 'wachtenden'}. Komt er een plek vrij, dan schuift de eerste automatisch door en heeft 24 uur om te betalen (voorstel).`}>
                            {waitlist.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Niemand op de wachtlijst.</p>
                            ) : (
                                <ol className="space-y-2 text-sm">
                                    {waitlist.map((d, i) => (
                                        <li key={d.id} className="flex gap-2">
                                            <span className="font-heading font-extrabold text-violet">{i + 1}</span>
                                            <span className="min-w-0 flex-1">
                                                <span className="font-semibold">{d.name}</span>
                                                <span className="block text-muted-foreground">Op de wachtlijst sinds {datumTijd(d.enrolled_at)}</span>
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </Paneel>

                        <Paneel titel="Betaling" sub="De betaalpartij is nog niet gekozen. Zet een deelnemer handmatig op betaald.">
                            <p className="text-sm">
                                {participants.filter((p) => p.paid).length} van {participants.length} betaald, prijs {euro(day.price_cents)} (voorstel).
                            </p>
                        </Paneel>

                        {cancelled.length > 0 && (
                            <Paneel titel="Afgemeld">
                                <ul className="text-sm text-muted-foreground">
                                    {cancelled.map((d) => <li key={d.id}>{d.name}</li>)}
                                </ul>
                            </Paneel>
                        )}

                        <Paneel titel="Instellingen van deze dag">
                            <details>
                                <summary className="cursor-pointer text-sm font-semibold text-violet">Bewerken</summary>
                                <div className="mt-3"><DagFormulier dag={day} /></div>
                            </details>
                        </Paneel>
                    </div>
                </div>
            </Pagina>
        </>
    );
}

Cursusdag.layout = { breadcrumbs: [{ title: 'Cursusdagen', href: cursusdagen.url() }] };
