import { Head, Link, router } from '@inertiajs/react';
import type Konva from 'konva';
import {
    ArrowLeft,
    Circle,
    Download,
    Film,
    Frame,
    Image as ImageIcon,
    Layers,
    Minus,
    Pause,
    Play,
    Plus,
    Redo2,
    Save,
    SlidersHorizontal,
    Sparkles,
    Square,
    Stamp,
    Type,
    Undo2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Layer, Line, Stage, Transformer } from 'react-konva';
import { Kaart, Pagina as PaginaContainer, PaginaKop, Sectie, Tabs } from '@/components/pagina';
import { naarBlob, opnameFormaat, useAfdruk, voorvertoning } from '@/components/studio/afdruk';
import type { BronSoort } from '@/components/studio/bronkiezer';
import { BronKiezer } from '@/components/studio/bronkiezer';
import { PaginaInhoud } from '@/components/studio/canvas';
import { laadAfbeelding, videoElement } from '@/components/studio/media';
import { LivePagina } from '@/components/studio/miniatuur';
import { Eigenschappen, LagenPaneel } from '@/components/studio/panelen';
import { useOntwerpStaat } from '@/components/studio/staat';
import type { Bron, CreatorVideo, Export, Laag, Logo, Media, OntwerpItem, Pagina, Soort } from '@/components/studio/typen';
import { bestandsgrootte, bronUrl, nieuweLaag, verstuur } from '@/components/studio/typen';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import exportsRoutes from '@/routes/team/studio/exports';
import ontwerpen from '@/routes/team/studio/ontwerpen';
import sjablonen from '@/routes/team/studio/sjablonen';

type Props = {
    soort: 'ontwerp' | 'sjabloon';
    item: OntwerpItem;
    exports: Export[];
    ffmpeg: boolean;
    maxVideoSeconden: number;
    maxUploadMb: number;
    media: Media[];
    creatorVideos: CreatorVideo[];
    logos: Logo[];
};

function useScherm(query: string): boolean {
    const [past, setPast] = useState(() => typeof window !== 'undefined' && window.matchMedia(query).matches);

    useEffect(() => {
        const mq = window.matchMedia(query);
        const zet = () => setPast(mq.matches);
        mq.addEventListener('change', zet);
        zet();

        return () => mq.removeEventListener('change', zet);
    }, [query]);

    return past;
}

