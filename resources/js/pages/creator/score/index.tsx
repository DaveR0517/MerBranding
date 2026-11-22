import { Head, Link } from '@inertiajs/react';
import { BarChart3, Sparkles } from 'lucide-react';
import { datum } from '@/lib/datum';
import { klus, score as scoreRoute } from '@/routes/creator';
import { Kaart, Kop } from '../_onderdelen/opmaak';
import { Balk, getal, scoreKleur, Verloop } from './_onderdelen/score';
import type { Deelscore, Drempel } from './_onderdelen/score';

type Props = {
    creator: { name: string; first_name: string; level: string; since: string | null };
    score: {
        total: number;
        jobs_count: number;
        voorlopig: boolean;
        min_jobs: number;
        startscore: number;
        new_talent: boolean;
        new_talent_until: string | null;
        period_days: number;
        calculated_at: string;
    };
    deelscores: Deelscore[];
    next_level: { name: string; score: number; jobs: number; points_to_go: number; progress: number } | null;
    gevaar: { herberekening: string; drempel: number; laagste: string | null } | null;
    verloop: { date: string; total: number }[];
    klussen: { id: number; company: string; location: string; status: string; date: string; total: number | null; deelscores: Record<string, number | null> }[];
    events: { id: number; kind: string; label: string; reason: string; points: number; company: string | null; date: string }[];
    klassement: { soort: string; positie: number | null; aantal: number; top: { positie: number; naam: string; score: number; ik: boolean }[] };
    drempels: Drempel[];
    herberekening: string;
};

