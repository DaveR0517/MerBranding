import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Award } from 'lucide-react';
import { datumTijd } from '@/lib/datum';
import { certificaat as certificaatRoute, overzicht } from '@/routes/academy';
import { inleveren } from '@/routes/academy/examen';
import { AanZet, Fout, Kaart, Kop, groteKnop, rustigeKnop } from '../creator/_onderdelen/opmaak';
import { Beoordelingslijst, Ingediend, Uitslag, VideoOfLink, dagDatum, gemiddeld } from './_onderdelen/onderdelen';
import type { Certificaat, Criterium, Dag, Examen as ExamenT } from './_onderdelen/types';

type Props = {
    exam: ExamenT;
    day: Dag;
    certificate: Certificaat | null;
    criteria: Criterium[];
    videos: number;
    pass_average: number;
};

const namen = ['Sfeer en hook', 'Product in beeld', 'Met oproep'];

export default function Examen({ exam, day, certificate, criteria, videos, pass_average }: Props) {
    const form = useForm<{ items: { link: string; video: File | null }[]; eigen_werk: boolean; toestemming: boolean }>({
        items: Array.from({ length: videos }, () => ({ link: '', video: null })),
        eigen_werk: false,
        toestemming: false,
    });
    const fouten = form.errors as Record<string, string | undefined>;
    const kanInleveren = exam.status === 'open' || exam.status === 'verbeteren';
    const zet = (i: number, waarde: Partial<{ link: string; video: File | null }>) =>
        form.setData('items', form.data.items.map((it, j) => (j === i ? { ...it, ...waarde } : it)));
    const regel = `Elk criterium krijgt 1 tot 5. Voldoende bij gemiddeld ${gemiddeld(pass_average)} of hoger en geen enkele 1.`;

    return (
        <>
            <Head title="Praktijkexamen" />
            <Kop boven="Praktijkexamen" titel={`Maak ${videos} korte video's voor een echte zaak`} onder={`Cursusdag ${dagDatum(day.date, false)}, ${day.location_name}, ${day.city}`}>
                <Link href={overzicht.url()} className="inline-flex items-center gap-1 text-sm font-semibold text-white/90 hover:text-white">
                    <ArrowLeft className="size-4" aria-hidden /> Mijn academy
                </Link>
            </Kop>
            <div className="mx-auto w-full max-w-3xl space-y-4 px-4 py-5 md:px-6">
                {exam.status === 'voldoende' && (
                    <Kaart accent>
                        <AanZet>Voldoende {datumTijd(exam.assessed_at)}{exam.assessor ? `, beoordeeld door ${exam.assessor}` : ''}</AanZet>
                        <p className="font-heading text-3xl font-extrabold">{gemiddeld(exam.average)} <span className="text-base font-semibold text-muted-foreground">gemiddeld van 5</span></p>
                        {certificate && (
                            <div className="mt-3 rounded-xl bg-aubergine-dark p-4 text-white">
                                <p className="text-xs font-bold tracking-wider text-white/70 uppercase">Certificaat</p>
                                <p className="flex items-center gap-2 font-heading text-xl font-extrabold break-all"><Award className="size-5 shrink-0" aria-hidden /> {certificate.number}</p>
                                <a className="mt-2 inline-block text-sm font-semibold underline" href={certificaatRoute.url(certificate.id)}>Certificaat bekijken</a>
                            </div>
                        )}
                    </Kaart>
                )}
                {(exam.status === 'verbeteren' || exam.status === 'onvoldoende') && (
                    <Kaart accent>
                        <AanZet>{exam.status === 'verbeteren' ? `Verbeteren, herkansing voor ${datumTijd(exam.due_at)}` : 'Niet gehaald'}</AanZet>
                        <p className="font-heading text-3xl font-extrabold">{gemiddeld(exam.average)} <span className="text-base font-semibold text-muted-foreground">gemiddeld van 5</span></p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {exam.status === 'verbeteren'
                                ? 'Je hebt één herkansing. Lukt het dan niet, dan krijg je een deelnamebewijs en mag je je toch als creator aanmelden, zonder label (voorstel).'
                                : 'Je krijgt een deelnamebewijs en mag je toch als creator aanmelden, zonder label (voorstel).'}
                        </p>
                    </Kaart>
                )}
                {exam.scores && (
                    <Kaart titel="Beoordeling">
                        <Uitslag criteria={criteria} scores={exam.scores} />
                        {exam.feedback && (
                            <>
                                <h3 className="mt-4 font-semibold">Opmerkingen van je docent</h3>
                                <p className="mt-1 rounded-xl bg-muted/50 p-3 text-sm whitespace-pre-line">{exam.feedback}</p>
                            </>
                        )}
                    </Kaart>
                )}

                {exam.status === 'ingeleverd' && (
                    <Kaart accent>
                        <AanZet>Ingeleverd {datumTijd(exam.submitted_at)}</AanZet>
                        <h2 className="font-heading text-lg font-extrabold">Je examen ligt bij je docent</h2>
                        <p className="mt-1 text-sm text-muted-foreground">Beoordelen gebeurt binnen 3 werkdagen. Je krijgt een melding zodra de beoordeling klaar is.</p>
                    </Kaart>
                )}
                {exam.videos.length > 0 && !kanInleveren && (
                    <div className="grid gap-3 sm:grid-cols-3">
                        {exam.videos.map((v) => <Ingediend key={v.id} label={v.label} link={v.link} video_url={v.video_url} />)}
                    </div>
                )}

                {kanInleveren && (
                    <Kaart titel={exam.status === 'verbeteren' ? 'Herkansing inleveren' : `Inleveren voor ${datumTijd(exam.due_at)}`}>
                        <p className="-mt-1 mb-3 text-sm text-muted-foreground">Staand (9:16), maximaal 60 seconden per video. {day.teacher_name ? `Je docent ${day.teacher_name}` : 'Je docent'} beoordeelt ze binnen 3 werkdagen.</p>
                        <details className="mb-3 rounded-xl border p-3">
                            <summary className="cursor-pointer font-semibold">Beoordelingslijst vooraf bekijken</summary>
                            <div className="mt-2"><Beoordelingslijst criteria={criteria} onder={regel} /></div>
                        </details>
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(inleveren.url(exam.id), { forceFormData: true, preserveScroll: true });
                            }}
                        >
                            {form.data.items.map((it, i) => (
                                <VideoOfLink
                                    key={i}
                                    id={`v${i}`}
                                    label={`Video ${i + 1}${namen[i] ? `, ${namen[i]}` : ''}`}
                                    link={it.link}
                                    bestand={it.video}
                                    onLink={(v) => zet(i, { link: v })}
                                    onBestand={(f) => zet(i, { video: f })}
                                    fout={fouten[`items.${i}`] ?? fouten[`items.${i}.link`] ?? fouten[`items.${i}.video`]}
                                />
                            ))}
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" className="mt-1 size-4 accent-violet" checked={form.data.eigen_werk} onChange={(e) => form.setData('eigen_werk', e.target.checked)} />
                                <span>Dit is mijn eigen werk, gemaakt na de cursusdag.</span>
                            </label>
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" className="mt-1 size-4 accent-violet" checked={form.data.toestemming} onChange={(e) => form.setData('toestemming', e.target.checked)} />
                                <span>Iedereen die herkenbaar in beeld is, heeft toestemming gegeven.</span>
                            </label>
                            <Fout tekst={fouten.eigen_werk ?? fouten.toestemming ?? fouten.items ?? fouten.examen} />
                            {form.progress && <p className="text-sm text-muted-foreground">Uploaden: {form.progress.percentage}%</p>}
                            <button type="submit" className={groteKnop} disabled={form.processing}>Examen inleveren</button>
                        </form>
                    </Kaart>
                )}

                <Link href={overzicht.url()} className={rustigeKnop}>Terug naar Mijn academy</Link>
            </div>
        </>
    );
}