function download(url: string) {
    const a = document.createElement('a');
    a.href = url;
    a.download = '';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

type Lijn = { punten: number[] };

/** MeStudio-editor: canvas met lagen, sjabloonvakken, ongedaan maken, hulplijnen en export op ware grootte. */
export default function Editor(props: Props) {
    const { soort, item, maxVideoSeconden, maxUploadMb, creatorVideos, logos, ffmpeg } = props;
    const W = item.width;
    const H = item.height;
    const staat = useOntwerpStaat(item.pages ?? [{ achtergrond: '#FFFFFF', lagen: [] }]);
    const { paginas } = staat;
    const [naam, setNaam] = useState(item.name);
    const [paginaIndex, setPaginaIndex] = useState(0);
    const [selectie, setSelectie] = useState<string | null>(null);
    const [uitsnedeModus, setUitsnedeModus] = useState(false);
    const [media, setMedia] = useState<Media[]>(props.media);
    const [exports, setExports] = useState<Export[]>(props.exports);
    const [kiezer, setKiezer] = useState<{ soort: BronSoort; doel: 'nieuw' | 'nieuw-vak' | 'laag' } | null>(null);
    const [melding, setMelding] = useState<{ tekst: string; fout?: boolean } | null>(null);
    const [bezig, setBezig] = useState<string | null>(null);
    const [voortgang, setVoortgang] = useState<number | null>(null);
    const [speelt, setSpeelt] = useState(false);
    const afdruk = useAfdruk();
    const telefoon = useScherm('(max-width: 767px)');

    const pagina = paginas[Math.min(paginaIndex, paginas.length - 1)];
    const gekozen = pagina.lagen.find((l) => l.id === selectie) ?? null;
    const heeftVideo = paginas.some((p) => p.lagen.some((l) => l.soort === 'video' && l.bron));
    const opslaanUrl = soort === 'ontwerp' ? ontwerpen.opslaan.url(item.id) : sjablonen.opslaan.url(item.id);

    const wijzig = useCallback((id: string, patch: Partial<Laag>, sleutel?: string) => staat.wijzigLaag(paginaIndex, id, patch, sleutel), [staat, paginaIndex]);

    const toon = (tekst: string, fout = false) => {
        setMelding({ tekst, fout });
        window.setTimeout(() => setMelding((m) => (m?.tekst === tekst ? null : m)), fout ? 9000 : 4000);
    };

    // Lettertypes laden voordat het canvas tekst meet.
    const [fontsKlaar, setFontsKlaar] = useState(0);
    useEffect(() => {
        void Promise.all(['400', '700', '800', '900'].flatMap((g) => [`${g} 40px "Montserrat Variable"`, `${g} 40px "Noto Sans Variable"`].map((f) => document.fonts.load(f)))).then(() =>
            setFontsKlaar((n) => n + 1),
        );
    }, []);

    // Editor: video's stil laten meelopen als je op afspelen drukt.
    useEffect(() => {
        const urls = pagina.lagen.filter((l) => l.soort === 'video').map((l) => bronUrl(l.bron)).filter((u): u is string => !!u);
        const els = urls.map(videoElement);
        els.forEach((v) => (speelt ? void v.play().catch(() => undefined) : v.pause()));
    }, [speelt, pagina.lagen]);

    // Niet opgeslagen werk niet kwijtraken.
    useEffect(() => {
        const waarschuw = (e: BeforeUnloadEvent) => {
            if (staat.gewijzigd) {
                e.preventDefault();
            }
        };
        window.addEventListener('beforeunload', waarschuw);

        return () => window.removeEventListener('beforeunload', waarschuw);
    }, [staat.gewijzigd]);

    const opslaan = useCallback(async () => {
        setBezig('opslaan');

        try {
            const canvas = await afdruk.maakCanvas(paginas[0], W, H);
            await verstuur(opslaanUrl, { method: 'PUT', json: { name: naam, pages: paginas, voorvertoning: voorvertoning(canvas) } });
            staat.setGewijzigd(false);
            toon('Opgeslagen.');

            return true;
        } catch (e) {
            toon((e as Error).message, true);

            return false;
        } finally {
            setBezig(null);
        }
    }, [afdruk, paginas, W, H, opslaanUrl, naam, staat]);

    const uploadExport = async (blob: Blob, kind: string, paginaNr: number): Promise<Export> => {
        const fd = new FormData();
        fd.append('soort', kind);
        fd.append('pagina', String(paginaNr));
        fd.append('bestand', blob, `export.${kind}`);
        const res = await verstuur<{ export: Export; exports: Export[] }>(exportsRoutes.store.url(item.id), { formulier: fd });
        setExports(res.exports);

        return res.export;
    };

    const exporteerAfbeelding = async (kind: 'png' | 'jpg', alle: boolean) => {
        if (!(await opslaan())) {
            return;
        }

        setBezig('export');

        try {
            const nummers = alle ? paginas.map((_, i) => i) : [paginaIndex];
            let laatste: Export | null = null;

            for (const i of nummers) {
                const canvas = await afdruk.maakCanvas(paginas[i], W, H);
                laatste = await uploadExport(await naarBlob(canvas, kind), kind, i + 1);

                if (!alle) {
                    download(laatste.url);
                }
            }

            if (alle) {
                download(exportsRoutes.zip.url(item.id));
            }

            toon(alle ? `${nummers.length} pagina's geëxporteerd, de zip komt eraan.` : `${kind.toUpperCase()} van ${W} bij ${H} pixels geëxporteerd.`);
        } catch (e) {
            toon((e as Error).message, true);
        } finally {
            setBezig(null);
        }
    };

    const exporteerVideo = async () => {
        if (!(await opslaan())) {
            return;
        }

        setBezig('video');
        setSpeelt(false);
        setVoortgang(0);

        try {
            const videoPagina = paginas.findIndex((p) => p.lagen.some((l) => l.soort === 'video' && l.bron));
            const { blob, extensie } = await afdruk.neemOp(paginas[videoPagina], W, H, maxVideoSeconden, setVoortgang);
            const e = await uploadExport(blob, extensie, videoPagina + 1);
            toon(
                extensie === 'webm'
                    ? ffmpeg
                        ? 'Video opgenomen. De server zet hem nu om naar MP4; even geduld.'
                        : 'Video opgenomen als webm. Omzetten naar MP4 kan niet: ffmpeg ontbreekt op de server.'
                    : 'Video opgenomen als MP4.',
            );

            if (e.status === 'klaar' || e.status === 'alleen_webm') {
                download(e.url);
            }
        } catch (err) {
            toon((err as Error).message, true);
        } finally {
            setBezig(null);
            setVoortgang(null);
        }
    };

    // Omzetten naar MP4 volgen tot het klaar is.
    useEffect(() => {
        if (soort !== 'ontwerp' || !exports.some((e) => e.status === 'omzetten')) {
            return;
        }

        const t = window.setInterval(async () => {
            const res = await verstuur<{ exports: Export[] }>(exportsRoutes.lijst.url(item.id), { method: 'GET' }).catch(() => null);

            if (res) {
                const nieuweMp4 = res.exports.find((e) => e.kind === 'mp4' && !exports.some((x) => x.id === e.id));

                if (nieuweMp4) {
                    toon('De MP4 staat klaar.');
                    download(nieuweMp4.url);
                }

                setExports(res.exports);
            }
        }, 3000);

        return () => window.clearInterval(t);
    }, [exports, item.id, soort]);

    const voegLaagToe = (s: Soort, extra: Partial<Laag> = {}): Laag => {
        const laag = nieuweLaag(s, W, H, extra);
        staat.voegToe(paginaIndex, laag);
        setSelectie(laag.id);

        return laag;
    };

    /** Logo's houden hun eigen verhouding: de hoogte volgt uit het bestand zodra het geladen is. */
    const logoVerhouding = (id: string, breedte: number, bron: Bron) => {
        const url = bronUrl(bron);

        if (url) {
            void laadAfbeelding(url)
                .then((img) => img.naturalWidth && wijzig(id, { hoogte: Math.round((breedte * img.naturalHeight) / img.naturalWidth) }, 'logo-maat'))
                .catch(() => undefined);
        }
    };

    const bronGekozen = (bron: Bron) => {
        if (!kiezer) {
            return;
        }

        if (kiezer.doel === 'laag' && gekozen) {
            wijzig(gekozen.id, { bron, uitsnede: { x: 0.5, y: 0.5, zoom: 1 } });

            if (gekozen.soort === 'logo') {
                logoVerhouding(gekozen.id, gekozen.breedte, bron);
            }
        } else if (kiezer.soort === 'logo') {
            const laag = voegLaagToe('logo', { bron });
            logoVerhouding(laag.id, laag.breedte, bron);
        } else {
            voegLaagToe(kiezer.soort, { bron });
        }
    };

    const gemeenschappelijk = {
        afdruk,
        staat,
        pagina,
        paginaIndex,
        setPaginaIndex,
        W,
        H,
        naam,
        setNaam,
        bezig,
        opslaan,
        exporteerAfbeelding,
        soort,
        item,
        melding,
        exports,
    };

    if (telefoon) {
        return (
            <>
                <Head title={naam} />
                <TelefoonWeergave
                    {...gemeenschappelijk}
                    fontsKlaar={fontsKlaar}
                    onKiesBron={(id, s) => {
                        setSelectie(id);
                        setKiezer({ soort: s, doel: 'laag' });
                    }}
                    wijzig={wijzig}
                />
                <BronKiezer
                    open={!!kiezer}
                    onOpenChange={(o) => !o && setKiezer(null)}
                    soort={kiezer?.soort ?? 'foto'}
                    media={media}
                    creatorVideos={creatorVideos}
                    logos={logos}
                    maxUploadMb={maxUploadMb}
                    onMediaErbij={(m) => setMedia((l) => [m, ...l])}
                    onKies={bronGekozen}
                />
                {afdruk.element}
            </>
        );
    }

    return (
        <>
            <Head title={naam} />
            <VolledigeEditor
                {...gemeenschappelijk}
                fontsKlaar={fontsKlaar}
                selectie={selectie}
                setSelectie={setSelectie}
                gekozen={gekozen}
                uitsnedeModus={uitsnedeModus}
                setUitsnedeModus={setUitsnedeModus}
                wijzig={wijzig}
                voegLaagToe={voegLaagToe}
                openKiezer={(s, doel) => setKiezer({ soort: s, doel })}
                creatorVideos={creatorVideos}
                heeftVideo={heeftVideo}
                speelt={speelt}
                setSpeelt={setSpeelt}
                exporteerVideo={exporteerVideo}
                voortgang={voortgang}
                ffmpeg={ffmpeg}
                maxVideoSeconden={maxVideoSeconden}
            />
            <BronKiezer
                open={!!kiezer}
                onOpenChange={(o) => !o && setKiezer(null)}
                soort={kiezer?.soort ?? 'foto'}
                media={media}
                creatorVideos={creatorVideos}
                logos={logos}
                maxUploadMb={maxUploadMb}
                onMediaErbij={(m) => setMedia((l) => [m, ...l])}
                onKies={bronGekozen}
            />
            {afdruk.element}
        </>
    );
}

type Gemeenschappelijk = {
    afdruk: ReturnType<typeof useAfdruk>;
    staat: ReturnType<typeof useOntwerpStaat>;
    pagina: Pagina;
    paginaIndex: number;
    setPaginaIndex: (i: number) => void;
    W: number;
    H: number;
    naam: string;
    setNaam: (n: string) => void;
    bezig: string | null;
    opslaan: () => Promise<boolean>;
    exporteerAfbeelding: (kind: 'png' | 'jpg', alle: boolean) => Promise<void>;
    soort: 'ontwerp' | 'sjabloon';
    item: OntwerpItem;
    melding: { tekst: string; fout?: boolean } | null;
    exports: Export[];
    fontsKlaar: number;
    wijzig: (id: string, patch: Partial<Laag>, sleutel?: string) => void;
};

function Melding({ melding }: { melding: Gemeenschappelijk['melding'] }) {
    return melding ? (
        <div role={melding.fout ? 'alert' : 'status'} className={cn('fixed inset-x-4 bottom-20 z-50 mx-auto max-w-md rounded-lg px-4 py-3 text-sm text-white shadow-lg md:bottom-6', melding.fout ? 'bg-rood' : 'bg-aubergine-dark')}>
            {melding.tekst}
        </div>
    ) : null;
}

function PaginaStrook({ aantal, actief, onKies, onErbij, onWeg }: { aantal: number; actief: number; onKies: (i: number) => void; onErbij?: () => void; onWeg?: () => void }) {
    return (
        <div className="flex flex-wrap items-center gap-1.5" aria-label="Pagina's van de carrousel">
            {Array.from({ length: aantal }, (_, i) => (
                <button
                    key={i}
                    type="button"
                    aria-current={i === actief}
                    onClick={() => onKies(i)}
                    className={cn('h-10 min-w-10 rounded-lg border px-3 text-sm font-semibold', i === actief ? 'border-violet bg-violet text-white' : 'bg-card hover:border-violet')}
                >
                    {i + 1}
                </button>
            ))}
            {onErbij && aantal < 10 && (
                <button type="button" onClick={onErbij} className="h-10 rounded-lg border border-dashed px-3 text-sm font-semibold hover:border-violet">
                    + Pagina
                </button>
            )}
            {onWeg && aantal > 1 && (
                <button type="button" onClick={onWeg} className="h-10 rounded-lg px-3 text-sm text-muted-foreground hover:text-rood">
                    Pagina {actief + 1} weg
                </button>
            )}
        </div>
    );
}

function ExportLijst({ exports }: { exports: Export[] }) {
    if (exports.length === 0) {
        return <p className="text-sm text-muted-foreground">Nog geen exports.</p>;
    }

    const status = (e: Export) =>
        ({ klaar: 'klaar', omzetten: 'wordt omgezet naar MP4...', mislukt: 'omzetten mislukt', alleen_webm: 'alleen webm (geen ffmpeg)' })[e.status];

    return (
        <ul className="space-y-1.5">
            {exports.slice(0, 12).map((e) => (
                <li key={e.id} className="flex items-center gap-2 rounded-lg border bg-card px-3 py-2 text-sm">
                    <div className="min-w-0 flex-1">
                        <p className="font-semibold">
                            {e.kind.toUpperCase()}, pagina {e.page}
                        </p>
                        <p className="truncate text-xs text-muted-foreground" title={e.message ?? undefined}>
                            {bestandsgrootte(e.size)}, {status(e)}
                        </p>
                    </div>
                    <a href={e.url} download className="grid size-10 shrink-0 place-items-center rounded-lg border hover:border-violet" aria-label={`Download ${e.kind}`}>
                        <Download className="size-4" />
                    </a>
                </li>
            ))}
        </ul>
    );
}

/** Op de telefoon: eenvoudige aanpassingen (tekst wijzigen, foto vervangen), opslaan en exporteren. */
function TelefoonWeergave(p: Gemeenschappelijk & { onKiesBron: (id: string, s: BronSoort) => void }) {
    const { pagina, staat, W, H } = p;
    const tekstLagen = pagina.lagen.filter((l) => l.soort === 'tekst' && (l.vak || !pagina.lagen.some((x) => x.vak)));
    const mediaLagen = pagina.lagen.filter((l) => (l.soort === 'foto' || l.soort === 'video') && !l.vergrendeld);

    return (
        <PaginaContainer smal>
            <PaginaKop
                boven={p.soort === 'sjabloon' ? 'Sjabloon' : 'Ontwerp'}
                titel={p.naam}
                omschrijving={`${p.item.format_naam}, ${W} bij ${H}. De volledige editor werkt op een laptop of tablet; hier pas je tekst en foto's aan.`}
                acties={
                    <>
                        <button type="button" data-test="studio-opslaan" onClick={() => void p.opslaan()} disabled={!!p.bezig} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet px-4 text-sm font-semibold text-white disabled:opacity-60">
                            <Save className="size-4" aria-hidden /> {p.bezig === 'opslaan' ? 'Opslaan...' : 'Opslaan'}
                        </button>
                        {p.soort === 'ontwerp' && (
                            <button type="button" data-test="studio-export-png" onClick={() => void p.exporteerAfbeelding('png', false)} disabled={!!p.bezig} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-card px-4 text-sm font-semibold">
                                <Download className="size-4" aria-hidden /> PNG
                            </button>
                        )}
                    </>
                }
            />
            <Kaart compact>
                <LivePagina key={p.fontsKlaar} pagina={pagina} W={W} H={H} className="mx-auto max-w-sm" />
            </Kaart>
            {staat.paginas.length > 1 && <PaginaStrook aantal={staat.paginas.length} actief={p.paginaIndex} onKies={p.setPaginaIndex} />}
            <Sectie titel="Aanpassen">
                <div className="space-y-3">
                    <label className="block space-y-1">
                        <span className="text-xs font-semibold text-muted-foreground">Naam van het ontwerp</span>
                        <input className="h-10 w-full rounded-lg border bg-background px-3" value={p.naam} onChange={(e) => p.setNaam(e.target.value)} />
                    </label>
                    {mediaLagen.map((l) => (
                        <div key={l.id} className="flex items-center gap-3 rounded-xl border bg-card px-3 py-2.5">
                            {l.soort === 'video' ? <Film className="size-5 text-violet" aria-hidden /> : <ImageIcon className="size-5 text-violet" aria-hidden />}
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-semibold">{l.vakNaam || l.naam}</p>
                                <p className="truncate text-sm text-muted-foreground">{l.bron ? 'Gevuld' : 'Nog leeg'}</p>
                            </div>
                            <button type="button" data-test="studio-kies-bron" onClick={() => p.onKiesBron(l.id, l.soort as BronSoort)} className="h-10 shrink-0 rounded-lg border px-3 text-sm font-semibold">
                                {l.bron ? 'Vervangen' : 'Plaatsen'}
                            </button>
                        </div>
                    ))}
                    {tekstLagen.map((l) => (
                        <label key={l.id} className="block space-y-1">
                            <span className="text-xs font-semibold text-muted-foreground">{l.vakNaam || l.naam}</span>
                            <textarea data-test="studio-tekst" className="min-h-20 w-full rounded-lg border bg-background px-3 py-2" value={l.tekst} onChange={(e) => p.wijzig(l.id, { tekst: e.target.value }, 'tekst')} />
                        </label>
                    ))}
                </div>
            </Sectie>
            {p.soort === 'ontwerp' && (
                <Sectie titel="Exports">
                    <ExportLijst exports={p.exports} />
                </Sectie>
            )}
            <Melding melding={p.melding} />
        </PaginaContainer>
    );
}

function Gereedschap({ label, onClick, children, test }: { label: string; onClick: () => void; children: ReactNode; test?: string }) {
    return (
        <button type="button" onClick={onClick} data-test={test} className="flex w-full flex-col items-center gap-1 rounded-lg px-1 py-2 text-[11px] font-semibold text-muted-foreground hover:bg-violet/10 hover:text-violet">
            {children}
            <span className="leading-tight">{label}</span>
        </button>
    );
}

function VolledigeEditor(
    p: Gemeenschappelijk & {
        selectie: string | null;
        setSelectie: (id: string | null) => void;
        gekozen: Laag | null;
        uitsnedeModus: boolean;
        setUitsnedeModus: (a: boolean) => void;
        voegLaagToe: (s: Soort, extra?: Partial<Laag>) => void;
        openKiezer: (s: BronSoort, doel: 'nieuw' | 'nieuw-vak' | 'laag') => void;
        creatorVideos: CreatorVideo[];
        heeftVideo: boolean;
        speelt: boolean;
        setSpeelt: (s: boolean) => void;
        exporteerVideo: () => Promise<void>;
        voortgang: number | null;
        ffmpeg: boolean;
        maxVideoSeconden: number;
    },
) {
    const { staat, pagina, paginaIndex, W, H, selectie, setSelectie, gekozen, wijzig } = p;
    const breed = useScherm('(min-width: 1280px)');
    const [paneel, setPaneel] = useState<'eigenschappen' | 'lagen' | 'exports'>('eigenschappen');
    const [lade, setLade] = useState(false);
    const vakRef = useRef<HTMLDivElement>(null);
    const stageRef = useRef<Konva.Stage>(null);
    const inhoudRef = useRef<Konva.Layer>(null);
    const trRef = useRef<Konva.Transformer>(null);
    const tekstRef = useRef<HTMLTextAreaElement>(null);
    const [passend, setPassend] = useState(0.4);
    const [zoom, setZoom] = useState<number | null>(null);
    const [lijnen, setLijnen] = useState<Lijn[]>([]);
    const doelen = useRef<{ v: number[]; h: number[] }>({ v: [], h: [] });
    const z = zoom ?? passend;

    // Passend in het scherm.
    useEffect(() => {
        const el = vakRef.current;

        if (!el) {
            return;
        }

        const ro = new ResizeObserver(() => setPassend(Math.max(0.05, Math.min((el.clientWidth - 48) / W, (el.clientHeight - 48) / H))));
        ro.observe(el);

        return () => ro.disconnect();
    }, [W, H]);

    // Transformer op de gekozen laag.
    useEffect(() => {
        const tr = trRef.current;
        const node = selectie ? stageRef.current?.findOne(`#${selectie}`) : null;

        if (!tr) {
            return;
        }

        tr.nodes(node && gekozen && !gekozen.vergrendeld && !p.uitsnedeModus ? [node] : []);
        tr.getLayer()?.batchDraw();
    }, [selectie, gekozen, p.uitsnedeModus, pagina, p.fontsKlaar]);

    // Sneltoetsen: ongedaan maken, opnieuw, opslaan, verwijderen, pijltjes.
    useEffect(() => {
        const toets = (e: KeyboardEvent) => {
            const inVeld = (e.target as HTMLElement)?.closest('input, textarea, select, [contenteditable]');
            const mod = e.metaKey || e.ctrlKey;

            if (mod && e.key.toLowerCase() === 's') {
                e.preventDefault();
                void p.opslaan();
            } else if (inVeld) {
                return;
            } else if (mod && e.key.toLowerCase() === 'z') {
                e.preventDefault();

                if (e.shiftKey) {
                    staat.opnieuw();
                } else {
                    staat.ongedaan();
                }
            } else if (mod && e.key.toLowerCase() === 'y') {
                e.preventDefault();
                staat.opnieuw();
            } else if (gekozen && (e.key === 'Delete' || e.key === 'Backspace')) {
                e.preventDefault();
                staat.verwijder(paginaIndex, gekozen.id);
                setSelectie(null);
            } else if (gekozen && !gekozen.vergrendeld && e.key.startsWith('Arrow')) {
                e.preventDefault();
                const d = e.shiftKey ? 10 : 1;
                const dx = e.key === 'ArrowLeft' ? -d : e.key === 'ArrowRight' ? d : 0;
                const dy = e.key === 'ArrowUp' ? -d : e.key === 'ArrowDown' ? d : 0;
                wijzig(gekozen.id, { x: gekozen.x + dx, y: gekozen.y + dy }, 'pijl');
            } else if (e.key === 'Escape') {
                setSelectie(null);
                p.setUitsnedeModus(false);
            }
        };
        window.addEventListener('keydown', toets);

        return () => window.removeEventListener('keydown', toets);
    }, [p, staat, gekozen, paginaIndex, setSelectie, wijzig]);

    // Uitlijnhulplijnen: midden en randen van het canvas en van de andere lagen.
    const sleepStart = useCallback(
        (node: Konva.Node) => {
            const laag = inhoudRef.current;

            if (!laag) {
                return;
            }

            const v = [0, W / 2, W];
            const h = [0, H / 2, H];
            laag.find('.laag').forEach((n) => {
                if (n === node || !n.visible()) {
                    return;
                }

                const r = n.getClientRect({ relativeTo: laag });
                v.push(r.x, r.x + r.width / 2, r.x + r.width);
                h.push(r.y, r.y + r.height / 2, r.y + r.height);
            });
            doelen.current = { v, h };
        },
        [W, H],
    );

    const sleep = useCallback(
        (node: Konva.Node) => {
            const laag = inhoudRef.current;

            if (!laag || !node.hasName('laag')) {
                return;
            }

            const grens = 8 / z;
            const r = node.getClientRect({ relativeTo: laag });
            const zoek = (randen: number[], doel: number[]) => {
                let beste: { verschil: number; lijn: number } | null = null;

                for (const rand of randen) {
                    for (const d of doel) {
                        const verschil = d - rand;

                        if (Math.abs(verschil) < grens && (!beste || Math.abs(verschil) < Math.abs(beste.verschil))) {
                            beste = { verschil, lijn: d };
                        }
                    }
                }

                return beste;
            };
            const sv = zoek([r.x, r.x + r.width / 2, r.x + r.width], doelen.current.v);
            const sh = zoek([r.y, r.y + r.height / 2, r.y + r.height], doelen.current.h);
            const nieuw: Lijn[] = [];

            if (sv) {
                node.x(node.x() + sv.verschil);
                nieuw.push({ punten: [sv.lijn, -H, sv.lijn, H * 2] });
            }

            if (sh) {
                node.y(node.y() + sh.verschil);
                nieuw.push({ punten: [-W, sh.lijn, W * 2, sh.lijn] });
            }

            setLijnen(nieuw);
        },
        [z, W, H],
    );

    const panelen = (
        <div className="space-y-4">
            <Tabs
                tabs={[
                    { waarde: 'eigenschappen', label: 'Eigenschappen' },
                    { waarde: 'lagen', label: 'Lagen', aantal: pagina.lagen.length },
                    ...(p.soort === 'ontwerp' ? [{ waarde: 'exports' as const, label: 'Exports', aantal: p.exports.length }] : []),
                ]}
                actief={paneel}
                onKies={setPaneel}
            />
            {paneel === 'eigenschappen' && (
                <Eigenschappen
                    laag={gekozen}
                    pagina={pagina}
                    W={W}
                    H={H}
                    creatorVideos={p.creatorVideos}
                    uitsnedeModus={p.uitsnedeModus}
                    onUitsnedeModus={p.setUitsnedeModus}
                    onWijzig={(patch, sleutel) => gekozen && wijzig(gekozen.id, patch, sleutel)}
                    onPagina={(patch) => staat.wijzigPagina(paginaIndex, patch)}
                    onKiesBron={(s) => p.openKiezer(s, 'laag')}
                    tekstRef={tekstRef}
                />
            )}
            {paneel === 'lagen' && (
                <LagenPaneel
                    pagina={pagina}
                    selectie={selectie}
                    onKies={(id) => {
                        setSelectie(id);
                        setPaneel('eigenschappen');
                    }}
                    onWijzig={(id, patch) => wijzig(id, patch)}
                    onVerschuif={(id, r) => staat.verschuif(paginaIndex, id, r)}
                    onDupliceer={(id) => setSelectie(staat.dupliceer(paginaIndex, id))}
                    onVerwijder={(id) => {
                        staat.verwijder(paginaIndex, id);
                        setSelectie(null);
                    }}
                />
            )}
            {paneel === 'exports' && <ExportLijst exports={p.exports} />}
        </div>
    );

    const knop = 'inline-flex h-9 items-center gap-1.5 rounded-lg border bg-card px-2.5 text-sm font-semibold hover:border-violet disabled:opacity-40';
    const [exportOpen, setExportOpen] = useState(false);
    const videoKan = useMemo(() => opnameFormaat() !== null, []);

    return (
        <div className="flex h-[calc(100dvh-5rem)] min-h-0 flex-col overflow-hidden" data-test="studio-editor">
            {/* Werkbalk */}
            <div className="flex flex-wrap items-center gap-2 border-b px-3 py-2">
                <Link href={p.soort === 'ontwerp' ? ontwerpen.index.url() : sjablonen.index.url()} className="grid size-9 place-items-center rounded-lg hover:bg-muted" aria-label="Terug naar het overzicht">
                    <ArrowLeft className="size-4" />
                </Link>
                <input
                    aria-label="Naam"
                    value={p.naam}
                    onChange={(e) => {
                        p.setNaam(e.target.value);
                        staat.setGewijzigd(true);
                    }}
                    className="h-9 w-40 min-w-0 flex-1 rounded-lg border-transparent bg-transparent px-2 font-heading font-bold hover:border-border focus:border-violet focus:outline-none lg:max-w-xs"
                />
                <span className="hidden text-xs text-muted-foreground xl:inline">
                    {p.soort === 'sjabloon' ? 'Sjabloon, ' : ''}
                    {p.item.format_naam}, {W} x {H}
                </span>
                <div className="flex items-center gap-1">
                    <button type="button" className={knop} onClick={staat.ongedaan} disabled={!staat.kanOngedaan} aria-label="Ongedaan maken" title="Ongedaan maken (Ctrl+Z)">
                        <Undo2 className="size-4" />
                    </button>
                    <button type="button" className={knop} onClick={staat.opnieuw} disabled={!staat.kanOpnieuw} aria-label="Opnieuw" title="Opnieuw (Ctrl+Shift+Z)">
                        <Redo2 className="size-4" />
                    </button>
                </div>
                <div className="flex items-center gap-1">
                    <button type="button" className={knop} onClick={() => setZoom(Math.max(0.05, z / 1.25))} aria-label="Uitzoomen">
                        <Minus className="size-4" />
                    </button>
                    <button type="button" className={cn(knop, 'w-16 justify-center tabular-nums')} onClick={() => setZoom(null)} title="Passend in het scherm">
                        {Math.round(z * 100)}%
                    </button>
                    <button type="button" className={knop} onClick={() => setZoom(Math.min(3, z * 1.25))} aria-label="Inzoomen">
                        <Plus className="size-4" />
                    </button>
                </div>
                {p.heeftVideo && (
                    <button type="button" className={knop} onClick={() => p.setSpeelt(!p.speelt)} aria-label={p.speelt ? 'Video pauzeren' : 'Video afspelen'}>
                        {p.speelt ? <Pause className="size-4" /> : <Play className="size-4" />}
                    </button>
                )}
                <div className="ml-auto flex items-center gap-1.5">
                    {!breed && (
                        <>
                            <button type="button" className={knop} onClick={() => { setPaneel('lagen'); setLade(true); }}>
                                <Layers className="size-4" /> Lagen
                            </button>
                            <button type="button" className={knop} onClick={() => { setPaneel('eigenschappen'); setLade(true); }} data-test="studio-open-eigenschappen">
                                <SlidersHorizontal className="size-4" /> Eigenschappen
                            </button>
                        </>
                    )}
                    {p.soort === 'sjabloon' ? (
                        <button type="button" className={knop} onClick={async () => (await p.opslaan()) && router.post(sjablonen.gebruiken.url(p.item.id))}>
                            Gebruiken
                        </button>
                    ) : (
                        <div className="relative">
                            <button type="button" className={knop} onClick={() => setExportOpen(!exportOpen)} aria-expanded={exportOpen} data-test="studio-exporteren">
                                <Download className="size-4" /> Exporteren
                            </button>
                            {exportOpen && (
                                <div className="absolute right-0 z-30 mt-1 w-72 space-y-1 rounded-xl border bg-popover p-2 shadow-lg" onClick={() => setExportOpen(false)}>
                                    <MenuKnop test="studio-export-png" onClick={() => void p.exporteerAfbeelding('png', false)}>
                                        PNG{staat.paginas.length > 1 ? `, pagina ${paginaIndex + 1}` : ''} ({W} x {H})
                                    </MenuKnop>
                                    <MenuKnop onClick={() => void p.exporteerAfbeelding('jpg', false)}>JPG{staat.paginas.length > 1 ? `, pagina ${paginaIndex + 1}` : ''}</MenuKnop>
                                    {staat.paginas.length > 1 && <MenuKnop onClick={() => void p.exporteerAfbeelding('png', true)}>Alle pagina's als PNG plus zip</MenuKnop>}
                                    {p.heeftVideo && (
                                        <MenuKnop test="studio-export-video" onClick={() => void p.exporteerVideo()} disabled={!videoKan}>
                                            Video (opnemen, hooguit {p.maxVideoSeconden} s){!videoKan ? ': niet in deze browser' : p.ffmpeg ? ', daarna MP4' : ', als webm'}
                                        </MenuKnop>
                                    )}
                                    <p className="px-2 pt-1 text-xs text-muted-foreground">Exporteren slaat het ontwerp eerst op. Bestanden blijven privé in MeStudio.</p>
                                </div>
                            )}
                        </div>
                    )}
                    {p.soort === 'ontwerp' && (
                        <button type="button" className={cn(knop, 'hidden lg:inline-flex')} onClick={async () => (await p.opslaan()) && router.post(ontwerpen.sjabloon.url(p.item.id), { name: p.naam })}>
                            Opslaan als sjabloon
                        </button>
                    )}
                    <button type="button" data-test="studio-opslaan" onClick={() => void p.opslaan()} disabled={!!p.bezig} className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-violet px-3 text-sm font-semibold text-white hover:bg-violet/90 disabled:opacity-60">
                        <Save className="size-4" /> {p.bezig === 'opslaan' ? 'Opslaan...' : staat.gewijzigd ? 'Opslaan' : 'Opgeslagen'}
                    </button>
                </div>
            </div>

            <div className="flex min-h-0 flex-1">
                {/* Toevoegen */}
                <div className="w-[72px] shrink-0 space-y-0.5 overflow-y-auto border-r p-1.5" aria-label="Toevoegen">
                    <Gereedschap label="Tekst" test="studio-tekst-erbij" onClick={() => p.voegLaagToe('tekst')}>
                        <Type className="size-5" />
                    </Gereedschap>
                    <Gereedschap label="Foto" onClick={() => p.openKiezer('foto', 'nieuw')}>
                        <ImageIcon className="size-5" />
                    </Gereedschap>
                    <Gereedschap label="Video" onClick={() => p.openKiezer('video', 'nieuw')}>
                        <Film className="size-5" />
                    </Gereedschap>
                    <Gereedschap label="Fotovak" onClick={() => p.voegLaagToe('foto', { vak: true, vakNaam: 'Fotovak', naam: 'Fotovak' })}>
                        <Frame className="size-5" />
                    </Gereedschap>
                    <Gereedschap label="Videovak" onClick={() => p.voegLaagToe('video', { vak: true, vakNaam: 'Videovak', naam: 'Videovak' })}>
                        <Frame className="size-5 text-rood" />
                    </Gereedschap>
                    <Gereedschap label="Rechthoek" onClick={() => p.voegLaagToe('vorm', { vorm: 'rechthoek', hoekafronding: 24 })}>
                        <Square className="size-5" />
                    </Gereedschap>
                    <Gereedschap label="Cirkel" onClick={() => p.voegLaagToe('vorm', { vorm: 'cirkel', naam: 'Cirkel' })}>
                        <Circle className="size-5" />
                    </Gereedschap>
                    <Gereedschap label="Verloop" onClick={() => p.voegLaagToe('verloop')}>
                        <Sparkles className="size-5" />
                    </Gereedschap>
                    <Gereedschap label="Logo" onClick={() => p.openKiezer('logo', 'nieuw')}>
                        <Stamp className="size-5" />
                    </Gereedschap>
                </div>

                {/* Canvas */}
                <div className="flex min-w-0 flex-1 flex-col">
                    <div ref={vakRef} className="flex min-h-0 flex-1 overflow-auto bg-[#E9E7F0] dark:bg-muted">
                        <div className="m-auto p-6">
                            <div className="shadow-xl shadow-aubergine/15" style={{ width: W * z, height: H * z }}>
                                <Stage
                                    ref={stageRef}
                                    width={W * z}
                                    height={H * z}
                                    scaleX={z}
                                    scaleY={z}
                                    onMouseDown={(e) => {
                                        if (e.target === e.target.getStage() || e.target.hasName('achtergrond')) {
                                            setSelectie(null);
                                            p.setUitsnedeModus(false);
                                        }
                                    }}
                                    onTouchStart={(e) => {
                                        if (e.target === e.target.getStage() || e.target.hasName('achtergrond')) {
                                            setSelectie(null);
                                        }
                                    }}
                                >
                                    <Layer ref={inhoudRef}>
                                        <PaginaInhoud
                                            key={`${paginaIndex}-${p.fontsKlaar}`}
                                            pagina={pagina}
                                            W={W}
                                            H={H}
                                            modus="editor"
                                            selectie={selectie}
                                            uitsnedeModus={p.uitsnedeModus}
                                            speelt={p.speelt}
                                            onKies={(id) => {
                                                if (id !== selectie) {
                                                    p.setUitsnedeModus(false);
                                                }

                                                setSelectie(id);
                                            }}
                                            onWijzig={(id, patch) => wijzig(id, patch)}
                                            onSleepStart={sleepStart}
                                            onSleep={sleep}
                                            onSleepEind={() => setLijnen([])}
                                            onDubbel={(l) => {
                                                if (l.soort === 'tekst') {
                                                    setPaneel('eigenschappen');

                                                    if (!breed) {
                                                        setLade(true);
                                                    }

                                                    setTimeout(() => tekstRef.current?.focus(), 50);
                                                } else if ((l.soort === 'foto' || l.soort === 'video') && l.bron) {
                                                    p.setUitsnedeModus(true);
                                                }
                                            }}
                                        />
                                    </Layer>
                                    <Layer listening>
                                        {lijnen.map((l, i) => (
                                            <Line key={i} points={l.punten} stroke="#D9303E" strokeWidth={1 / z} dash={[6 / z, 4 / z]} listening={false} />
                                        ))}
                                        <Transformer
                                            ref={trRef}
                                            rotateEnabled
                                            keepRatio={gekozen?.soort === 'logo'}
                                            enabledAnchors={
                                                gekozen?.soort === 'logo'
                                                    ? ['top-left', 'top-right', 'bottom-left', 'bottom-right']
                                                    : ['top-left', 'top-center', 'top-right', 'middle-right', 'middle-left', 'bottom-left', 'bottom-center', 'bottom-right']
                                            }
                                            anchorSize={10}
                                            anchorCornerRadius={5}
                                            borderStroke="#614ED9"
                                            anchorStroke="#614ED9"
                                            rotationSnaps={[0, 45, 90, 135, 180, 225, 270, 315]}
                                            boundBoxFunc={(oud, nieuw) => (Math.abs(nieuw.width) < 10 || Math.abs(nieuw.height) < 10 ? oud : nieuw)}
                                        />
                                    </Layer>
                                </Stage>
                            </div>
                        </div>
                    </div>
                    {(staat.paginas.length > 1 || p.item.format === 'carrousel') && (
                        <div className="border-t px-3 py-2">
                            <PaginaStrook
                                aantal={staat.paginas.length}
                                actief={paginaIndex}
                                onKies={(i) => {
                                    p.setPaginaIndex(i);
                                    setSelectie(null);
                                }}
                                onErbij={() => {
                                    staat.paginaErbij(paginaIndex, false);
                                    p.setPaginaIndex(paginaIndex + 1);
                                }}
                                onWeg={() => {
                                    staat.paginaWeg(paginaIndex);
                                    p.setPaginaIndex(Math.max(0, paginaIndex - 1));
                                }}
                            />
                        </div>
                    )}
                    {p.uitsnedeModus && <p className="border-t bg-violet/10 px-3 py-1.5 text-sm text-violet">Sleep de foto of video binnen het vak. Esc of een klik naast het vak is klaar.</p>}
                    {p.voortgang !== null && (
                        <div className="border-t px-3 py-2" role="status">
                            <p className="text-sm font-semibold">Video opnemen... {Math.round(p.voortgang * 100)}% (laat dit tabblad open)</p>
                            <div className="mt-1 h-2 overflow-hidden rounded-full bg-muted">
                                <div className="h-full bg-brand-gradient" style={{ width: `${p.voortgang * 100}%` }} />
                            </div>
                        </div>
                    )}
                </div>

                {breed && <aside className="w-80 shrink-0 overflow-y-auto border-l p-4">{panelen}</aside>}
            </div>

            {!breed && (
                <Sheet open={lade} onOpenChange={setLade}>
                    <SheetContent side="right" className="w-[340px] overflow-y-auto p-4 sm:max-w-sm">
                        <SheetHeader className="p-0">
                            <SheetTitle>{paneel === 'lagen' ? 'Lagen' : paneel === 'exports' ? 'Exports' : 'Eigenschappen'}</SheetTitle>
                        </SheetHeader>
                        {panelen}
                    </SheetContent>
                </Sheet>
            )}
            <Melding melding={p.melding} />
        </div>
    );
}

function MenuKnop({ onClick, children, disabled, test }: { onClick: () => void; children: ReactNode; disabled?: boolean; test?: string }) {
    return (
        <button type="button" data-test={test} disabled={disabled} onClick={onClick} className="block w-full rounded-lg px-2 py-2 text-left text-sm hover:bg-muted disabled:opacity-50">
            {children}
        </button>
    );
}