export default function CreatorScore({ creator, score, deelscores, next_level, gevaar, verloop, klussen, events, klassement, drempels, herberekening }: Props) {
    const herverdeeld = deelscores.some((d) => d.value === null);
    const strafpunten = events.filter((e) => e.kind !== 'beoordeling');

    return (
        <>
            <Head title="Jouw score" />
            <Kop
                boven="Score"
                titel="Jouw score"
                onder={
                    <span className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span className="font-heading text-5xl leading-none font-black tracking-tight" data-test="totaalscore">
                            {getal(score.total)}
                        </span>
                        <span className="flex flex-col gap-1.5">
                            <span className="flex flex-wrap gap-1.5">
                                <span className="rounded-md bg-white px-2 py-0.5 text-xs font-extrabold tracking-wider text-aubergine-dark uppercase">{creator.level}</span>
                                {score.voorlopig && <span className="rounded-md bg-white/15 px-2 py-0.5 text-xs font-bold">Voorlopig</span>}
                                {score.new_talent && <span className="rounded-md bg-white/15 px-2 py-0.5 text-xs font-bold">Nieuw talent</span>}
                                {gevaar && <span className="rounded-md bg-rood px-2 py-0.5 text-xs font-bold">Niveau in gevaar</span>}
                            </span>
                            <span className="text-xs text-white/80">
                                {score.voorlopig
                                    ? 'Startscore voor nieuwe creators'
                                    : next_level
                                      ? `Nog ${getal(next_level.points_to_go)} punten tot de scoregrens van ${next_level.name}`
                                      : `${score.period_days}-dagenscore, hoogste niveau`}
                            </span>
                        </span>
                    </span>
                }
            >
                {next_level && !score.voorlopig && (
                    <div>
                        <span
                            role="progressbar"
                            aria-label={`Voortgang naar ${next_level.name}`}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={next_level.progress}
                            className="block h-1.5 rounded-full bg-white/20"
                        >
                            <span className="block h-1.5 rounded-full bg-gradient-to-r from-[#9C8CFF] to-white" style={{ width: `${next_level.progress}%` }} />
                        </span>
                        <span className="mt-1 flex justify-between text-[10px] font-bold tracking-wider text-white/70 uppercase">
                            <span>{creator.level}</span>
                            <span>{next_level.name}</span>
                        </span>
                    </div>
                )}
            </Kop>

            <div className="mx-auto w-full max-w-3xl space-y-4 px-4 py-5 md:px-6">
                {gevaar && (
                    <Kaart accent className="border-l-rood!">
                        <p className="text-xs font-bold tracking-wider text-rood uppercase">Niveau in gevaar</p>
                        <h2 className="mt-1 font-heading text-lg font-extrabold">Je {creator.level}-niveau staat onder druk</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Op {datum(gevaar.herberekening)} rekenen we de niveaus opnieuw uit (voorstel). Je {score.period_days}-dagenscore ligt nu onder de grens van {gevaar.drempel}. Je zakt
                            pas als je 2 metingen achter elkaar onder de grens blijft, en altijd beslist eerst iemand van het team.
                        </p>
                        <dl className="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                            <dt className="text-muted-foreground">Laagste deelscore</dt>
                            <dd className="font-semibold">{gevaar.laagste ?? 'Onbekend'}</dd>
                            <dt className="text-muted-foreground">Lopende samenwerkingen</dt>
                            <dd className="font-semibold">Houden hun vergoeding</dd>
                        </dl>
                    </Kaart>
                )}

                {score.voorlopig && (
                    <Kaart accent>
                        <p className="flex justify-between text-xs font-bold tracking-wider text-violet uppercase">
                            <span>Voorlopig</span>
                            <span>
                                {score.jobs_count} van {score.min_jobs} samenwerkingen
                            </span>
                        </p>
                        <h2 className="mt-1 font-heading text-lg font-extrabold">Je start met een gemiddelde, {creator.first_name}</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Tot je {score.min_jobs} samenwerkingen hebt in de laatste {score.period_days} dagen, telt een startscore van {getal(score.startscore)} mee. Daarna bepaalt
                            alleen jouw eigen werk je score en vervalt het label Voorlopig.
                        </p>
                        <div className="mt-3">
                            <Balk waarde={Math.max(4, (score.jobs_count / score.min_jobs) * 100)} label="Afgeronde samenwerkingen" />
                        </div>
                    </Kaart>
                )}

                {score.new_talent && (
                    <Kaart titel="Nieuw talent">
                        <p className="text-sm text-muted-foreground">
                            De eerste 90 dagen{score.new_talent_until ? `, tot ${datum(score.new_talent_until)},` : ''} sta je in het klassement Nieuw talent. Zo vergelijk je je met
                            creators die ook net begonnen zijn.
                        </p>
                    </Kaart>
                )}

                <Kaart titel="Deelscores">
                    <p className="-mt-1 mb-3 text-sm text-muted-foreground">
                        Je score is het gewogen gemiddelde van vijf deelscores over je samenwerkingen van de laatste {score.period_days} dagen.
                    </p>
                    <ul className="space-y-4" data-test="deelscores">
                        {deelscores.map((d) => (
                            <li key={d.key} className="space-y-1.5">
                                <p className="flex items-baseline justify-between gap-3">
                                    <span className="font-bold">{d.name}</span>
                                    <span className={`text-sm font-bold tabular-nums ${scoreKleur(d.value)}`}>{getal(d.value)}</span>
                                </p>
                                <Balk waarde={d.value} label={d.name} />
                                <p className="text-xs text-muted-foreground">
                                    Gewicht {d.weight}%
                                    {d.effective !== null && d.effective !== d.weight ? `, nu ${getal(d.effective)}% na herverdeling` : ''}
                                    {d.value === null ? ', telt nu niet mee' : ''}. {d.uitleg}
                                </p>
                            </li>
                        ))}
                    </ul>
                    {herverdeeld && (
                        <p role="note" className="mt-4 rounded-lg border-l-4 border-violet bg-violet/5 px-3 py-2 text-sm">
                            Waar nog geen cijfers zijn (bijvoorbeeld geen advertentie), telt die deelscore niet mee. Het gewicht gaat naar verhouding naar de andere deelscores.
                        </p>
                    )}
                </Kaart>

                <Kaart titel="Verloop">
                    <Verloop punten={verloop} />
                </Kaart>

                {klussen.length > 0 && (
                    <Kaart titel="Wat meetelt">
                        <ul className="divide-y">
                            {klussen.map((k) => (
                                <li key={k.id} className="py-2.5 first:pt-0 last:pb-0">
                                    <Link href={klus(k.id)} className="flex items-center justify-between gap-3 hover:text-violet">
                                        <span className="min-w-0">
                                            <span className="block font-semibold break-words">
                                                {k.company}, {k.location}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {k.status} op {datum(k.date)}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-sm font-bold tabular-nums">{getal(k.total)}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Kaart>
                )}

                <Kaart titel="Wat telt en wat niet">
                    <ul className="space-y-2 text-sm">
                        <li>
                            <b>Telt mee:</b> afgeronde samenwerkingen (cijfers na 14 dagen en de beoordeling van de ondernemer) en of je op tijd bent, op tijd levert en op de afgesproken datum
                            plaatst.
                        </li>
                        <li>
                            <b>Telt mee op Betrouwbaarheid:</b> niet komen nadat je een klus hebt aangenomen.
                        </li>
                        <li>
                            <b>Telt niet mee:</b> een uitnodiging weigeren of laten verlopen. Dat heeft nooit gevolgen voor je score.
                        </li>
                        <li>
                            <b>Telt niet mee:</b> een ondernemer die er niet was, of een verbroken koppeling. Dat is een signaal voor het team, geen strafpunt.
                        </li>
                    </ul>
                </Kaart>

                <Kaart>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="font-heading text-lg font-extrabold">Strafpunten</h2>
                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Strafregeling: nog te bespreken</span>
                    </div>
                    {strafpunten.length === 0 ? (
                        <p className="mt-2 text-sm text-muted-foreground">Je hebt geen strafpunten.</p>
                    ) : (
                        <ul className="mt-2 divide-y text-sm" data-test="strafpunten">
                            {strafpunten.map((e) => (
                                <li key={e.id} className="flex items-start justify-between gap-3 py-2">
                                    <span className="min-w-0">
                                        <span className="block font-semibold">{e.label}</span>
                                        <span className="block text-xs text-muted-foreground">
                                            {[e.company, e.reason, datum(e.date)].filter(Boolean).join(', ')}
                                        </span>
                                    </span>
                                    <span className="shrink-0 font-bold text-rood tabular-nums">{e.points}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="mt-2 text-xs text-muted-foreground">
                        Een strafpunt op Betrouwbaarheid ontstaat alleen als je niet kwam na toezegging, te laat leverde of plaatste, of je post te vroeg weghaalde. Hoe zwaar het telt en
                        wanneer het vervalt, is nog niet bepaald: de getallen zijn een voorstel.
                    </p>
                </Kaart>

                <Kaart>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="flex items-center gap-2 font-heading text-lg font-extrabold">
                            <BarChart3 className="size-5 text-violet" aria-hidden />
                            Klassement
                        </h2>
                        <span className="text-xs text-muted-foreground">{klassement.soort}</span>
                    </div>
                    {klassement.top.length === 0 ? (
                        <p className="mt-2 text-sm text-muted-foreground">Je positie verschijnt na de eerste herberekening.</p>
                    ) : (
                        <ol className="mt-2 space-y-0.5 text-sm" data-test="klassement">
                            {klassement.top.map((r) => (
                                <li
                                    key={r.positie}
                                    aria-current={r.ik || undefined}
                                    className={`grid grid-cols-[1.5rem_1fr_auto] items-center gap-2 rounded-lg px-2 py-1.5 ${r.ik ? 'bg-violet font-bold text-white' : ''}`}
                                >
                                    <span className={r.ik ? '' : 'font-bold text-muted-foreground'}>{r.positie}</span>
                                    <span className="truncate">{r.ik ? `Jij, ${creator.first_name}` : r.naam}</span>
                                    <span className="font-semibold tabular-nums">{getal(r.score)}</span>
                                </li>
                            ))}
                        </ol>
                    )}
                    {klassement.positie !== null && klassement.positie > 5 && (
                        <p className="mt-2 rounded-lg bg-violet px-2 py-1.5 text-sm font-bold text-white">
                            Jij staat op plaats {klassement.positie} van {klassement.aantal}
                        </p>
                    )}
                </Kaart>

                <details className="rounded-2xl border bg-card p-4 text-sm md:p-5">
                    <summary className="cursor-pointer font-heading font-bold">Hoe werkt de score?</summary>
                    <div className="mt-3 space-y-2 text-muted-foreground">
                        <p>
                            Je score is een gewogen gemiddelde van de vijf deelscores over je samenwerkingen van de laatste {score.period_days} dagen. Een hoger niveau geeft voorrang bij
                            nieuwe uitnodigingen.
                        </p>
                        <p>
                            Elke 1e van de maand kijkt het systeem of je de grens van een ander niveau haalt (voorstel, volgende keer op {datum(herberekening)}). Het systeem doet dan alleen
                            een voorstel: iemand van het team beslist en je krijgt bericht. Omlaag pas na 2 metingen achter elkaar onder de grens.
                        </p>
                        <p className="flex items-center gap-1.5 font-semibold text-foreground">
                            <Sparkles className="size-4 text-violet" aria-hidden />
                            Niveaugrenzen (voorstel)
                        </p>
                        <ul className="space-y-1">
                            {drempels.map((d) => (
                                <li key={d.value}>
                                    <b className="text-foreground">{d.name}</b>: {d.score === 0 ? 'start na screening' : `score ${d.score} of hoger`}
                                    {d.jobs > 0 ? `, minimaal ${d.jobs} samenwerkingen` : ''}
                                    {d.quality ? `, Klantoordeel ${d.quality} of hoger` : ''}
                                </li>
                            ))}
                        </ul>
                    </div>
                </details>
            </div>
        </>
    );
}

CreatorScore.layout = {
    breadcrumbs: [{ title: 'Score', href: scoreRoute() }],
};
