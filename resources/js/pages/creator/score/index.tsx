import { Head, Link } from '@inertiajs/react';
import { BarChart3, Sparkles } from 'lucide-react';
import {
    Kaart,
    KaartRaster,
    MeerLijst,
    Pagina,
    PaginaKop,
    Rij,
    Sectie,
    TweeKolommen,
    Uitklap,
} from '@/components/pagina';
import { datum } from '@/lib/datum';
import { klus, score as scoreRoute } from '@/routes/creator';
import { Balk, getal, scoreKleur, Verloop } from './_onderdelen/score';
import type { Deelscore, Drempel } from './_onderdelen/score';

type Props = {
    creator: {
        name: string;
        first_name: string;
        level: string;
        since: string | null;
    };
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
    next_level: {
        name: string;
        score: number;
        jobs: number;
        points_to_go: number;
        progress: number;
    } | null;
    gevaar: {
        herberekening: string;
        drempel: number;
        laagste: string | null;
    } | null;
    verloop: { date: string; total: number }[];
    klussen: {
        id: number;
        company: string;
        location: string;
        status: string;
        date: string;
        total: number | null;
        deelscores: Record<string, number | null>;
    }[];
    events: {
        id: number;
        kind: string;
        label: string;
        reason: string;
        points: number;
        company: string | null;
        date: string;
    }[];
    klassement: {
        soort: string;
        positie: number | null;
        aantal: number;
        top: { positie: number; naam: string; score: number; ik: boolean }[];
    };
    drempels: Drempel[];
    herberekening: string;
};

