import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Kaart, Pagina, PaginaKop } from '@/components/pagina';
import { Button } from '@/components/ui/button';
import { datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import { Initialen, VraagStatus, tekstvak } from './opmaak';
import type { Vraag } from './types';

/** Eén supportvraag zoals de vraagsteller hem ziet, met de reacties van het team. */
export function VraagScherm({
    ticket,
    helpUrl,
    reagerenUrl,
}: {
    ticket: Vraag;
    helpUrl: string;
    reagerenUrl: string;
}) {
    const form = useForm({ body: '' });

    return (
        <Pagina smal>
            <Link
                href={helpUrl}
                className="inline-flex min-h-10 items-center gap-1 text-sm font-semibold text-violet"
            >
                <ArrowLeft className="size-4" aria-hidden /> Terug naar Help
            </Link>
            <PaginaKop
                boven={ticket.number}
                titel={ticket.subject}
                omschrijving={
                    ticket.collaboration
                        ? `Over ${ticket.collaboration.number} ${ticket.collaboration.title}`
                        : undefined
                }
                extra={<VraagStatus vraag={ticket} />}
            />

            <ol className="space-y-3" data-test="reacties">
                {ticket.replies.map((r) => (
                    <li
                        key={r.id}
                        className={cn(
                            'rounded-xl border p-4 md:p-5',
                            r.mine_side
                                ? 'bg-card'
                                : 'border-violet/30 bg-violet/5',
                        )}
                    >
                        <p className="mb-2 flex items-center gap-2 text-sm">
                            <Initialen naam={r.author} className="size-7" />
                            <span className="font-semibold">
                                {r.mine_side
                                    ? 'Jij'
                                    : `${r.author}, MEBRANDING`}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {datumTijd(r.at)}
                            </span>
                        </p>
                        <p className="text-[15px] leading-relaxed whitespace-pre-line">
                            {r.body}
                        </p>
                    </li>
                ))}
            </ol>

            {ticket.status === 'gesloten' ? (
                <p className="rounded-xl border border-dashed bg-card p-4 text-sm text-muted-foreground">
                    Deze vraag is gesloten. Reageer hieronder als je toch nog
                    iets wilt weten; dan gaat hij weer open.
                </p>
            ) : null}
            <Kaart>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(reagerenUrl, {
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        });
                    }}
                    className="space-y-2"
                >
                    <label htmlFor="reactie" className="text-sm font-semibold">
                        Reageren
                    </label>
                    <textarea
                        id="reactie"
                        rows={3}
                        required
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        className={tekstvak}
                    />
                    {form.errors.body && (
                        <p className="text-sm text-rood">{form.errors.body}</p>
                    )}
                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="bg-violet hover:bg-violet/90"
                    >
                        Versturen
                    </Button>
                </form>
            </Kaart>
        </Pagina>
    );
}
