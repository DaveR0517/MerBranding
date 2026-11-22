import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Download, ExternalLink, GripVertical, Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Kaart, Leeg, MeerLijst, Pagina, PaginaKop, Rij, Sectie, Tabs, TweeKolommen } from '@/components/pagina';
import { Miniatuur, StudioStatus } from '@/components/studio/miniatuur';
import type { Export, OntwerpItem } from '@/components/studio/typen';
import { telHashtags } from '@/components/studio/typen';
import { datum, datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import kanalen from '@/routes/team/kanalen';
import feedplanner from '@/routes/team/studio/feedplanner';
import ontwerpen from '@/routes/team/studio/ontwerpen';

type Post = OntwerpItem & { laatste_export?: Export | null };

type Props = {
    feed: Post[];
    concepten: OntwerpItem[];
    kalender: OntwerpItem[];
    maand: string;
    statussen: Record<string, string>;
    /** Eigen Instagram gekoppeld met het recht om te plaatsen (module eigen kanalen). */
    instagram_plaatsen: boolean;
};

const INSTAGRAM_FORMATEN = ['instagram_post', 'instagram_portret', 'story', 'carrousel'];

const MAX_BIJSCHRIFT = 2200;
const MAX_HASHTAGS = 30;
const veld = 'h-10 w-full rounded-lg border bg-background px-3 text-sm';

/** ISO naar de waarde van een datetime-local veld, in de tijd van Nederland (de browser van het team). */
function lokaal(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso);
    const p = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

function PostDetail({ post, statussen, plaatsen }: { post: Post; statussen: Record<string, string>; plaatsen: boolean }) {
    const [status, setStatus] = useState(post.status ?? 'klaar');
    const [gepland, setGepland] = useState(lokaal(post.scheduled_at));
    const [bijschrift, setBijschrift] = useState(post.caption ?? '');
    const [link, setLink] = useState(post.post_url ?? '');
    const [fouten, setFouten] = useState<Record<string, string>>({});
    const hashtags = telHashtags(bijschrift);

    useEffect(() => {
        setStatus(post.status ?? 'klaar');
        setGepland(lokaal(post.scheduled_at));
        setBijschrift(post.caption ?? '');
        setLink(post.post_url ?? '');
        setFouten({});
    }, [post]);

    const bewaar = (extra: Record<string, unknown> = {}) =>
        router.patch(
            feedplanner.bijwerken.url(post.id),
            { status, scheduled_at: gepland ? new Date(gepland).toISOString() : null, caption: bijschrift || null, post_url: link || null, ...extra },
            { preserveScroll: true, onError: (e) => setFouten(e), onSuccess: () => setFouten({}) },
        );

    return (
        <Kaart className="space-y-4" id="post-detail">
            <div className="flex gap-3">
                <Miniatuur item={post} className="w-20 shrink-0" />
                <div className="min-w-0 space-y-1">
                    <p className="truncate font-semibold">{post.name}</p>
                    <p className="text-sm text-muted-foreground">{post.format_naam}</p>
                    <Link href={ontwerpen.bewerken.url(post.id)} className="text-sm font-semibold text-violet">
                        Ontwerp openen
                    </Link>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-2">
                <label className="space-y-1">
                    <span className="text-xs font-semibold text-muted-foreground">Status</span>
                    <select className={veld} value={status} onChange={(e) => setStatus(e.target.value)} data-test="feed-status">
                        {Object.entries(statussen).map(([k, s]) => (
                            <option key={k} value={k}>
                                {s}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="space-y-1">
                    <span className="text-xs font-semibold text-muted-foreground">Gepland op</span>
                    <input type="datetime-local" className={veld} value={gepland} onChange={(e) => setGepland(e.target.value)} />
                </label>
            </div>
            {fouten.scheduled_at && <p className="text-sm text-rood">{fouten.scheduled_at}</p>}
            <label className="block space-y-1">
                <span className="flex justify-between text-xs font-semibold text-muted-foreground">
                    Bijschrift
                    <span className={cn('tabular-nums', (bijschrift.length > MAX_BIJSCHRIFT || hashtags > MAX_HASHTAGS) && 'text-rood')}>
                        {bijschrift.length}/{MAX_BIJSCHRIFT} tekens, {hashtags}/{MAX_HASHTAGS} hashtags
                    </span>
                </span>
                <textarea className="min-h-28 w-full rounded-lg border bg-background px-3 py-2 text-sm" value={bijschrift} onChange={(e) => setBijschrift(e.target.value)} />
            </label>
            {fouten.caption && <p className="text-sm text-rood">{fouten.caption}</p>}
            <button type="button" onClick={() => bewaar()} className="h-10 w-full rounded-lg bg-violet text-sm font-semibold text-white hover:bg-violet/90">
                Opslaan
            </button>

            <div className="space-y-2 border-t pt-3">
                {plaatsen && INSTAGRAM_FORMATEN.includes(post.format) && post.status !== 'geplaatst' ? (
                    <button
                        type="button"
                        onClick={() => router.post(kanalen.plaatsen.url(post.id), {}, { preserveScroll: true, onError: (e) => setFouten(e) })}
                        className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-violet text-sm font-semibold text-white hover:bg-violet/90"
                        data-test="feed-plaatsen"
                    >
                        <Send className="size-4" aria-hidden /> Plaatsen op Instagram
                    </button>
                ) : (
                    <p className="text-sm text-muted-foreground">Download de post, plaats hem zelf en markeer hem hier. Rechtstreeks plaatsen kan zodra Instagram gekoppeld is onder Eigen kanalen.</p>
                )}
                {fouten.plaatsen && <p className="text-sm text-rood">{fouten.plaatsen}</p>}
                {post.laatste_export ? (
                    <a href={post.laatste_export.url} className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border text-sm font-semibold hover:border-violet">
                        <Download className="size-4" aria-hidden /> Downloaden voor plaatsen ({post.laatste_export.kind.toUpperCase()})
                    </a>
                ) : (
                    <Link href={ontwerpen.bewerken.url(post.id)} className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border text-sm font-semibold hover:border-violet">
                        <Download className="size-4" aria-hidden /> Eerst exporteren in de editor
                    </Link>
                )}
                <label className="block space-y-1">
                    <span className="text-xs font-semibold text-muted-foreground">Link naar de post op Instagram</span>
                    <input type="url" className={veld} placeholder="https://www.instagram.com/p/..." value={link} onChange={(e) => setLink(e.target.value)} />
                </label>
                {fouten.post_url && <p className="text-sm text-rood">{fouten.post_url}</p>}
                <button type="button" onClick={() => bewaar({ status: 'geplaatst' })} className="h-10 w-full rounded-lg border text-sm font-semibold hover:border-violet" data-test="feed-geplaatst">
                    Markeer als geplaatst
                </button>
                {post.post_url && (
                    <a href={post.post_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm font-semibold text-violet">
                        Bekijk op Instagram <ExternalLink className="size-3.5" aria-hidden />
                    </a>
                )}
            </div>
        </Kaart>
    );
}

function Kalender({ maand, posts }: { maand: string; posts: OntwerpItem[] }) {
    const [jaar, m] = maand.split('-').map(Number);
    const eerste = new Date(jaar, m - 1, 1);
    const dagen = new Date(jaar, m, 0).getDate();
    const leeg = (eerste.getDay() + 6) % 7;
    const vanDag = (d: number) =>
        posts.filter((p) => {
            const iso = p.status === 'geplaatst' ? (p.posted_at ?? p.scheduled_at) : p.scheduled_at;

            if (!iso) {
                return false;
            }

            const x = new Date(iso);

            return x.getFullYear() === jaar && x.getMonth() === m - 1 && x.getDate() === d;
        });
    const verschuif = (n: number) => {
        const d = new Date(jaar, m - 1 + n, 1);
        router.get(feedplanner.index.url(), { maand: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` }, { preserveScroll: true, preserveState: true, only: ['kalender', 'maand'] });
    };
    const vandaag = new Date();

    return (
        <Kaart className="space-y-3">
            <div className="flex items-center justify-between gap-2">
                <button type="button" onClick={() => verschuif(-1)} className="grid size-10 place-items-center rounded-full border hover:border-violet" aria-label="Vorige maand">
                    <ChevronLeft className="size-4" />
                </button>
                <p className="font-heading text-lg font-bold capitalize">{eerste.toLocaleDateString('nl-NL', { month: 'long', year: 'numeric' })}</p>
                <button type="button" onClick={() => verschuif(1)} className="grid size-10 place-items-center rounded-full border hover:border-violet" aria-label="Volgende maand">
                    <ChevronRight className="size-4" />
                </button>
            </div>
            <div className="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-muted-foreground">
                {['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'].map((d) => (
                    <span key={d}>{d}</span>
                ))}
            </div>
            <div className="grid grid-cols-7 gap-1">
                {Array.from({ length: leeg }, (_, i) => (
                    <span key={`l${i}`} />
                ))}
                {Array.from({ length: dagen }, (_, i) => {
                    const d = i + 1;
                    const hier = vanDag(d);
                    const isVandaag = vandaag.getFullYear() === jaar && vandaag.getMonth() === m - 1 && vandaag.getDate() === d;

                    return (
                        <div key={d} className={cn('min-h-12 rounded-lg border p-1 text-left md:min-h-20', isVandaag && 'border-violet')}>
                            <span className="text-xs font-semibold tabular-nums">{d}</span>
                            <div className="mt-0.5 space-y-0.5">
                                {hier.map((p) => (
                                    <Link
                                        key={p.id}
                                        href={ontwerpen.bewerken.url(p.id)}
                                        title={p.name}
                                        className={cn('block truncate rounded px-1 text-[10px] leading-4 font-semibold md:text-xs md:leading-5', p.status === 'geplaatst' ? 'bg-emerald-100 text-emerald-800' : 'bg-violet/10 text-violet')}
                                    >
                                        {p.name}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
            {posts.length === 0 && <p className="text-sm text-muted-foreground">Nog niets gepland of geplaatst in deze maand.</p>}
        </Kaart>
    );
}

/** MeStudio, Feedplanner: raster zoals een Instagram-profiel (slepen voor de volgorde) en een maandkalender. */
export default function Feedplanner({ feed, concepten, kalender, maand, statussen, instagram_plaatsen }: Props) {
    const [tab, setTab] = useState<'raster' | 'kalender'>('raster');
    const [volgorde, setVolgorde] = useState(feed);
    const [gekozen, setGekozen] = useState<number | null>(feed[0]?.id ?? null);
    const [sleept, setSleept] = useState<number | null>(null);

    useEffect(() => setVolgorde(feed), [feed]);

    const post = volgorde.find((p) => p.id === gekozen) ?? null;
    const bewaarVolgorde = (lijst: Post[]) => {
        setVolgorde(lijst);
        router.post(feedplanner.volgorde.url(), { ids: lijst.map((p) => p.id) }, { preserveScroll: true, preserveState: true });
    };
    const verplaats = (id: number, naar: number) => {
        const lijst = volgorde.filter((p) => p.id !== id);
        lijst.splice(Math.max(0, Math.min(lijst.length, naar)), 0, volgorde.find((p) => p.id === id)!);
        bewaarVolgorde(lijst);
    };

    return (
        <>
            <Head title="Feedplanner" />
            <Pagina>
                <PaginaKop
                    boven="MeStudio"
                    titel="Feedplanner"
                    omschrijving="Zo komt het Instagram-profiel van MEBRANDING eruit te zien. Sleep posts om de volgorde te wijzigen; linksboven staat de nieuwste."
                    extra={
                        <Tabs
                            tabs={[
                                { waarde: 'raster', label: 'Raster', aantal: feed.length },
                                { waarde: 'kalender', label: 'Kalender' },
                            ]}
                            actief={tab}
                            onKies={setTab}
                        />
                    }
                />

                {tab === 'kalender' ? (
                    <Kalender maand={maand} posts={kalender} />
                ) : (
                    <TweeKolommen
                        hoofd={
                            volgorde.length === 0 ? (
                                <Leeg titel="De feed is nog leeg">Zet een ontwerp op Klaar of Ingepland, dan komt het hier. Concepten staan ernaast.</Leeg>
                            ) : (
                                <div className="mx-auto max-w-xl">
                                    <ol className="grid grid-cols-3 gap-1" aria-label="Feed, nieuwste eerst" data-test="feed-raster">
                                        {volgorde.map((p, i) => (
                                            <li
                                                key={p.id}
                                                draggable
                                                data-test="feed-post"
                                                onDragStart={(e) => {
                                                    setSleept(p.id);
                                                    e.dataTransfer.effectAllowed = 'move';
                                                }}
                                                onDragOver={(e) => e.preventDefault()}
                                                onDrop={(e) => {
                                                    e.preventDefault();

                                                    if (sleept !== null && sleept !== p.id) {
                                                        verplaats(sleept, i);
                                                    }

                                                    setSleept(null);
                                                }}
                                                onDragEnd={() => setSleept(null)}
                                                className={cn('relative cursor-grab', sleept === p.id && 'opacity-40', gekozen === p.id && 'ring-2 ring-violet')}
                                            >
                                                <button type="button" onClick={() => setGekozen(p.id)} className="block w-full text-left" aria-label={`${p.name}, ${statussen[p.status ?? ''] ?? ''}`}>
                                                    <Miniatuur item={p} vullen className="rounded-none" />
                                                </button>
                                                <span className="pointer-events-none absolute top-1 left-1">
                                                    <StudioStatus status={p.status} />
                                                </span>
                                                <GripVertical className="pointer-events-none absolute top-1 right-1 size-4 text-white drop-shadow" aria-hidden />
                                                {p.scheduled_at && p.status !== 'geplaatst' && (
                                                    <span className="pointer-events-none absolute inset-x-1 bottom-1 truncate rounded bg-aubergine-dark/80 px-1 text-[10px] text-white md:text-xs">
                                                        {datumTijd(p.scheduled_at)}
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                    {post && (
                                        <div className="mt-2 flex justify-center gap-2">
                                            <button type="button" onClick={() => verplaats(post.id, volgorde.indexOf(post) - 1)} className="h-10 rounded-lg border bg-card px-3 text-sm font-semibold hover:border-violet" disabled={volgorde[0]?.id === post.id}>
                                                Eerder in de feed
                                            </button>
                                            <button type="button" onClick={() => verplaats(post.id, volgorde.indexOf(post) + 1)} className="h-10 rounded-lg border bg-card px-3 text-sm font-semibold hover:border-violet" disabled={volgorde.at(-1)?.id === post.id}>
                                                Later in de feed
                                            </button>
                                        </div>
                                    )}
                                </div>
                            )
                        }
                        zij={
                            <>
                                {post && <PostDetail post={post} statussen={statussen} plaatsen={instagram_plaatsen} />}
                                <Sectie titel="Concepten" omschrijving="Nog niet in de feed.">
                                    {concepten.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">Geen concepten.</p>
                                    ) : (
                                        <MeerLijst eerst={5}>
                                            {concepten.map((c) => (
                                                <li key={c.id}>
                                                    <Rij
                                                        links={<Miniatuur item={c} className="w-10" />}
                                                        titel={c.name}
                                                        onder={`${c.format_naam}, ${datum(c.updated_at ?? null)}`}
                                                        rechts={
                                                            <button
                                                                type="button"
                                                                onClick={() => router.patch(feedplanner.bijwerken.url(c.id), { status: 'klaar' }, { preserveScroll: true, onSuccess: () => setGekozen(c.id) })}
                                                                className="h-10 rounded-lg border px-3 text-sm font-semibold hover:border-violet"
                                                            >
                                                                In de feed
                                                            </button>
                                                        }
                                                    />
                                                </li>
                                            ))}
                                        </MeerLijst>
                                    )}
                                </Sectie>
                            </>
                        }
                    />
                )}
            </Pagina>
        </>
    );
}
