import { Head, Link, useForm } from '@inertiajs/react';
import { Kaart, Pagina, PaginaKop } from '@/components/pagina';
import { datumTijd } from '@/lib/datum';
import { overzicht } from '@/routes/academy';
import { indienen } from '@/routes/academy/opdracht';
import {
    AanZet,
    TitelKaart,
    groteKnop,
    veld,
} from '../creator/_onderdelen/opmaak';
import {
    Beoordelingslijst,
    Ingediend,
    Uitslag,
    VideoOfLink,
} from './_onderdelen/onderdelen';
import type { Criterium, Inzending } from './_onderdelen/types';

type Props = {
    assignment: {
        id: number;
        title: string;
        description: string;
        criteria: Criterium[];
    };
    submissions: Inzending[];
};

export default function Opdracht({ assignment, submissions }: Props) {
    const form = useForm<{ link: string; video: File | null; note: string }>({
        link: '',
        video: null,
        note: '',
    });
    const fouten = form.errors as Record<string, string | undefined>;

    return (
        <>
            <Head title={assignment.title} />
            <Pagina smal>
                <PaginaKop
                    boven={
                        <Link
                            href={overzicht.url()}
                            className="hover:text-violet"
                        >
                            Mijn academy
                        </Link>
                    }
                    titel={assignment.title}
                    omschrijving="Oefenopdracht"
                />
                <Kaart>
                    <p className="whitespace-pre-line">
                        {assignment.description}
                    </p>
                    <h2 className="mt-4 mb-2 font-semibold">
                        Beoordelingslijst
                    </h2>
                    <Beoordelingslijst
                        criteria={assignment.criteria}
                        onder="Je docent geeft elk criterium 1 tot 5, met feedback."
                    />
                </Kaart>

                <TitelKaart
                    titel={
                        submissions.length
                            ? 'Nog een versie indienen'
                            : 'Indienen'
                    }
                >
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(indienen.url(assignment.id), {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            });
                        }}
                    >
                        <VideoOfLink
                            id="o"
                            label="Je video"
                            link={form.data.link}
                            bestand={form.data.video}
                            onLink={(v) => form.setData('link', v)}
                            onBestand={(f) => form.setData('video', f)}
                            fout={fouten.link ?? fouten.video}
                        />
                        <div>
                            <label
                                htmlFor="o-note"
                                className="mb-1 block text-sm font-semibold"
                            >
                                Toelichting (optioneel)
                            </label>
                            <textarea
                                id="o-note"
                                rows={3}
                                className={veld}
                                value={form.data.note}
                                onChange={(e) =>
                                    form.setData('note', e.target.value)
                                }
                            />
                        </div>
                        {form.progress && (
                            <p className="text-sm text-muted-foreground">
                                Uploaden: {form.progress.percentage}%
                            </p>
                        )}
                        <button
                            type="submit"
                            className={groteKnop}
                            disabled={form.processing}
                        >
                            Indienen
                        </button>
                    </form>
                </TitelKaart>

                {submissions.map((s) => (
                    <Kaart key={s.id}>
                        <AanZet>
                            {s.assessed_at
                                ? `Beoordeeld door ${s.assessor ?? 'je docent'}`
                                : 'Ligt bij je docent'}
                        </AanZet>
                        <p className="mb-2 text-sm text-muted-foreground">
                            Ingediend {datumTijd(s.created_at)}
                        </p>
                        <Ingediend link={s.link} video_url={s.video_url} />
                        {s.note && <p className="mt-2 text-sm">{s.note}</p>}
                        {s.scores && (
                            <div className="mt-3">
                                <Uitslag
                                    criteria={assignment.criteria}
                                    scores={s.scores}
                                />
                            </div>
                        )}
                        {s.feedback && (
                            <p className="mt-3 rounded-xl bg-muted/50 p-3 text-sm whitespace-pre-line">
                                {s.feedback}
                            </p>
                        )}
                    </Kaart>
                ))}
            </Pagina>
        </>
    );
}
