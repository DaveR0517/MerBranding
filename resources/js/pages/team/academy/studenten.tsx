import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cursusdag, cursusdagen } from '@/routes/team/academy';
import { opslaan } from '@/routes/team/academy/studenten';
import { InloggenAls, Kop, Label, Leeg, Pagina, Paneel, Veld, invoer } from '../_onderdelen/basis';
import { Tabs } from './_onderdelen/academy';

type Student = {
    id: number;
    name: string;
    email: string;
    enrollments: { course_day_id: number; day: string; date: string; status_name: string; certificate: string | null }[];
    creator_status: string | null;
};
type Props = { students: Student[]; days: { id: number; name: string }[] };

export default function Studenten({ students, days }: Props) {
    const form = useForm({ name: '', email: '', course_day_id: '' });

    return (
        <>
            <Head title="Studenten" />
            <Pagina breed>
                <Kop titel="Studenten" sub={`${students.length} ${students.length === 1 ? 'student' : 'studenten'}`} />
                <Tabs />
                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
                    <div>
                        {students.length === 0 ? (
                            <Leeg>Nog geen studenten.</Leeg>
                        ) : (
                            <ul className="space-y-2">
                                {students.map((s) => (
                                    <li key={s.id} className="rounded-2xl border bg-card p-4 text-sm">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="flex flex-wrap items-center gap-1.5">
                                                    <span className="font-semibold">{s.name}</span>
                                                    {s.creator_status && <Label kleur="groen">{s.creator_status === 'screening' ? 'Creator in screening' : `Creator, ${s.creator_status}`}</Label>}
                                                </p>
                                                <p className="break-all text-muted-foreground">{s.email}</p>
                                            </div>
                                            <InloggenAls userId={s.id} naam={s.name} />
                                        </div>
                                        {s.enrollments.length > 0 ? (
                                            <ul className="mt-2 space-y-1">
                                                {s.enrollments.map((e) => (
                                                    <li key={e.course_day_id} className="flex flex-wrap items-center gap-1.5">
                                                        <Link href={cursusdag.url(e.course_day_id)} className="text-violet hover:underline">{e.day}</Link>
                                                        <Label kleur="grijs">{e.status_name}</Label>
                                                        {e.certificate && <Label kleur="groen">{e.certificate}</Label>}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="mt-2 text-muted-foreground">Nog niet ingeschreven.</p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <Paneel titel="Student toevoegen" sub="Er komt een account met de rol Student. De student krijgt een uitnodiging per mail en kiest zelf een wachtwoord.">
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(opslaan.url(), { preserveScroll: true, onSuccess: () => form.reset() });
                            }}
                        >
                            <Veld label="Naam" htmlFor="s-name" fout={form.errors.name}>
                                <input id="s-name" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={invoer} autoComplete="off" />
                            </Veld>
                            <Veld label="E-mailadres" htmlFor="s-email" fout={form.errors.email}>
                                <input id="s-email" type="email" required value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className={invoer} autoComplete="off" />
                            </Veld>
                            <Veld label="Direct inschrijven voor (optioneel)" htmlFor="s-dag" fout={form.errors.course_day_id} hint="Is de dag vol, dan komt de student op de wachtlijst.">
                                <select id="s-dag" value={form.data.course_day_id} onChange={(e) => form.setData('course_day_id', e.target.value)} className={invoer}>
                                    <option value="">Nog niet</option>
                                    {days.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                                </select>
                            </Veld>
                            <Button type="submit" disabled={form.processing} className="bg-violet hover:bg-violet/90">Uitnodigen</Button>
                        </form>
                    </Paneel>
                </div>
            </Pagina>
        </>
    );
}

Studenten.layout = { breadcrumbs: [{ title: 'Cursusdagen', href: cursusdagen.url() }] };
