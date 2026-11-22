import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, CircleDashed, Play } from 'lucide-react';
import { les as lesRoute, overzicht } from '@/routes/academy';
import { klaar } from '@/routes/academy/les';
import { Kaart, Kop, groteKnop, rustigeKnop } from '../creator/_onderdelen/opmaak';

type LesKort = { id: number; title: string; minutes: number | null; done: boolean };
type Props = {
    lesson: LesKort & { html: string };
    lessons: LesKort[];
    position: number;
    previous_id: number | null;
    next_id: number | null;
};

export default function Les({ lesson, lessons, position, previous_id, next_id }: Props) {
    return (
        <>
            <Head title={lesson.title} />
            <Kop boven={`Leeromgeving, les ${position} van ${lessons.length}`} titel={lesson.title} onder={lesson.minutes ? `${lesson.minutes} minuten` : undefined}>
                <Link href={overzicht.url()} className="inline-flex items-center gap-1 text-sm font-semibold text-white/90 hover:text-white">
                    <ArrowLeft className="size-4" aria-hidden /> Mijn academy
                </Link>
            </Kop>
            <div className="mx-auto grid w-full max-w-5xl gap-4 px-4 py-5 md:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,300px)]">
                <div className="space-y-4">
                    <Kaart>
                        <div
                            className="space-y-3 text-base leading-relaxed break-words [&_a]:text-violet [&_a]:underline [&_h2]:font-heading [&_h2]:text-lg [&_h2]:font-extrabold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
                            dangerouslySetInnerHTML={{ __html: lesson.html }}
                        />
                    </Kaart>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {lesson.done ? (
                            <p className="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-emerald-50 font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                <Check className="size-5" aria-hidden /> Afgerond
                            </p>
                        ) : (
                            <button type="button" className={groteKnop} onClick={() => router.post(klaar.url(lesson.id), {}, { preserveScroll: true })}>
                                Markeer als klaar
                            </button>
                        )}
                        {next_id ? (
                            <Link className={rustigeKnop} href={lesRoute.url(next_id)}>Volgende les</Link>
                        ) : previous_id ? (
                            <Link className={rustigeKnop} href={lesRoute.url(previous_id)}>Vorige les</Link>
                        ) : null}
                    </div>
                </div>
                <aside>
                    <Kaart titel="Alle lessen">
                        <ol className="space-y-1">
                            {lessons.map((l, i) => (
                                <li key={l.id}>
                                    <Link
                                        href={lesRoute.url(l.id)}
                                        aria-current={l.id === lesson.id ? 'page' : undefined}
                                        className={`flex items-center gap-2 rounded-lg px-2 py-2 text-sm hover:bg-muted ${l.id === lesson.id ? 'bg-violet/10 font-bold text-violet' : ''}`}
                                    >
                                        {l.id === lesson.id ? <Play className="size-4 shrink-0" aria-hidden /> : l.done ? <Check className="size-4 shrink-0 text-emerald-600" aria-hidden /> : <CircleDashed className="size-4 shrink-0 text-muted-foreground" aria-hidden />}
                                        <span className="min-w-0 flex-1">{i + 1}. {l.title}</span>
                                        {l.minutes && <span className="text-xs text-muted-foreground">{l.minutes} min</span>}
                                    </Link>
                                </li>
                            ))}
                        </ol>
                        <p className="mt-3 text-xs text-muted-foreground">De leeromgeving hoort bij je cursusdag. Toegang tot 90 dagen na de dag is een voorstel; nu staat hij altijd open.</p>
                    </Kaart>
                </aside>
            </div>
        </>
    );
}
