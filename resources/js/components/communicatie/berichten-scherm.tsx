import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, MessagesSquare, Search, Send } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { Button } from '@/components/ui/button';
import { datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import { Initialen, Teller, tekstvak } from './opmaak';
import type { Bericht, Gesprek, Gesprekregel, Kanaal, Omgeving } from './types';

type Props = {
    omgeving: Omgeving;
    conversations: Gesprekregel[];
    conversation: Gesprek | null;
    inboxUrl: string;
    kop: ReactNode;
    leegTekst: string;
};

const filters = [
    { key: 'alles', label: 'Alles' },
    { key: 'ongelezen', label: 'Ongelezen' },
    { key: 'lopend', label: 'Lopend' },
    { key: 'afgerond', label: 'Afgerond' },
] as const;

/** Berichten per samenwerking: links de gesprekken, rechts het gekozen gesprek (op de telefoon een van de twee). */
export function BerichtenScherm({ omgeving, conversations, conversation, inboxUrl, kop, leegTekst }: Props) {
    const [zoek, setZoek] = useState('');
    const [filter, setFilter] = useState<(typeof filters)[number]['key']>('alles');
    const ongelezen = conversations.reduce((n, c) => n + (c.unread > 0 ? 1 : 0), 0);

    const lijst = useMemo(() => {
        const z = zoek.trim().toLowerCase();

        return conversations.filter((c) => {
            if (filter === 'ongelezen' && c.unread === 0) {
                return false;
            }
            if (filter === 'lopend' && c.afgerond) {
                return false;
            }
            if (filter === 'afgerond' && !c.afgerond) {
                return false;
            }

            return !z || [c.title, c.company, c.location, c.creator, c.number].some((v) => v?.toLowerCase().includes(z));
        });
    }, [conversations, zoek, filter]);

    return (
        <div className="mx-auto w-full max-w-7xl space-y-4 p-4 md:p-6">
            <div className={cn(conversation && 'hidden lg:block')}>{kop}</div>

            <div className="grid gap-4 lg:grid-cols-[340px_minmax(0,1fr)]">
                <section aria-label="Gesprekken" className={cn('min-w-0 space-y-3', conversation && 'hidden lg:block')}>
                    <label className="relative block">
                        <span className="sr-only">Zoek gesprek</span>
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
                        <input
                            type="search"
                            value={zoek}
                            onChange={(e) => setZoek(e.target.value)}
                            placeholder={omgeving === 'creator' ? 'Zoek op zaak of vestiging' : 'Zoek op creator, zaak of vestiging'}
                            className={`${tekstvak} pl-9`}
                        />
                    </label>
                    <div className="flex flex-wrap gap-1.5" role="group" aria-label="Filter">
                        {filters.map((f) => (
                            <button
                                key={f.key}
                                type="button"
                                aria-pressed={filter === f.key}
                                onClick={() => setFilter(f.key)}
                                className="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-semibold transition aria-pressed:border-violet aria-pressed:bg-violet aria-pressed:text-white"
                            >
                                {f.label}
                                {f.key === 'ongelezen' && ongelezen > 0 && <Teller>{ongelezen}</Teller>}
                            </button>
                        ))}
                    </div>
                    {lijst.length === 0 ? (
                        <p className="rounded-2xl border border-dashed bg-card p-6 text-center text-sm text-muted-foreground">
                            {conversations.length === 0 ? leegTekst : 'Geen gesprekken met deze zoekterm of dit filter.'}
                        </p>
                    ) : (
                        <ul className="space-y-1.5" data-test="gesprekken">
                            {lijst.map((c) => (
                                <li key={c.id}>
                                    <Link
                                        href={c.url}
                                        preserveScroll
                                        className={cn(
                                            'block rounded-2xl border bg-card p-3 transition hover:border-violet',
                                            conversation?.id === c.id && 'border-violet ring-2 ring-violet/20',
                                            c.unread > 0 && 'border-violet/40 bg-violet/5',
                                        )}
                                    >
                                        <span className="flex items-start gap-3">
                                            <Initialen naam={omgeving === 'creator' ? c.company : (c.creator ?? c.company)} />
                                            <span className="min-w-0 flex-1">
                                                <span className="flex items-baseline justify-between gap-2">
                                                    <span className="truncate font-semibold">{c.title}</span>
                                                    {c.last && <span className="shrink-0 text-xs text-muted-foreground">{datumTijd(c.last.at)}</span>}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {[omgeving === 'creator' ? c.company : c.creator, c.location].filter(Boolean).join(', ')}
                                                </span>
                                                <span className="mt-1 flex items-center justify-between gap-2">
                                                    <span className={cn('truncate text-sm', c.unread > 0 ? 'font-semibold' : 'text-muted-foreground')}>
                                                        {c.last ? `${c.last.author ? `${c.last.author.split(' ')[0]}: ` : ''}${c.last.body}` : 'Nog geen berichten'}
                                                    </span>
                                                    {c.unread > 0 && <Teller aria-label={`${c.unread} ongelezen`}>{c.unread}</Teller>}
                                                </span>
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {conversation ? (
                    <Gesprekvenster key={conversation.id} gesprek={conversation} omgeving={omgeving} inboxUrl={inboxUrl} />
                ) : (
                    <div className="hidden min-h-80 place-items-center rounded-2xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground lg:grid">
                        <span>
                            <MessagesSquare className="mx-auto mb-2 size-8 text-violet" aria-hidden />
                            Kies een gesprek. Elke samenwerking heeft een eigen gesprek.
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}

function Gesprekvenster({ gesprek, omgeving, inboxUrl }: { gesprek: Gesprek; omgeving: Omgeving; inboxUrl: string }) {
    // Begin in het kanaal met het nieuwste bericht.
    const start = [...gesprek.channels].sort((a, b) => (laatste(b) ?? '').localeCompare(laatste(a) ?? ''))[0]?.key ?? gesprek.channels[0]?.key;
    const [kanaalKey, setKanaalKey] = useState(start);
    const kanaal = gesprek.channels.find((k) => k.key === kanaalKey) ?? gesprek.channels[0];

    return (
        <section aria-label={`Gesprek over ${gesprek.title}`} className="flex min-w-0 flex-col rounded-2xl border bg-card">
            <header className="border-b p-3 md:p-4">
                <Link href={inboxUrl} className="mb-2 inline-flex items-center gap-1 text-sm font-semibold text-violet lg:hidden">
                    <ArrowLeft className="size-4" aria-hidden /> Alle berichten
                </Link>
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                        <h2 className="font-heading text-lg leading-tight font-extrabold break-words">{gesprek.title}</h2>
                        <p className="text-sm text-muted-foreground">
                            {gesprek.number} · {omgeving === 'creator' ? gesprek.company : [gesprek.company, gesprek.creator].filter(Boolean).join(' met ')}, {gesprek.location}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={gesprek.status} />
                        <Link href={gesprek.url} className="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-semibold hover:border-violet">
                            Open samenwerking <ExternalLink className="size-3.5" aria-hidden />
                        </Link>
                    </div>
                </div>
                {gesprek.channels.length > 1 && (
                    <div className="mt-3 flex flex-wrap gap-1.5" role="tablist" aria-label="Gesprekken in deze samenwerking">
                        {gesprek.channels.map((k) => (
                            <button
                                key={k.key}
                                type="button"
                                role="tab"
                                aria-selected={k.key === kanaal?.key}
                                onClick={() => setKanaalKey(k.key)}
                                className="rounded-full border px-3 py-1.5 text-sm font-semibold transition aria-selected:border-aubergine aria-selected:bg-aubergine aria-selected:text-white dark:aria-selected:bg-violet"
                                data-test={`kanaal-${k.key}`}
                            >
                                {k.name}
                                <span className="ml-1 font-normal opacity-70">{k.messages.length}</span>
                            </button>
                        ))}
                    </div>
                )}
            </header>
            {kanaal && <Kanaalvenster key={kanaal.key} kanaal={kanaal} postUrl={gesprek.post_url} company={gesprek.company} />}
        </section>
    );
}

function laatste(k: Kanaal): string | undefined {
    return k.messages[k.messages.length - 1]?.at;
}

function Kanaalvenster({ kanaal, postUrl, company }: { kanaal: Kanaal; postUrl: string; company: string }) {
    const form = useForm({ channel: kanaal.key, body: '' });
    const eind = useRef<HTMLDivElement>(null);

    useEffect(() => {
        eind.current?.scrollIntoView({ block: 'nearest' });
    }, [kanaal.messages.length]);

    const sturen = (e?: React.FormEvent) => {
        e?.preventDefault();
        if (!form.data.body.trim()) {
            return;
        }
        form.post(postUrl, { preserveScroll: true, onSuccess: () => form.reset('body') });
    };

    return (
        <>
            <div className="max-h-[60vh] min-h-64 flex-1 space-y-3 overflow-y-auto p-3 md:p-4" aria-live="polite" data-test="berichten">
                {kanaal.messages.length === 0 && (
                    <p className="py-10 text-center text-sm text-muted-foreground">Nog geen berichten. Stel hier je vraag of deel een update.</p>
                )}
                {kanaal.messages.map((b, i) => (
                    <Ballon key={b.id} bericht={b} vorige={kanaal.messages[i - 1]} company={company} />
                ))}
                <div ref={eind} />
            </div>
            <form onSubmit={sturen} className="border-t p-3 md:p-4">
                {kanaal.can_write ? (
                    <>
                        <label htmlFor={`bericht-${kanaal.key}`} className="sr-only">
                            Bericht
                        </label>
                        <div className="flex items-end gap-2">
                            <textarea
                                id={`bericht-${kanaal.key}`}
                                rows={2}
                                value={form.data.body}
                                onChange={(e) => form.setData('body', e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                                        sturen();
                                    }
                                }}
                                placeholder="Schrijf een bericht"
                                className={`${tekstvak} min-h-12 resize-y`}
                                maxLength={4000}
                            />
                            <Button type="submit" size="icon" className="size-12 shrink-0 rounded-xl bg-violet hover:bg-violet/90" disabled={form.processing || !form.data.body.trim()} aria-label="Versturen">
                                <Send className="size-5" aria-hidden />
                            </Button>
                        </div>
                        {(form.errors.body || form.errors.channel) && <p className="mt-1 text-sm text-rood">{form.errors.body ?? form.errors.channel}</p>}
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">Dit gesprek opent zodra er een creator gekoppeld is.</p>
                )}
                <p className="mt-2 text-xs text-muted-foreground">{kanaal.visibility}</p>
            </form>
        </>
    );
}

const rolNaam = (b: Bericht, company: string) => ({ team: 'MEBRANDING', ondernemer: company, creator: 'Creator', systeem: '' })[b.role];

function Ballon({ bericht, vorige, company }: { bericht: Bericht; vorige?: Bericht; company: string }) {
    const dag = new Date(bericht.at).toDateString();
    const nieuweDag = !vorige || new Date(vorige.at).toDateString() !== dag;

    return (
        <>
            {nieuweDag && (
                <p className="py-1 text-center text-xs font-semibold text-muted-foreground">
                    {new Date(bericht.at).toLocaleDateString('nl-NL', { timeZone: 'Europe/Amsterdam', weekday: 'long', day: 'numeric', month: 'long' })}
                </p>
            )}
            <div className={cn('flex gap-2', bericht.mine && 'flex-row-reverse')} data-test="bericht">
                {!bericht.mine && <Initialen naam={bericht.author} className="size-8" />}
                <div className={cn('max-w-[85%] min-w-0 md:max-w-[70%]', bericht.mine && 'text-right')}>
                    {!bericht.mine && (
                        <p className="mb-0.5 text-xs text-muted-foreground">
                            <span className="font-semibold text-foreground">{bericht.author}</span>
                            {rolNaam(bericht, company) && `, ${rolNaam(bericht, company)}`}
                        </p>
                    )}
                    <p
                        className={cn(
                            'rounded-2xl px-3.5 py-2.5 text-left text-[15px] leading-snug break-words whitespace-pre-line',
                            bericht.mine ? 'rounded-br-md bg-violet text-white' : 'rounded-bl-md bg-muted',
                        )}
                    >
                        {bericht.body}
                    </p>
                    <p className="mt-0.5 text-[11px] text-muted-foreground">
                        {new Date(bericht.at).toLocaleTimeString('nl-NL', { timeZone: 'Europe/Amsterdam', hour: '2-digit', minute: '2-digit' })}
                    </p>
                </div>
            </div>
        </>
    );
}
