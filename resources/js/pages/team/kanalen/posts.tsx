import { Head, router, useForm } from '@inertiajs/react';
import { ExternalLink, Film, Image as ImageIcon, MessageSquare, RefreshCw, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Etiket } from '@/components/content/labels';
import { getal, KanalenTabs, PLATFORMS } from '@/components/kanalen/onderdelen';
import type { PlatformSleutel } from '@/components/kanalen/onderdelen';
import { Kaart, Leeg, Pagina, PaginaKop, TweeKolommen } from '@/components/pagina';
import { Button } from '@/components/ui/button';
import { datum, datumTijd } from '@/lib/datum';
import { cn } from '@/lib/utils';
import kanalen from '@/routes/team/kanalen';

type Cijfers = { views: number | null; reach: number | null; likes: number | null; comments: number | null; saved: number | null; shares: number | null };

type Post = {
    id: number;
    platform: PlatformSleutel;
    soort: 'reel' | 'post' | 'carrousel' | 'story' | 'video';
    bijschrift: string | null;
    miniatuur: string | null;
    permalink: string | null;
    geplaatst_op: string | null;
    cijfers: Cijfers;
};

type Reactie = { id: number; ouder: string | null; extern_id: string; naam: string | null; tekst: string | null; likes: number; eigen: boolean; door: string | null; op: string | null };

type Detail = Post & {
    media_url: string | null;
    duur: number | null;
    alle_cijfers: Record<string, number>;
    cijfers_op: string | null;
    reacties_mogelijk: boolean;
    mag_antwoorden: boolean;
    reacties: Reactie[];
};

type Props = { posts: Post[]; detail: Detail | null; gekoppeld: Record<PlatformSleutel, boolean> };

const SOORTEN: Record<string, string> = { reel: 'Reel', post: 'Post', carrousel: 'Carrousel', story: 'Story', video: 'TikTok-video' };
const SORTERING: { key: keyof Cijfers | 'nieuwste'; naam: string }[] = [
    { key: 'nieuwste', naam: 'Nieuwste eerst' },
    { key: 'views', naam: 'Meeste weergaven' },
    { key: 'reach', naam: 'Meeste bereik' },
    { key: 'likes', naam: 'Meeste likes' },
    { key: 'comments', naam: 'Meeste reacties' },
    { key: 'saved', naam: 'Meest opgeslagen' },
    { key: 'shares', naam: 'Meest gedeeld' },
];
const CIJFER_NAMEN: Record<string, string> = { views: 'Weergaven', reach: 'Bereik', likes: 'Likes', comments: 'Reacties', saved: 'Opgeslagen', shares: 'Gedeeld', total_interactions: 'Interacties' };
const veld = 'h-10 rounded-lg border bg-background px-3 text-sm';
const STAP = 12;

function Miniatuur({ post, className }: { post: Post; className?: string }) {
    const Icoon = PLATFORMS[post.platform].icoon;
    const [kapot, setKapot] = useState(false);

    return (
        <div className={cn('relative aspect-[4/5] overflow-hidden rounded-lg bg-muted', className)}>
            {post.miniatuur && !kapot ? (
                <img src={post.miniatuur} alt="" loading="lazy" onError={() => setKapot(true)} className="size-full object-cover" />
            ) : (
                <div className="flex size-full items-center justify-center text-muted-foreground">
                    {post.soort === 'post' || post.soort === 'carrousel' ? <ImageIcon className="size-8" aria-hidden /> : <Film className="size-8" aria-hidden />}
                </div>
            )}
            <span className="absolute top-1.5 left-1.5 flex size-6 items-center justify-center rounded-full bg-black/60 text-white">
                <Icoon className="size-3.5" aria-label={PLATFORMS[post.platform].naam} />
            </span>
        </div>
    );
}

function Antwoord({ reactie }: { reactie: Reactie }) {
    const form = useForm({ tekst: '' });

    return (
        <form
            className="mt-2 flex gap-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(kanalen.reacties.antwoord.url(reactie.id), { preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <input className={cn(veld, 'min-w-0 flex-1')} placeholder={`Antwoord aan @${reactie.naam ?? ''}`} value={form.data.tekst} onChange={(e) => form.setData('tekst', e.target.value)} aria-label="Antwoord" />
            <Button type="submit" className="h-10 bg-violet text-white hover:bg-violet/90" disabled={form.processing || !form.data.tekst.trim()}>
                Plaats
            </Button>
            {form.errors.tekst && <p className="text-sm text-rood">{form.errors.tekst}</p>}
        </form>
    );
}

