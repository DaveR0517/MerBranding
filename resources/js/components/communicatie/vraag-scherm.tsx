import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import { Initialen, VraagStatus, tekstvak } from './opmaak';
import type { Vraag } from './types';

/** Eén supportvraag zoals de vraagsteller hem ziet, met de reacties van het team. */
export function VraagScherm({ ticket, helpUrl, reagerenUrl }: { ticket: Vraag; helpUrl: string; reagerenUrl: string }) {
    const form = useForm({ body: '' });

    return (
        <div className="mx-auto w-full max-w-3xl space-y-4 p-4 md:p-6">
            <Link href={helpUrl} className="inline-flex items-center gap-1 text-sm font-semibold text-violet">
                <ArrowLeft className="size-4" aria-hidden /> Terug naar Help
            </Link>
            <header className="space-y-1">
                <p className="text-xs font-bold tracking-wider text-muted-foreground uppercase">{ticket.number}</p>
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="font-heading text-2xl font-extrabold break-words">{ticket.subject}</h1>
                    <VraagStatus vraag={ticket} />
                </div>
                {ticket.collaboration && (
                    <p className="text-sm text-muted-foreground">
                        Over {ticket.collaboration.number} {ticket.collaboration.title}
                    </p>
                )}
            </header>

            <ol className="space-y-3" data-test="reacties">
                {ticket.replies.map((r) => (
                    <li key={r.id} className={cn('rounded-2xl border p-4', r.mine_side ? 'bg-card' : 'border-violet/30 bg-violet/5')}>
                        <p className="mb-2 flex items-center gap-2 text-sm">
                            <Initialen naam={r.author} className="size-7" />
                            <span className="font-semibold">{r.mine_side ? 'Jij' : `${r.author}, MEBRANDING`}</span>
                            <span className="text-xs text-muted-foreground">{datumTijd(r.at)}</span>
                        </p>
                        <p className="text-[15px] leading-relaxed whitespace-pre-line">{r.body}</p>
                    </li>
                ))}
            </ol>

            {ticket.status === 'gesloten' ? (
                <p className="rounded-2xl border border-dashed bg-card p-4 text-sm text-muted-foreground">
                    Deze vraag is gesloten. Reageer hieronder als je toch nog iets wilt weten; dan gaat hij weer open.
                </p>
            ) : null}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(reagerenUrl, { preserveScroll: true, onSuccess: () => form.reset() });
                }}
                className="space-y-2 rounded-2xl border bg-card p-4"
            >
                <label htmlFor="reactie" className="text-sm font-semibold">
                    Reageren
                </label>
                <textarea id="reactie" rows={3} required value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} className={tekstvak} />
                {form.errors.body && <p className="text-sm text-rood">{form.errors.body}</p>}
                <Button type="submit" disabled={form.processing} className="bg-violet hover:bg-violet/90">
                    Versturen
                </Button>
            </form>
        </div>
    );
}
