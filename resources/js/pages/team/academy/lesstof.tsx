import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cursusdagen } from '@/routes/team/academy';
import * as lessen from '@/routes/team/academy/lessen';
import * as opdrachten from '@/routes/team/academy/opdrachten';
import type { Criterium } from '../../academy/_onderdelen/types';
import { Pagina, PaginaKop } from '@/components/pagina';
import { Fouten, Paneel, Veld, invoer } from '../_onderdelen/basis';
import { Tabs } from './_onderdelen/academy';

type Les = { id: number; title: string; body: string; position: number; minutes: number | null };
type Opdracht = { id: number; title: string; description: string; criteria: Criterium[]; position: number };

function LesFormulier({ les }: { les?: Les }) {
    const form = useForm({ title: les?.title ?? '', body: les?.body ?? '', position: String(les?.position ?? ''), minutes: String(les?.minutes ?? '') });
    const id = les?.id ?? 'nieuw';

    return (
        <form
            className="space-y-3"
            onSubmit={(e) => {
                e.preventDefault();
                if (les) {
                    form.put(lessen.bijwerken.url(les.id), { preserveScroll: true });
                } else {
                    form.post(lessen.opslaan.url(), { preserveScroll: true, onSuccess: () => form.reset() });
                }
            }}
        >
            <Veld label="Titel" htmlFor={`l-${id}-t`} fout={form.errors.title}>
                <input id={`l-${id}-t`} required value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={invoer} />
            </Veld>
            <Veld label="Lestekst (markdown)" htmlFor={`l-${id}-b`} fout={form.errors.body} hint="**vet**, lijstjes met - of 1., koppen met ##">
                <textarea id={`l-${id}-b`} required rows={6} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} className={`${invoer} h-auto py-2 font-mono`} />
            </Veld>
            <div className="grid grid-cols-2 gap-2">
                <Veld label="Volgorde" htmlFor={`l-${id}-p`} fout={form.errors.position}>
                    <input id={`l-${id}-p`} type="number" min={0} value={form.data.position} onChange={(e) => form.setData('position', e.target.value)} className={invoer} />
                </Veld>
                <Veld label="Minuten (optioneel)" htmlFor={`l-${id}-m`} fout={form.errors.minutes}>
                    <input id={`l-${id}-m`} type="number" min={1} value={form.data.minutes} onChange={(e) => form.setData('minutes', e.target.value)} className={invoer} />
                </Veld>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={form.processing} className="bg-violet hover:bg-violet/90">{les ? 'Opslaan' : 'Les toevoegen'}</Button>
                {les && (
                    <Button type="button" variant="outline" onClick={() => confirm(`Les "${les.title}" verwijderen?`) && router.delete(lessen.verwijderen.url(les.id), { preserveScroll: true })}>
                        Verwijderen
                    </Button>
                )}
            </div>
        </form>
    );
}

function OpdrachtFormulier({ opdracht }: { opdracht?: Opdracht }) {
    const form = useForm({
        title: opdracht?.title ?? '',
        description: opdracht?.description ?? '',
        criteria: opdracht ? opdracht.criteria.map((c) => (c.hint ? `${c.label}: ${c.hint}` : c.label)).join('\n') : 'Hook: Pakt in de eerste 2 seconden\nBeeld: Stabiel, 9:16',
        position: String(opdracht?.position ?? ''),
    });
    const id = opdracht?.id ?? 'nieuw';

    return (
        <form
            className="space-y-3"
            onSubmit={(e) => {
                e.preventDefault();
                if (opdracht) {
                    form.put(opdrachten.bijwerken.url(opdracht.id), { preserveScroll: true });
                } else {
                    form.post(opdrachten.opslaan.url(), { preserveScroll: true, onSuccess: () => form.reset() });
                }
            }}
        >
            <Veld label="Titel" htmlFor={`o-${id}-t`} fout={form.errors.title}>
                <input id={`o-${id}-t`} required value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={invoer} />
            </Veld>
            <Veld label="Opdracht" htmlFor={`o-${id}-d`} fout={form.errors.description}>
                <textarea id={`o-${id}-d`} required rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className={`${invoer} h-auto py-2`} />
            </Veld>
            <Veld label="Beoordelingslijst" htmlFor={`o-${id}-c`} fout={form.errors.criteria} hint='Eén criterium per regel: "Naam: uitleg". Elk criterium krijgt 1 tot 5.'>
                <textarea id={`o-${id}-c`} required rows={4} value={form.data.criteria} onChange={(e) => form.setData('criteria', e.target.value)} className={`${invoer} h-auto py-2`} />
            </Veld>
            <Veld label="Volgorde" htmlFor={`o-${id}-p`} fout={form.errors.position}>
                <input id={`o-${id}-p`} type="number" min={0} value={form.data.position} onChange={(e) => form.setData('position', e.target.value)} className={invoer} />
            </Veld>
            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={form.processing} className="bg-violet hover:bg-violet/90">{opdracht ? 'Opslaan' : 'Opdracht toevoegen'}</Button>
                {opdracht && (
                    <Button type="button" variant="outline" onClick={() => confirm(`Opdracht "${opdracht.title}" verwijderen?`) && router.delete(opdrachten.verwijderen.url(opdracht.id), { preserveScroll: true })}>
                        Verwijderen
                    </Button>
                )}
            </div>
        </form>
    );
}

export default function Lesstof({ lessons, assignments }: { lessons: Les[]; assignments: Opdracht[] }) {
    return (
        <>
            <Head title="Lessen en opdrachten" />
            <Pagina>
                <PaginaKop titel="Lessen en opdrachten" omschrijving="De online leeromgeving: optioneel voor studenten, voor en na de cursusdag." />
                <Tabs />
                <Fouten />
                <div className="grid gap-5 lg:grid-cols-2">
                    <section className="space-y-3">
                        <h2 className="font-heading text-lg font-bold">Lessen ({lessons.length})</h2>
                        {lessons.map((l) => (
                            <Paneel key={l.id}>
                                <details>
                                    <summary className="cursor-pointer font-semibold">{l.position}. {l.title}{l.minutes ? `, ${l.minutes} min` : ''}</summary>
                                    <div className="mt-3"><LesFormulier les={l} /></div>
                                </details>
                            </Paneel>
                        ))}
                        <Paneel titel="Nieuwe les"><LesFormulier /></Paneel>
                    </section>
                    <section className="space-y-3">
                        <h2 className="font-heading text-lg font-bold">Oefenopdrachten ({assignments.length})</h2>
                        {assignments.map((o) => (
                            <Paneel key={o.id}>
                                <details>
                                    <summary className="cursor-pointer font-semibold">{o.position}. {o.title} ({o.criteria.length} criteria)</summary>
                                    <div className="mt-3"><OpdrachtFormulier opdracht={o} /></div>
                                </details>
                            </Paneel>
                        ))}
                        <Paneel titel="Nieuwe opdracht"><OpdrachtFormulier /></Paneel>
                    </section>
                </div>
            </Pagina>
        </>
    );
}

Lesstof.layout = { breadcrumbs: [{ title: 'Cursusdagen', href: cursusdagen.url() }] };