function PostDetail({ d, sluit }: { d: Detail; sluit: () => void }) {
    const [open, setOpen] = useState<number | null>(null);
    const c = d.alle_cijfers;
    const interactie = ['likes', 'comments', 'saved', 'shares'].reduce((s, k) => s + (c[k] ?? 0), 0);
    // Berekend: betrokkenheid = interacties gedeeld door bereik.
    const betrokkenheid = c.reach ? Math.round((interactie / c.reach) * 1000) / 10 : null;
    const hoofd = d.reacties.filter((r) => !r.ouder);

    return (
        <Kaart className="space-y-4 lg:sticky lg:top-4" id="post-detail">
            <div className="flex gap-3">
                <Miniatuur post={d} className="w-24 shrink-0" />
                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex items-start justify-between gap-2">
                        <Etiket kleur="grijs">{SOORTEN[d.soort]}</Etiket>
                        <button type="button" onClick={sluit} aria-label="Sluiten" className="inline-flex size-10 items-center justify-center rounded-md hover:bg-muted md:size-8">
                            <X className="size-4" aria-hidden />
                        </button>
                    </div>
                    <p className="text-sm text-muted-foreground">{datumTijd(d.geplaatst_op)}</p>
                    {d.permalink && (
                        <a href={d.permalink} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm font-semibold text-violet">
                            Bekijk op {PLATFORMS[d.platform].naam} <ExternalLink className="size-3.5" aria-hidden />
                        </a>
                    )}
                </div>
            </div>
            {d.bijschrift && <p className="line-clamp-4 text-sm whitespace-pre-line">{d.bijschrift}</p>}

            <dl className="grid grid-cols-3 gap-2">
                {Object.entries(CIJFER_NAMEN)
                    .filter(([k]) => c[k] !== undefined)
                    .map(([k, naam]) => (
                        <div key={k} className="rounded-lg bg-muted/50 p-2">
                            <dt className="text-xs text-muted-foreground">{naam}</dt>
                            <dd className="font-semibold tabular-nums">{getal(c[k])}</dd>
                        </div>
                    ))}
                {betrokkenheid !== null && (
                    <div className="rounded-lg bg-muted/50 p-2">
                        <dt className="text-xs text-muted-foreground">Betrokkenheid</dt>
                        <dd className="font-semibold tabular-nums">{betrokkenheid.toLocaleString('nl-NL')}%</dd>
                    </div>
                )}
            </dl>
            <p className="text-xs text-muted-foreground">
                {d.cijfers_op ? `Cijfers van ${datumTijd(d.cijfers_op)}.` : 'Nog geen cijfers.'} Betrokkenheid is likes, reacties, opslagen en delen gedeeld door bereik.
            </p>

            {d.reacties_mogelijk ? (
                <div className="space-y-2 border-t pt-3">
                    <div className="flex items-center justify-between gap-2">
                        <p className="font-semibold">Reacties ({d.reacties.length})</p>
                        <Button variant="outline" size="sm" className="max-md:h-10" onClick={() => router.post(kanalen.reacties.verversen.url(d.id), {}, { preserveScroll: true })}>
                            <RefreshCw className="size-3.5" aria-hidden /> Ophalen
                        </Button>
                    </div>
                    {hoofd.length === 0 && <p className="text-sm text-muted-foreground">Nog geen reacties opgehaald.</p>}
                    <ul className="max-h-96 space-y-2 overflow-y-auto">
                        {hoofd.map((r) => (
                            <li key={r.id} className="rounded-lg border p-2 text-sm">
                                <p>
                                    <span className="font-semibold">@{r.naam}</span> {r.tekst}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {datum(r.op)}
                                    {r.likes > 0 && `, ${r.likes} likes`}
                                </p>
                                {d.reacties
                                    .filter((a) => a.ouder === r.extern_id)
                                    .map((a) => (
                                        <p key={a.id} className={cn('mt-1.5 ml-3 border-l-2 pl-2', a.eigen && 'border-violet')}>
                                            <span className="font-semibold">@{a.naam}</span> {a.tekst}
                                            {a.door && <span className="block text-xs text-muted-foreground">door {a.door}</span>}
                                        </p>
                                    ))}
                                {d.mag_antwoorden && !r.eigen && (open === r.id ? <Antwoord reactie={r} /> : (
                                    <button type="button" onClick={() => setOpen(r.id)} className="mt-1 inline-flex min-h-10 items-center gap-1 text-xs font-semibold text-violet md:min-h-0">
                                        <MessageSquare className="size-3.5" aria-hidden /> Beantwoorden
                                    </button>
                                ))}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : (
                <p className="border-t pt-3 text-sm text-muted-foreground">Reacties op TikTok lees en beantwoord je in de TikTok-app: de Display API levert alleen het aantal.</p>
            )}
        </Kaart>
    );
}

/** Eigen kanalen, posts en filmpjes: alle posts en video's met cijfers, te filteren en te sorteren. */
export default function Posts({ posts, detail, gekoppeld }: Props) {
    const [platform, setPlatform] = useState<'alle' | PlatformSleutel>('alle');
    const [soort, setSoort] = useState('alle');
    const [sorteer, setSorteer] = useState<(typeof SORTERING)[number]['key']>('nieuwste');
    const [aantal, setAantal] = useState(STAP);

    const zichtbaar = useMemo(() => {
        const lijst = posts.filter((p) => (platform === 'alle' || p.platform === platform) && (soort === 'alle' || p.soort === soort));

        return sorteer === 'nieuwste' ? lijst : [...lijst].sort((a, b) => (b.cijfers[sorteer] ?? -1) - (a.cijfers[sorteer] ?? -1));
    }, [posts, platform, soort, sorteer]);

    useEffect(() => setAantal(STAP), [platform, soort, sorteer]);

    const kies = (id: number | null) =>
        router.get(kanalen.posts.url(id ? { query: { post: id } } : undefined), {}, {
            only: ['detail'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => id && window.innerWidth < 1024 && document.getElementById('post-detail')?.scrollIntoView({ behavior: 'smooth' }),
        });

    const raster = (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-2 md:flex md:flex-wrap">
                <select className={veld} value={platform} onChange={(e) => setPlatform(e.target.value as typeof platform)} aria-label="Platform">
                    <option value="alle">Alle platforms</option>
                    <option value="instagram">Instagram</option>
                    <option value="tiktok">TikTok</option>
                </select>
                <select className={veld} value={soort} onChange={(e) => setSoort(e.target.value)} aria-label="Soort">
                    <option value="alle">Alle soorten</option>
                    {Object.entries(SOORTEN).map(([k, n]) => (
                        <option key={k} value={k}>
                            {n}
                        </option>
                    ))}
                </select>
                <select className={cn(veld, 'col-span-2')} value={sorteer} onChange={(e) => setSorteer(e.target.value as typeof sorteer)} aria-label="Sorteren">
                    {SORTERING.map((s) => (
                        <option key={s.key} value={s.key}>
                            {s.naam}
                        </option>
                    ))}
                </select>
            </div>

            {zichtbaar.length === 0 ? (
                <Leeg titel="Geen posts">
                    {gekoppeld.instagram || gekoppeld.tiktok ? 'Geen posts met dit filter.' : 'Koppel Instagram of TikTok onder Overzicht. Daarna verschijnen de posts hier vanzelf.'}
                </Leeg>
            ) : (
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    {zichtbaar.slice(0, aantal).map((p) => (
                        <li key={p.id}>
                            <button
                                type="button"
                                onClick={() => kies(p.id)}
                                aria-pressed={detail?.id === p.id}
                                className={cn('w-full rounded-xl border bg-card p-1.5 text-left hover:border-violet', detail?.id === p.id && 'border-violet ring-2 ring-violet/30')}
                            >
                                <Miniatuur post={p} />
                                <div className="space-y-0.5 px-1 pt-1.5 pb-0.5">
                                    <p className="truncate text-xs text-muted-foreground">
                                        {SOORTEN[p.soort]}, {datum(p.geplaatst_op)}
                                    </p>
                                    <p className="flex flex-wrap gap-x-2 text-xs tabular-nums">
                                        <span title="Weergaven">{getal(p.cijfers.views)} weerg.</span>
                                        <span title="Likes">{getal(p.cijfers.likes)} likes</span>
                                        <span title="Reacties">{getal(p.cijfers.comments)} reac.</span>
                                    </p>
                                </div>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {zichtbaar.length > aantal && (
                <button type="button" onClick={() => setAantal(aantal + STAP)} className="w-full rounded-lg border bg-card py-2 text-sm font-semibold text-violet hover:border-violet">
                    Toon meer ({zichtbaar.length - aantal})
                </button>
            )}
        </div>
    );

    return (
        <>
            <Head title="Posts en filmpjes" />
            <Pagina>
                <PaginaKop boven="Eigen kanalen" titel="Posts en filmpjes" omschrijving="Alle posts van Instagram en video's van TikTok met weergaven, bereik, likes, reacties, opslagen en delen." />
                <KanalenTabs actief="posts" />
                <TweeKolommen
                    hoofd={raster}
                    zij={detail ? <PostDetail d={detail} sluit={() => kies(null)} /> : <Leeg titel="Kies een post">Klik op een post voor de cijfers en reacties.</Leeg>}
                />
            </Pagina>
        </>
    );
}
