import { router } from '@inertiajs/react';
import { Check, Clock, Plus, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { FeedbackLijst, VideoSpeler, tijdcode } from '@/components/samenwerking/video-speler';
import type { VideoSpelerHandle } from '@/components/samenwerking/video-speler';
import { Button } from '@/components/ui/button';
import { datum, datumTijd } from '@/lib/datum';
import * as team from '@/routes/team';
import type { SamenwerkingVol, Versie } from '@/types/samenwerking';
import { Label, Leeg, Paneel, invoer, morgen, vandaag } from './basis';

type Punt = { timecode: number | null; body: string };

function Reclamecheck({ check }: { check: Versie['label_check'] }) {
    const onderdelen: [string, boolean][] = [
        ['Bijschrift begint met de vermelding', check.bijschrift],
        ['Platformlabel staat aan', check.platformlabel],
        ['Tekst in beeld', check.tekst_in_beeld],
    ];

    return (
        <div
            className={`rounded-xl border p-3 ${check.geslaagd ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rood/30 bg-rood/5 text-rood'}`}
            data-test="reclamecheck"
        >
            <p className="font-semibold">{check.geslaagd ? 'Reclamevermelding in orde' : 'Reclamevermelding niet compleet'}</p>
            <ul className="mt-2 space-y-1 text-sm">
                {onderdelen.map(([naam, ok]) => (
                    <li key={naam} className="flex items-center gap-2">
                        {ok ? (
                            <Check className="size-4 text-emerald-700" aria-label="Ja" />
                        ) : (
                            <X className="size-4 text-rood" aria-label="Nee" />
                        )}
                        <span className={ok ? 'text-foreground' : ''}>{naam}</span>
                    </li>
                ))}
            </ul>
            {!check.geslaagd && <p className="mt-2 text-sm">{check.uitleg}</p>}
        </div>
    );
}

export function Contentcontrole({ c, magControleren }: { c: SamenwerkingVol; magControleren: boolean }) {
    const speler = useRef<VideoSpelerHandle>(null);
    const laatste = c.versions[c.versions.length - 1];
    const eerdere = c.versions.slice(0, -1).reverse();
    const [punten, setPunten] = useState<Punt[]>([]);
    const [tekst, setTekst] = useState('');
    const [tijd, setTijd] = useState<number | null>(null);
    const [publicatie, setPublicatie] = useState(morgen());
    const [bezig, setBezig] = useState(false);

    if (!laatste) {
        return <Leeg>De creator heeft nog geen video ingediend.</Leeg>;
    }

    const actief = magControleren && c.status.value === 'ter_controle';
    const vanOndernemer = laatste.feedback.filter((f) => f.from_owner);
    const vanTeam = laatste.feedback.filter((f) => !f.from_owner);

    const voegToe = () => {
        if (!tekst.trim()) {
            return;
        }
        setPunten([...punten, { timecode: tijd, body: tekst.trim() }].sort((a, b) => (a.timecode ?? -1) - (b.timecode ?? -1)));
        setTekst('');
        setTijd(null);
    };

    const opties = { preserveScroll: true, onStart: () => setBezig(true), onFinish: () => setBezig(false) };

    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
            <div className="space-y-3">
                <Paneel
                    titel={`Versie ${laatste.version}`}
                    sub={`Ingediend ${datumTijd(laatste.submitted_at)}`}
                    acties={
                        laatste.outcome === 'goedgekeurd' ? (
                            <Label kleur="groen">Goedgekeurd</Label>
                        ) : laatste.outcome === 'feedback' ? (
                            <Label kleur="amber">Feedback gegeven</Label>
                        ) : (
                            <Label kleur="violet">Te beoordelen</Label>
                        )
                    }
                >
                    <VideoSpeler ref={speler} versie={laatste} />
                </Paneel>
            </div>

            <div className="space-y-4">
                <Paneel titel="Bijschrift en reclamevermelding">
                    <p className="mb-3 rounded-xl bg-muted/60 p-3 text-sm whitespace-pre-line">{laatste.caption}</p>
                    <Reclamecheck check={laatste.label_check} />
                </Paneel>

                {vanOndernemer.length > 0 && (
                    <Paneel titel="Opmerkingen van de ondernemer" sub="Alleen voor het team. Neem over wat klopt in je eigen feedback.">
                        <FeedbackLijst punten={vanOndernemer} onKlik={(s) => speler.current?.naar(s)} />
                    </Paneel>
                )}

                {vanTeam.length > 0 && (
                    <Paneel titel="Feedback op deze versie">
                        <FeedbackLijst punten={vanTeam} onKlik={(s) => speler.current?.naar(s)} />
                    </Paneel>
                )}

                {actief && (
                    <Paneel titel="Feedback geven" sub="Speel de video af, zet hem stil en leg het punt vast op die tijd.">
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setTijd(speler.current?.tijd() ?? 0)}
                                    data-test="huidige-tijd"
                                >
                                    <Clock /> Huidige tijd
                                </Button>
                                <span className="rounded bg-violet/10 px-2 py-1 font-mono text-xs font-semibold text-violet" data-test="gekozen-tijd">
                                    {tijdcode(tijd)}
                                </span>
                                {tijd !== null && (
                                    <button type="button" className="text-xs text-muted-foreground underline" onClick={() => setTijd(null)}>
                                        Algemeen punt
                                    </button>
                                )}
                            </div>
                            <label htmlFor="feedback-tekst" className="sr-only">
                                Opmerking
                            </label>
                            <textarea
                                id="feedback-tekst"
                                value={tekst}
                                onChange={(e) => setTekst(e.target.value)}
                                rows={2}
                                placeholder="Bijvoorbeeld: laat de naam op het raam in beeld komen."
                                className={`${invoer} h-auto py-2`}
                            />
                            <Button type="button" variant="outline" size="sm" onClick={voegToe} disabled={!tekst.trim()}>
                                <Plus /> Punt toevoegen
                            </Button>
                        </div>

                        {punten.length > 0 && (
                            <ul className="mt-3 space-y-2" data-test="punten">
                                {punten.map((p, i) => (
                                    <li key={i} className="flex items-start gap-2 rounded-lg border bg-background p-2.5 text-sm">
                                        <button
                                            type="button"
                                            onClick={() => p.timecode !== null && speler.current?.naar(p.timecode)}
                                            className="rounded bg-violet/10 px-1.5 py-0.5 font-mono text-xs font-semibold text-violet"
                                        >
                                            {tijdcode(p.timecode)}
                                        </button>
                                        <span className="flex-1">{p.body}</span>
                                        <button
                                            type="button"
                                            onClick={() => setPunten(punten.filter((_, j) => j !== i))}
                                            className="text-muted-foreground hover:text-rood"
                                            aria-label="Punt verwijderen"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <Button
                            type="button"
                            className="mt-3 w-full bg-aubergine text-white hover:bg-aubergine-dark sm:w-auto"
                            disabled={punten.length === 0 || bezig}
                            onClick={() =>
                                router.post(team.feedback.url(c.id), { points: punten }, { ...opties, onSuccess: () => setPunten([]) })
                            }
                        >
                            Feedback versturen ({punten.length})
                        </Button>
                        <p className="mt-2 text-xs text-muted-foreground">
                            De creator krijgt de punten en maakt een nieuwe versie. De status gaat naar Feedback.
                        </p>
                    </Paneel>
                )}

                {actief && (
                    <Paneel titel="Goedkeuren" className="border-violet/40">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="space-y-1.5">
                                <label htmlFor="publish_on" className="text-sm font-semibold">
                                    Publicatiedatum
                                </label>
                                <input
                                    id="publish_on"
                                    type="date"
                                    min={vandaag()}
                                    value={publicatie}
                                    onChange={(e) => setPublicatie(e.target.value)}
                                    className={`${invoer} w-auto`}
                                />
                            </div>
                            <Button
                                type="button"
                                className="bg-violet text-white hover:bg-violet/90"
                                disabled={!publicatie || bezig || punten.length > 0}
                                onClick={() => router.post(team.goedkeuren.url(c.id), { publish_on: publicatie }, opties)}
                            >
                                <Check /> Goedkeuren
                            </Button>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Na goedkeuren krijgt de ondernemer de video zonder watermerk om te downloaden en een melding. De creator hoort
                            dat de video goed is en zet hem op {publicatie ? datum(`${publicatie}T12:00:00`) : 'de gekozen datum'} online.
                        </p>
                        {punten.length > 0 && (
                            <p className="mt-1 text-sm text-rood">Je hebt nog feedbackpunten klaarstaan. Verstuur of verwijder ze eerst.</p>
                        )}
                    </Paneel>
                )}

                {!actief && c.status.value === 'ter_controle' && (
                    <p className="text-sm text-muted-foreground">Contentcontrole doet een collega met het recht om content te controleren.</p>
                )}

                {eerdere.length > 0 && (
                    <Paneel titel="Eerdere versies">
                        <ul className="space-y-3">
                            {eerdere.map((v) => (
                                <li key={v.id} className="rounded-xl border bg-background p-3">
                                    <p className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <span className="font-semibold">Versie {v.version}</span>
                                        <span className="text-muted-foreground">{datumTijd(v.submitted_at)}</span>
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">{v.caption}</p>
                                    <a href={v.url} target="_blank" rel="noreferrer" className="mt-1 inline-block text-sm font-semibold text-violet underline">
                                        Video bekijken
                                    </a>
                                    <div className="mt-2">
                                        {v.feedback.length > 0 ? (
                                            <FeedbackLijst punten={v.feedback} />
                                        ) : (
                                            <p className="text-sm text-muted-foreground">Geen feedback.</p>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Paneel>
                )}
            </div>
        </div>
    );
}
