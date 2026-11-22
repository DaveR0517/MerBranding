import { useForm } from '@inertiajs/react';
import { Lightbulb, Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { datum } from '@/lib/datum';
import { Etiket } from './labels';
import type { Idee } from './types';

export const ideeStatus: Record<
    Idee['status'],
    { naam: string; kleur: 'violet' | 'amber' | 'groen' }
> = {
    idee: { naam: 'Idee', kleur: 'violet' },
    gepland: { naam: 'Gepland', kleur: 'amber' },
    gebruikt: { naam: 'Gebruikt', kleur: 'groen' },
};

/** Formulier voor een nieuw video-idee, voor ondernemer en team. */
export function IdeeFormulier({
    url,
    uitleg,
}: {
    url: string;
    uitleg: string;
}) {
    const form = useForm({ title: '', description: '' });

    function opslaan(e: FormEvent) {
        e.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <form
            onSubmit={opslaan}
            className="space-y-3 rounded-2xl border bg-card p-4"
            noValidate
        >
            <h2 className="font-heading text-lg font-bold">Nieuw idee</h2>
            <p className="text-sm text-muted-foreground">{uitleg}</p>
            <div className="grid gap-1.5">
                <label htmlFor="idee-titel" className="text-sm font-semibold">
                    Titel
                </label>
                <Input
                    id="idee-titel"
                    value={form.data.title}
                    maxLength={120}
                    onChange={(e) => form.setData('title', e.target.value)}
                    placeholder="Bijvoorbeeld: Van pan tot kom"
                    aria-invalid={!!form.errors.title}
                />
                <InputError message={form.errors.title} />
            </div>
            <div className="grid gap-1.5">
                <label
                    htmlFor="idee-omschrijving"
                    className="text-sm font-semibold"
                >
                    Omschrijving (optioneel)
                </label>
                <textarea
                    id="idee-omschrijving"
                    rows={3}
                    maxLength={2000}
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Wat zie je voor je? Welk gerecht, welk moment, welke hook?"
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-violet focus-visible:ring-2 focus-visible:ring-violet/30 md:text-sm"
                />
            </div>
            <Button
                type="submit"
                className="min-h-11 w-full bg-violet hover:bg-violet/90 sm:w-auto"
                disabled={form.processing}
            >
                <Plus aria-hidden />
                Idee bewaren
            </Button>
        </form>
    );
}

export function IdeeenLijst({
    ideas,
    actie,
    team = false,
}: {
    ideas: Idee[];
    actie?: (i: Idee) => ReactNode;
    team?: boolean;
}) {
    if (ideas.length === 0) {
        return (
            <p className="rounded-2xl border border-dashed bg-card p-6 text-center text-sm text-muted-foreground">
                Nog geen ideeën. Voeg je eerste idee toe.
            </p>
        );
    }

    return (
        <ul className="space-y-3" data-test="ideeen">
            {ideas.map((i) => (
                <li key={i.id} className="rounded-2xl border bg-card p-4">
                    <div className="flex items-start gap-3">
                        <span
                            className="grid size-9 shrink-0 place-items-center rounded-full bg-violet/10 text-violet"
                            aria-hidden
                        >
                            <Lightbulb className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <Etiket kleur={ideeStatus[i.status].kleur}>
                                    {ideeStatus[i.status].naam}
                                </Etiket>
                                <Etiket kleur="grijs">
                                    {i.source === 'team'
                                        ? 'Van het team'
                                        : team
                                          ? 'Van de ondernemer'
                                          : 'Van jou'}
                                </Etiket>
                            </div>
                            <h3 className="mt-1 font-semibold break-words">
                                {i.title}
                            </h3>
                            {i.description && (
                                <p className="mt-0.5 text-sm break-words whitespace-pre-line text-muted-foreground">
                                    {i.description}
                                </p>
                            )}
                            <p className="mt-1 text-xs text-muted-foreground">
                                {i.created_at &&
                                    `Toegevoegd ${datum(i.created_at)}`}
                                {i.collaboration &&
                                    `. Gebruikt bij ${i.collaboration.number} ${i.collaboration.title}`}
                            </p>
                        </div>
                    </div>
                    {actie && <div className="mt-3">{actie(i)}</div>}
                </li>
            ))}
        </ul>
    );
}
