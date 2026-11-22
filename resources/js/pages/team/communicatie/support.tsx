import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Search } from 'lucide-react';
import { useState } from 'react';
import { Initialen, VraagStatus, tekstvak } from '@/components/communicatie/opmaak';
import type { Vraag, VraagKort } from '@/components/communicatie/types';
import { Button } from '@/components/ui/button';
import { datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import * as team from '@/routes/team';
import communicatie from '@/routes/team/communicatie';
import { Kop, Label } from '../_onderdelen/basis';

type Props = {
    filter: string;
    zoek: string;
    counts: Record<string, number>;
    tickets: VraagKort[];
    ticket: Vraag | null;
    statuses: Record<string, string>;
    priorities: Record<string, string>;
    team: { id: number; name: string }[];
};

const filters = [
    ['open', 'Open'],
    ['mijn', 'Mijn'],
    ['ondernemer', 'Ondernemers'],
    ['creator', 'Creators'],
    ['beantwoord', 'Beantwoord'],
    ['gesloten', 'Gesloten'],
    ['alles', 'Alles'],
] as const;

const omgevingNaam = { ondernemer: 'Ondernemer', creator: 'Creator', team: 'Team' } as const;

/** Werkbak Support (AdminSupport): vragen van ondernemers en creators. Eerste reactie binnen 1 werkdag (voorstel). */
export default function Support({ filter, zoek, counts, tickets, ticket, statuses, priorities, team: teamleden }: Props) {
    const [term, setTerm] = useState(zoek);
    const query = { filter, ...(zoek ? { zoek } : {}) };

    return (
        <>
            <Head title={ticket ? `Support ${ticket.number}` : 'Support'} />
            <div className="mx-auto w-full max-w-7xl space-y-4 p-4 md:p-6">
                <div className={cn(ticket && 'hidden lg:block')}>
                    <Kop
                        titel="Support"
                        sub="Vragen en problemen van ondernemers en creators. Eerste reactie binnen 1 werkdag (voorstel). Elke vraag hangt waar mogelijk aan een samenwerking."
                        acties={
                            <Link href={communicatie.help.url()} className="rounded-md border px-3 py-2 text-sm font-semibold hover:border-violet">
                                Helpcentrum bekijken
                            </Link>
                        }
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-[380px_minmax(0,1fr)]">
                    <section aria-label="Vragen" className={cn('min-w-0 space-y-3', ticket && 'hidden lg:block')}>
                        <form
                            role="search"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(communicatie.support.url(), { filter, ...(term.trim() ? { zoek: term.trim() } : {}) }, { preserveState: true });
                            }}
                        >
                            <label className="relative block">
                                <span className="sr-only">Zoeken</span>
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
                                <input type="search" value={term} onChange={(e) => setTerm(e.target.value)} placeholder="Zoek op onderwerp of nummer" className={`${tekstvak} pl-9`} />
                            </label>
                        </form>
                        <div className="flex flex-wrap gap-1.5" role="group" aria-label="Filter">
                            {filters.map(([key, naam]) => (
                                <Link
                                    key={key}
                                    href={communicatie.support.url({ query: { filter: key, ...(zoek ? { zoek } : {}) } })}
                                    preserveState
                                    aria-current={filter === key ? 'true' : undefined}
                                    className="inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-sm font-semibold aria-[current]:border-violet aria-[current]:bg-violet aria-[current]:text-white"
                                >
                                    {naam} <span className="font-normal opacity-70">{counts[key] ?? 0}</span>
                                </Link>
                            ))}
                        </div>
                        {tickets.length === 0 ? (
                            <p className="rounded-2xl border border-dashed bg-card p-6 text-center text-sm text-muted-foreground">Geen vragen gevonden met deze zoekterm of dit filter.</p>
                        ) : (
                            <ul className="space-y-1.5" data-test="support-lijst">
                                {tickets.map((t) => (
                                    <li key={t.id}>
                                        <Link
                                            href={communicatie.support.vraag.url(t.id, { query })}
                                            preserveScroll
                                            className={cn('block rounded-2xl border bg-card p-3 hover:border-violet', ticket?.id === t.id && 'border-violet ring-2 ring-violet/20')}
                                        >
                                            <span className="flex items-start gap-3">
                                                <Initialen naam={t.user ?? '?'} />
                                                <span className="min-w-0 flex-1">
                                                    <span className="flex items-baseline justify-between gap-2">
                                                        <span className="truncate text-sm font-semibold">{t.user}</span>
                                                        <span className="shrink-0 text-xs text-muted-foreground">{datumTijd(t.updated_at)}</span>
                                                    </span>
                                                    <span className="block truncate font-semibold">{t.subject}</span>
                                                    <span className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                                        {t.number} · {t.company ?? omgevingNaam[t.portal]} · {t.assignee ?? 'Niet toegewezen'}
                                                        <VraagStatus vraag={t} />
                                                        {t.priority === 'hoog' && <Label kleur="rood">Hoog</Label>}
                                                    </span>
                                                </span>
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    {ticket ? (
                        <Detail key={ticket.id} ticket={ticket} statuses={statuses} priorities={priorities} teamleden={teamleden} terugUrl={communicatie.support.url({ query })} />
                    ) : (
                        <div className="hidden min-h-80 place-items-center rounded-2xl border border-dashed bg-card p-8 text-sm text-muted-foreground lg:grid">Kies een vraag.</div>
                    )}
                </div>
            </div>
        </>
    );
}

function Detail({ ticket, statuses, priorities, teamleden, terugUrl }: { ticket: Vraag; statuses: Record<string, string>; priorities: Record<string, string>; teamleden: { id: number; name: string }[]; terugUrl: string }) {
    const instellingen = useForm({ status: ticket.status as string, priority: ticket.priority as string, assigned_to: ticket.assigned_to ? String(ticket.assigned_to) : '' });
    const antwoord = useForm({ body: '', internal: false });
    const voornaam = ticket.user?.split(' ')[0] ?? 'de vraagsteller';
    const teLaat = ticket.status === 'open' && ticket.respond_before && new Date(ticket.respond_before) < new Date();

    return (
        <section aria-label={`Vraag ${ticket.number}`} className="min-w-0 space-y-4 rounded-2xl border bg-card p-4 md:p-5">
            <Link href={terugUrl} className="inline-flex items-center gap-1 text-sm font-semibold text-violet lg:hidden">
                <ArrowLeft className="size-4" aria-hidden /> Alle vragen
            </Link>
            <header>
                <p className="text-xs text-muted-foreground">
                    {ticket.number} · binnen {datumTijd(ticket.created_at)} · {omgevingNaam[ticket.portal]}
                    {ticket.company ? `, ${ticket.company}` : ''}
                </p>
                <h2 className="mt-1 font-heading text-xl font-extrabold break-words">{ticket.subject}</h2>
                <div className="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <VraagStatus vraag={ticket} />
                    {ticket.respond_before && ticket.status === 'open' && <Label kleur={teLaat ? 'rood' : 'grijs'}>Reageren voor {datumTijd(ticket.respond_before)}</Label>}
                    {ticket.collaboration && (
                        <Link href={team.samenwerking.url(ticket.collaboration.id)} className="text-violet underline">
                            {ticket.collaboration.number} {ticket.collaboration.title}
                        </Link>
                    )}
                </div>
            </header>

            <form
                className="grid gap-2 rounded-xl bg-muted/60 p-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end"
                onSubmit={(e) => {
                    e.preventDefault();
                    instellingen.post(communicatie.support.bijwerken.url(ticket.id), { preserveScroll: true });
                }}
            >
                <label className="space-y-1 text-xs font-semibold">
                    Status
                    <select value={instellingen.data.status} onChange={(e) => instellingen.setData('status', e.target.value)} className={tekstvak}>
                        {Object.entries(statuses).map(([k, v]) => (
                            <option key={k} value={k}>
                                {v}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="space-y-1 text-xs font-semibold">
                    Prioriteit
                    <select value={instellingen.data.priority} onChange={(e) => instellingen.setData('priority', e.target.value)} className={tekstvak}>
                        {Object.entries(priorities).map(([k, v]) => (
                            <option key={k} value={k}>
                                {v}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="space-y-1 text-xs font-semibold">
                    Toegewezen aan
                    <select value={instellingen.data.assigned_to} onChange={(e) => instellingen.setData('assigned_to', e.target.value)} className={tekstvak}>
                        <option value="">Niemand</option>
                        {teamleden.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.name}
                            </option>
                        ))}
                    </select>
                </label>
                <Button type="submit" variant="outline" disabled={instellingen.processing || !instellingen.isDirty}>
                    Opslaan
                </Button>
            </form>

            <ol className="space-y-3" data-test="reacties">
                {ticket.replies.map((r) => (
                    <li key={r.id} className={cn('rounded-2xl border p-3.5', r.internal ? 'border-amber-300 bg-amber-50 dark:bg-amber-950/30' : r.mine_side ? 'bg-background' : 'border-violet/30 bg-violet/5')}>
                        <p className="mb-1.5 flex flex-wrap items-center gap-2 text-sm">
                            <Initialen naam={r.author} className="size-7" />
                            <span className="font-semibold">{r.author}</span>
                            {r.internal && <Label kleur="amber">Interne notitie</Label>}
                            <span className="text-xs text-muted-foreground">{datumTijd(r.at)}</span>
                        </p>
                        <p className="text-[15px] leading-relaxed whitespace-pre-line">{r.body}</p>
                    </li>
                ))}
            </ol>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    antwoord.post(communicatie.support.antwoorden.url(ticket.id), { preserveScroll: true, onSuccess: () => antwoord.reset('body') });
                }}
                className="space-y-2"
                data-test="antwoord-formulier"
            >
                <div className="flex flex-wrap gap-1.5" role="radiogroup" aria-label="Soort reactie">
                    {[
                        [false, `Antwoord aan ${voornaam}`],
                        [true, 'Interne notitie'],
                    ].map(([intern, naam]) => (
                        <button
                            key={String(intern)}
                            type="button"
                            role="radio"
                            aria-checked={antwoord.data.internal === intern}
                            onClick={() => antwoord.setData('internal', intern as boolean)}
                            className="rounded-full border px-3 py-1.5 text-sm font-semibold aria-checked:border-aubergine aria-checked:bg-aubergine aria-checked:text-white dark:aria-checked:bg-violet"
                        >
                            {naam as string}
                        </button>
                    ))}
                </div>
                <label htmlFor="antwoord" className="sr-only">
                    Reactie
                </label>
                <textarea
                    id="antwoord"
                    rows={4}
                    required
                    value={antwoord.data.body}
                    onChange={(e) => antwoord.setData('body', e.target.value)}
                    className={cn(tekstvak, antwoord.data.internal && 'border-amber-300 bg-amber-50 dark:bg-amber-950/30')}
                    placeholder={antwoord.data.internal ? 'Alleen het team ziet deze notitie.' : `Hoi ${voornaam},`}
                />
                {antwoord.errors.body && <p className="text-sm text-rood">{antwoord.errors.body}</p>}
                <p className="text-xs text-muted-foreground">
                    {antwoord.data.internal ? 'Alleen zichtbaar voor het team.' : `${voornaam} krijgt een melding in de app. De vraag staat daarna op Beantwoord.`}
                </p>
                <Button type="submit" disabled={antwoord.processing} className="bg-violet hover:bg-violet/90">
                    {antwoord.data.internal ? 'Notitie opslaan' : 'Antwoord versturen'}
                </Button>
            </form>
        </section>
    );
}
