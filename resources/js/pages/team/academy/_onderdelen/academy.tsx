import { Link, useForm, usePage } from '@inertiajs/react';
import { cursusdagen, inzendingen, lesstof, studenten } from '@/routes/team/academy';
import type { Criterium, Dag } from '../../../academy/_onderdelen/types';
import { Label } from '../../_onderdelen/basis';

/** Tabbladen van academybeheer: één menu-item, vier schermen. */
export function Tabs() {
    const { url } = usePage();
    const tabs = [
        { naam: 'Cursusdagen', href: cursusdagen.url() },
        { naam: 'Beoordelen', href: inzendingen.url() },
        { naam: 'Studenten', href: studenten.url() },
        { naam: 'Lessen en opdrachten', href: lesstof.url() },
    ];
    const pad = (h: string) => new URL(h, window.location.href).pathname;
    const nu = new URL(url, window.location.href).pathname;
    const actief = (h: string) => (pad(h) === pad(cursusdagen.url()) ? nu === pad(h) || nu.startsWith(`${pad(h)}/cursusdagen`) : nu.startsWith(pad(h)));

    return (
        <nav aria-label="Academy" className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <ul className="flex min-w-max gap-1 border-b">
                {tabs.map((t) => (
                    <li key={t.naam}>
                        <Link
                            href={t.href}
                            aria-current={actief(t.href) ? 'page' : undefined}
                            className={`inline-block border-b-2 px-3 py-2 text-sm font-semibold ${actief(t.href) ? 'border-violet text-violet' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                        >
                            {t.naam}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

/** Beoordelen per criterium (1 tot 5) met feedback. Toont direct of het voldoende is (gemiddeld minimaal de grens en geen 1). */
export function Beoordelen({ criteria, url, grens, knop, uitslag = true }: { criteria: Criterium[]; url: string; grens?: number; knop: string; uitslag?: boolean }) {
    const form = useForm<{ scores: Record<string, number>; feedback: string }>({ scores: {}, feedback: '' });
    const cijfers = criteria.map((c) => form.data.scores[c.label]).filter((x): x is number => typeof x === 'number');
    const compleet = cijfers.length === criteria.length;
    const gem = compleet ? cijfers.reduce((a, b) => a + b, 0) / cijfers.length : null;
    const voldoende = gem !== null && grens !== undefined && gem >= grens && Math.min(...cijfers) > 1;
    const fouten = form.errors as Record<string, string | undefined>;

    return (
        <form
            className="space-y-3"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(url, { preserveScroll: true });
            }}
        >
            {criteria.map((c) => (
                <fieldset key={c.label} className="flex flex-wrap items-center justify-between gap-2">
                    <legend className="sr-only">{c.label}</legend>
                    <div className="min-w-0">
                        <p className="text-sm font-semibold">{c.label}</p>
                        {c.hint && <p className="text-xs text-muted-foreground">{c.hint}</p>}
                    </div>
                    <div className="flex gap-1">
                        {[1, 2, 3, 4, 5].map((n) => (
                            <label key={n} className={`grid size-10 cursor-pointer place-items-center rounded-lg border text-sm font-bold has-focus-visible:ring-2 has-focus-visible:ring-violet ${form.data.scores[c.label] === n ? 'border-violet bg-violet text-white' : 'hover:border-violet'}`}>
                                <input type="radio" className="sr-only" name={`s-${c.label}`} value={n} checked={form.data.scores[c.label] === n} onChange={() => form.setData('scores', { ...form.data.scores, [c.label]: n })} aria-label={`${c.label} ${n}`} />
                                {n}
                            </label>
                        ))}
                    </div>
                </fieldset>
            ))}
            <div>
                <label htmlFor={`fb-${url}`} className="mb-1 block text-sm font-semibold">Feedback (ook op tijdcode, bijvoorbeeld "V2 0:14 geluid hol")</label>
                <textarea id={`fb-${url}`} rows={3} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={form.data.feedback} onChange={(e) => form.setData('feedback', e.target.value)} />
            </div>
            {uitslag && gem !== null && (
                <p className={`rounded-lg px-3 py-2 text-sm font-semibold ${voldoende ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-200'}`}>
                    Gemiddeld {gem.toLocaleString('nl-NL', { maximumFractionDigits: 1 })}
                    {Math.min(...cijfers) === 1 ? ', met een 1' : ', geen 1'}: {voldoende ? 'Voldoende, het certificaat gaat direct uit' : 'Verbeteren'}
                </p>
            )}
            {(fouten.scores || fouten.examen) && <p className="text-sm text-rood">{fouten.scores ?? fouten.examen}</p>}
            <button type="submit" disabled={!compleet || form.processing} className="inline-flex h-10 items-center rounded-md bg-violet px-4 text-sm font-semibold text-white disabled:opacity-50">
                {knop}
            </button>
        </form>
    );
}

export function DagStatus({ dag }: { dag: Dag }) {
    if (dag.status === 'afgesloten') {
        return <Label kleur="grijs">Afgesloten</Label>;
    }
    if (dag.status === 'geannuleerd') {
        return <Label kleur="rood">Geannuleerd</Label>;
    }
    if (dag.is_today) {
        return <Label kleur="groen">Vandaag</Label>;
    }

    return dag.free === 0 ? <Label kleur="amber">Vol</Label> : <Label>Open</Label>;
}