export default function CreatorScore({
    creator,
    score,
    deelscores,
    next_level,
    gevaar,
    verloop,
    klussen,
    events,
    klassement,
    drempels,
    herberekening,
}: Props) {
    const herverdeeld = deelscores.some((d) => d.value === null);
    const strafpunten = events.filter((e) => e.kind !== 'beoordeling');

    const hoofd = (
        <>
            <Sectie
                titel="Deelscores"
                omschrijving={`Gewogen gemiddelde over je samenwerkingen van de laatste ${score.period_days} dagen.`}
            >
                <Kaart>
                    <ul className="space-y-3" data-test="deelscores">
                        {deelscores.map((d) => (
                            <li key={d.key} className="space-y-1">
                                <p className="flex items-baseline justify-between gap-3">
                                    <span className="font-semibold">
                                        {d.name}
                                    </span>
                                    <span
                                        className={`text-sm font-bold tabular-nums ${scoreKleur(d.value)}`}
                                    >
                                        {getal(d.value)}
                                    </span>
                                </p>
                                <Balk waarde={d.value} label={d.name} />
                                <p className="text-xs text-muted-foreground tabular-nums">
                                    Gewicht {d.weight}%
                                    {d.effective !== null &&
                                    d.effective !== d.weight
                                        ? `, nu ${getal(d.effective)}% na herverdeling`
                                        : ''}
                                    {d.value === null
                                        ? ', telt nu niet mee'
                                        : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                    {herverdeeld && (
                        <p
                            role="note"
                            className="mt-3 rounded-lg border-l-4 border-violet bg-violet/5 px-3 py-2 text-sm"
                        >
                            Waar nog geen cijfers zijn (bijvoorbeeld geen
                            advertentie), telt die deelscore niet mee. Het
                            gewicht gaat naar verhouding naar de andere
                            deelscores.
                        </p>
                    )}
                </Kaart>
                <Uitklap titel="Wat meten de deelscores?">
                    <dl className="space-y-2 text-sm">
                        {deelscores.map((d) => (
                            <div key={d.key}>
                                <dt className="font-semibold">{d.name}</dt>
                                <dd className="text-muted-foreground">
                                    {d.uitleg}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Uitklap>
            </Sectie>

            <Sectie titel="Verloop">
                <Kaart>
                    <Verloop punten={verloop} />
                </Kaart>
            </Sectie>

            {klussen.length > 0 && (
                <Sectie titel="Wat meetelt">
                    <MeerLijst eerst={5}>
                        {klussen.map((k) => (
                            <li key={k.id}>
                                <Link href={klus(k.id)} className="block">
                                    <Rij
                                        className="transition hover:border-violet"
                                        titel={`${k.company}, ${k.location}`}
                                        onder={`${k.status} op ${datum(k.date)}`}
                                        rechts={
                                            <span className="font-bold tabular-nums">
                                                {getal(k.total)}
                                            </span>
                                        }
                                    />
                                </Link>
                            </li>
                        ))}
                    </MeerLijst>
                </Sectie>
            )}
        </>
    );

    const zij = (
        <>
            <Sectie
                titel={
                    <span className="flex items-center gap-2">
                        <BarChart3 className="size-5 text-violet" aria-hidden />
                        Klassement
                    </span>
                }
                actie={
                    <span className="text-xs text-muted-foreground">
                        {klassement.soort}
                    </span>
                }
            >
                <Kaart compact>
                    {klassement.top.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Je positie verschijnt na de eerste herberekening.
                        </p>
                    ) : (
                        <ol
                            className="space-y-0.5 text-sm"
                            data-test="klassement"
                        >
                            {klassement.top.map((r) => (
                                <li
                                    key={r.positie}
                                    aria-current={r.ik || undefined}
                                    className={`grid grid-cols-[1.5rem_1fr_auto] items-center gap-2 rounded-lg px-2 py-1.5 ${r.ik ? 'bg-violet font-bold text-white' : ''}`}
                                >
                                    <span
                                        className={`tabular-nums ${r.ik ? '' : 'font-bold text-muted-foreground'}`}
                                    >
                                        {r.positie}
                                    </span>
                                    <span className="truncate">
                                        {r.ik
                                            ? `Jij, ${creator.first_name}`
                                            : r.naam}
                                    </span>
                                    <span className="font-semibold tabular-nums">
                                        {getal(r.score)}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    )}
                    {klassement.positie !== null && klassement.positie > 5 && (
                        <p className="mt-2 rounded-lg bg-violet px-2 py-1.5 text-sm font-bold text-white tabular-nums">
                            Jij staat op plaats {klassement.positie} van{' '}
                            {klassement.aantal}
                        </p>
                    )}
                </Kaart>
            </Sectie>

            <Sectie
                titel="Strafpunten"
                actie={
                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                        Strafregeling: nog te bespreken
                    </span>
                }
            >
                <Kaart compact>
                    {strafpunten.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Je hebt geen strafpunten.
                        </p>
                    ) : (
                        <ul
                            className="divide-y text-sm"
                            data-test="strafpunten"
                        >
                            {strafpunten.map((e) => (
                                <li
                                    key={e.id}
                                    className="flex items-start justify-between gap-3 py-2 first:pt-0"
                                >
                                    <span className="min-w-0">
                                        <span className="block font-semibold">
                                            {e.label}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {[
                                                e.company,
                                                e.reason,
                                                datum(e.date),
                                            ]
                                                .filter(Boolean)
                                                .join(', ')}
                                        </span>
                                    </span>
                                    <span className="shrink-0 font-bold text-rood tabular-nums">
                                        {e.points}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="mt-2 text-xs text-muted-foreground">
                        Een strafpunt op Betrouwbaarheid ontstaat alleen als je
                        niet kwam na toezegging, te laat leverde of plaatste, of
                        je post te vroeg weghaalde. Hoe zwaar het telt en
                        wanneer het vervalt, is nog niet bepaald: de getallen
                        zijn een voorstel.
                    </p>
                </Kaart>
            </Sectie>

            <div className="space-y-3">
                <Uitklap titel="Wat telt en wat niet">
                    <ul className="space-y-2 text-sm">
                        <li>
                            <b>Telt mee:</b> afgeronde samenwerkingen (cijfers
                            na 14 dagen en de beoordeling van de ondernemer) en
                            of je op tijd bent, op tijd levert en op de
                            afgesproken datum plaatst.
                        </li>
                        <li>
                            <b>Telt mee op Betrouwbaarheid:</b> niet komen nadat
                            je een klus hebt aangenomen.
                        </li>
                        <li>
                            <b>Telt niet mee:</b> een uitnodiging weigeren of
                            laten verlopen. Dat heeft nooit gevolgen voor je
                            score.
                        </li>
                        <li>
                            <b>Telt niet mee:</b> een ondernemer die er niet
                            was, of een verbroken koppeling. Dat is een signaal
                            voor het team, geen strafpunt.
                        </li>
                    </ul>
                </Uitklap>

                <Uitklap titel="Hoe werkt de score?">
                    <div className="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Je score is een gewogen gemiddelde van de vijf
                            deelscores over je samenwerkingen van de laatste{' '}
                            {score.period_days} dagen. Een hoger niveau geeft
                            voorrang bij nieuwe uitnodigingen.
                        </p>
                        <p>
                            Elke 1e van de maand kijkt het systeem of je de
                            grens van een ander niveau haalt (voorstel, volgende
                            keer op {datum(herberekening)}). Het systeem doet
                            dan alleen een voorstel: iemand van het team beslist
                            en je krijgt bericht. Omlaag pas na 2 metingen
                            achter elkaar onder de grens.
                        </p>
                        <p className="flex items-center gap-1.5 font-semibold text-foreground">
                            <Sparkles
                                className="size-4 text-violet"
                                aria-hidden
                            />
                            Niveaugrenzen (voorstel)
                        </p>
                        <ul className="space-y-1">
                            {drempels.map((d) => (
                                <li key={d.value}>
                                    <b className="text-foreground">{d.name}</b>:{' '}
                                    {d.score === 0
                                        ? 'start na screening'
                                        : `score ${d.score} of hoger`}
                                    {d.jobs > 0
                                        ? `, minimaal ${d.jobs} samenwerkingen`
                                        : ''}
                                    {d.quality
                                        ? `, Klantoordeel ${d.quality} of hoger`
                                        : ''}
                                </li>
                            ))}
                        </ul>
                    </div>
                </Uitklap>
            </div>
        </>
    );

    return (
        <>
            <Head title="Jouw score" />
            <Pagina>
                <PaginaKop
                    titel="Jouw score"
                    omschrijving={`Je ${score.period_days}-dagenscore bepaalt je niveau en je voorrang bij nieuwe uitnodigingen.`}
                />

                <TweeKolommen
                    hoofd={
                        <Kaart nadruk>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                <span
                                    className="font-heading text-5xl leading-none font-black tracking-tight tabular-nums"
                                    data-test="totaalscore"
                                >
                                    {getal(score.total)}
                                </span>
                                <span className="flex min-w-0 flex-col gap-1.5">
                                    <span className="flex flex-wrap gap-1.5">
                                        <span className="rounded-md bg-white px-2 py-0.5 text-xs font-bold text-aubergine-dark">
                                            {creator.level}
                                        </span>
                                        {score.voorlopig && (
                                            <span className="rounded-md bg-white/15 px-2 py-0.5 text-xs font-bold">
                                                Voorlopig
                                            </span>
                                        )}
                                        {score.new_talent && (
                                            <span className="rounded-md bg-white/15 px-2 py-0.5 text-xs font-bold">
                                                Nieuw talent
                                            </span>
                                        )}
                                        {gevaar && (
                                            <span className="rounded-md bg-rood px-2 py-0.5 text-xs font-bold">
                                                Niveau in gevaar
                                            </span>
                                        )}
                                    </span>
                                    <span className="text-sm text-white/80">
                                        {score.voorlopig
                                            ? 'Startscore voor nieuwe creators'
                                            : next_level
                                              ? `Nog ${getal(next_level.points_to_go)} punten tot de scoregrens van ${next_level.name}`
                                              : `${score.period_days}-dagenscore, hoogste niveau`}
                                    </span>
                                </span>
                            </div>
                            {next_level && !score.voorlopig && (
                                <div className="mt-4">
                                    <span
                                        role="progressbar"
                                        aria-label={`Voortgang naar ${next_level.name}`}
                                        aria-valuemin={0}
                                        aria-valuemax={100}
                                        aria-valuenow={next_level.progress}
                                        className="block h-1.5 rounded-full bg-white/20"
                                    >
                                        <span
                                            className="block h-1.5 rounded-full bg-gradient-to-r from-[#9C8CFF] to-white"
                                            style={{
                                                width: `${next_level.progress}%`,
                                            }}
                                        />
                                    </span>
                                    <span className="mt-1 flex justify-between text-xs font-semibold text-white/75">
                                        <span>{creator.level}</span>
                                        <span>{next_level.name}</span>
                                    </span>
                                </div>
                            )}
                        </Kaart>
                    }
                    zij={
                        <>
                            {gevaar && (
                                <Kaart className="border-rood/50">
                                    <p className="text-sm font-semibold text-rood">
                                        Niveau in gevaar
                                    </p>
                                    <h2 className="font-heading text-lg font-bold">
                                        Je {creator.level}-niveau staat onder
                                        druk
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Op {datum(gevaar.herberekening)} rekenen
                                        we de niveaus opnieuw uit (voorstel). Je{' '}
                                        {score.period_days}-dagenscore ligt nu
                                        onder de grens van {gevaar.drempel}. Je
                                        zakt pas als je 2 metingen achter elkaar
                                        onder de grens blijft, en altijd beslist
                                        eerst iemand van het team.
                                    </p>
                                    <dl className="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                                        <dt className="text-muted-foreground">
                                            Laagste deelscore
                                        </dt>
                                        <dd className="font-semibold">
                                            {gevaar.laagste ?? 'Onbekend'}
                                        </dd>
                                        <dt className="text-muted-foreground">
                                            Lopende samenwerkingen
                                        </dt>
                                        <dd className="font-semibold">
                                            Houden hun vergoeding
                                        </dd>
                                    </dl>
                                </Kaart>
                            )}
                            {score.voorlopig && (
                                <Kaart className="border-violet">
                                    <p className="flex justify-between gap-3 text-sm font-semibold text-violet">
                                        <span>Voorlopig</span>
                                        <span className="tabular-nums">
                                            {score.jobs_count} van{' '}
                                            {score.min_jobs} samenwerkingen
                                        </span>
                                    </p>
                                    <h2 className="font-heading text-lg font-bold">
                                        Je start met een gemiddelde,{' '}
                                        {creator.first_name}
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Tot je {score.min_jobs} samenwerkingen
                                        hebt in de laatste {score.period_days}{' '}
                                        dagen, telt een startscore van{' '}
                                        {getal(score.startscore)} mee. Daarna
                                        bepaalt alleen jouw eigen werk je score
                                        en vervalt het label Voorlopig.
                                    </p>
                                    <div className="mt-3">
                                        <Balk
                                            waarde={Math.max(
                                                4,
                                                (score.jobs_count /
                                                    score.min_jobs) *
                                                    100,
                                            )}
                                            label="Afgeronde samenwerkingen"
                                        />
                                    </div>
                                </Kaart>
                            )}
                            {!gevaar && !score.voorlopig && (
                                <Kaart className="hidden sm:block">
                                    <p className="text-sm text-muted-foreground">
                                        Je score is het gewogen gemiddelde van
                                        vijf deelscores. Op{' '}
                                        {datum(herberekening)} kijkt het systeem
                                        of je de grens van een ander niveau
                                        haalt. Iemand van het team beslist.
                                    </p>
                                </Kaart>
                            )}
                        </>
                    }
                />

                {score.new_talent && (
                    <Kaart compact>
                        <p className="text-sm">
                            <span className="font-semibold">
                                Nieuw talent.{' '}
                            </span>
                            <span className="text-muted-foreground">
                                De eerste 90 dagen
                                {score.new_talent_until
                                    ? `, tot ${datum(score.new_talent_until)},`
                                    : ''}{' '}
                                sta je in het klassement Nieuw talent. Zo
                                vergelijk je je met creators die ook net
                                begonnen zijn.
                            </span>
                        </p>
                    </Kaart>
                )}

                <TweeKolommen hoofd={hoofd} zij={zij} />
            </Pagina>
        </>
    );
}

CreatorScore.layout = {
    breadcrumbs: [{ title: 'Score', href: scoreRoute() }],
};
