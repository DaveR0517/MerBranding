import { Head, Link, router, usePage } from '@inertiajs/react';
import type Konva from 'konva';
import {
    ArrowLeft,
    Circle,
    Download,
    Film,
    Frame,
    Grid3x3,
    Image as ImageIcon,
    LayoutTemplate,
    Minus,
    MoreHorizontal,
    Pause,
    Play,
    Plus,
    Redo2,
    Save,
    ShieldCheck,
    Sparkles,
    Square,
    Stamp,
    Type,
    Undo2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Group, Layer, Line, Rect, Stage, Text, Transformer } from 'react-konva';
import { Kaart, Pagina as PaginaContainer, PaginaKop, Sectie } from '@/components/pagina';
import { naarBlob, opnameFormaat, useAfdruk, voorvertoning } from '@/components/studio/afdruk';
import type { BronSoort } from '@/components/studio/bronkiezer';
import { BronKiezer } from '@/components/studio/bronkiezer';
import { isVastVak, PaginaInhoud } from '@/components/studio/canvas';
import { MerkContext, isDonker, vrijeRuimte } from '@/components/studio/merk';
import type { Bevinding } from '@/components/studio/merkcontrole';
import { controleer, herstelAlles, niveauVan } from '@/components/studio/merkcontrole';
import { laadAfbeelding, videoElement } from '@/components/studio/media';
import { LivePagina } from '@/components/studio/miniatuur';
import { Eigenschappen, LagenPaneel, MerkcontrolePaneel, NIVEAU_KLEUR, staatVast } from '@/components/studio/panelen';
import { useOntwerpStaat } from '@/components/studio/staat';
import type { Bron, CreatorVideo, Export, Laag, Logo, Media, Merkkit, OntwerpItem, Pagina, Soort } from '@/components/studio/typen';
import { bestandsgrootte, bronUrl, nieuweLaag, verstuur } from '@/components/studio/typen';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import exportsRoutes from '@/routes/team/studio/exports';
import ontwerpen from '@/routes/team/studio/ontwerpen';
import sjablonen from '@/routes/team/studio/sjablonen';
import type { Auth } from '@/types/auth';

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
    merkkit: Merkkit;
};

type Paneel = 'eigenschappen' | 'lagen' | 'merk' | 'exports';

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

/** Breedte van een element (de editor past zijn werkbalk en panelen aan op de ruimte die er echt is, naast de zijbalk). */
function useBreedte<T extends HTMLElement>(): [React.RefObject<T | null>, number] {
    const ref = useRef<T>(null);
    const [breedte, setBreedte] = useState(1440);

    useEffect(() => {
        const el = ref.current;

        if (!el) {
            return;
        }

        const ro = new ResizeObserver(() => setBreedte(el.clientWidth));
        ro.observe(el);

        return () => ro.disconnect();
    }, []);

    return [ref, breedte];
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

/** MeStudio-editor: canvas met lagen, merkslot (stijlen, vaste merkelementen en vakken), merkcontrole en export op ware grootte. */
export default function Editor(props: Props) {
    const { soort, item, maxVideoSeconden, maxUploadMb, creatorVideos, logos, ffmpeg, merkkit } = props;
    const { auth } = usePage().props as unknown as { auth: Auth };
    const superAdmin = !!auth.is_super_admin;
    const W = item.width;
    const H = item.height;
    const formaat = item.format;
    const staat = useOntwerpStaat(item.pages ?? [{ achtergrond: '#FFFFFF', lagen: [] }]);
    const { paginas } = staat;
    const [naam, setNaam] = useState(item.name);
    const [paginaIndex, setPaginaIndex] = useState(0);
    const [selectie, setSelectie] = useState<string | null>(null);
    const [uitsnedeModus, setUitsnedeModus] = useState(false);
    const [kaderBewerken, setKaderBewerken] = useState<string | null>(null);
    const [media, setMedia] = useState<Media[]>(props.media);
    const [exports, setExports] = useState<Export[]>(props.exports);
    const [kiezer, setKiezer] = useState<{ soort: BronSoort; doel: 'nieuw' | 'laag' } | null>(null);
    const [melding, setMelding] = useState<{ tekst: string; fout?: boolean } | null>(null);
    const [bezig, setBezig] = useState<string | null>(null);
    const [voortgang, setVoortgang] = useState<number | null>(null);
    const [speelt, setSpeelt] = useState(false);
    const [poort, setPoort] = useState<{ actie: () => Promise<void> } | null>(null);
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

    // Lettertypes laden voordat het canvas (en de merkcontrole) tekst meet.
    const [fontsKlaar, setFontsKlaar] = useState(0);
    useEffect(() => {
        void Promise.all(['400', '700', '800', '900'].flatMap((g) => [`${g} 40px "Montserrat Variable"`, `${g} 40px "Noto Sans Variable"`].map((f) => document.fonts.load(f)))).then(() =>
            setFontsKlaar((n) => n + 1),
        );
    }, []);

    const controleContext = useMemo(() => ({ merk: merkkit, formaat, W, H }), [merkkit, formaat, W, H]);
    // fontsKlaar hoort erbij: tekst wordt pas goed gemeten als de lettertypes er zijn.
    const bevindingen = useMemo(() => (fontsKlaar >= 0 ? controleer(paginas, controleContext) : []), [paginas, controleContext, fontsKlaar]);
    const niveau = niveauVan(bevindingen);

    // Editor: video's stil laten meelopen als je op afspelen drukt.
    useEffect(() => {
        const urls = pagina.lagen.filter((l) => l.soort === 'video').map((l) => bronUrl(l.bron)).filter((u): u is string => !!u);
        urls.map(videoElement).forEach((v) => (speelt ? void v.play().catch(() => undefined) : v.pause()));
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

    /** Merkcontrole bij exporteren: rood houdt tegen (met herstel), Super Admin kan met reden toch. */
    const bewaakt = (actie: () => Promise<void>) => () => {
        if (niveau === 'rood') {
            setPoort({ actie });

            return;
        }

        void actie();
    };

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

            for (const i of nummers) {
                const canvas = await afdruk.maakCanvas(paginas[i], W, H);
                const e = await uploadExport(await naarBlob(canvas, kind), kind, i + 1);

                if (!alle) {
                    download(e.url);
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
        const laag = nieuweLaag(s, W, H, merkkit, formaat, extra);
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
            // Alleen de inhoud wisselt; het kader van een vak blijft staan.
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

    const herstel = (b: Bevinding) => {
        if (b.herstel) {
            staat.vervang(b.herstel);
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
        exporteerPng: bewaakt(() => exporteerAfbeelding('png', false)),
        soort,
        item,
        melding,
        exports,
        fontsKlaar,
        wijzig,
        superAdmin,
        niveau,
    };

    const kiezerElement = (
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
    );

    return (
        <MerkContext.Provider value={merkkit}>
            <Head title={naam} />
            {telefoon ? (
                <TelefoonWeergave
                    {...gemeenschappelijk}
                    onKiesBron={(id, s) => {
                        setSelectie(id);
                        setKiezer({ soort: s, doel: 'laag' });
                    }}
                />
            ) : (
                <VolledigeEditor
                    {...gemeenschappelijk}
                    selectie={selectie}
                    setSelectie={setSelectie}
                    gekozen={gekozen}
                    uitsnedeModus={uitsnedeModus}
                    setUitsnedeModus={setUitsnedeModus}
                    kaderBewerken={kaderBewerken}
                    setKaderBewerken={setKaderBewerken}
                    voegLaagToe={voegLaagToe}
                    openKiezer={(s, doel) => setKiezer({ soort: s, doel })}
                    creatorVideos={creatorVideos}
                    heeftVideo={heeftVideo}
                    speelt={speelt}
                    setSpeelt={setSpeelt}
                    exporteer={{
                        jpg: bewaakt(() => exporteerAfbeelding('jpg', false)),
                        alle: bewaakt(() => exporteerAfbeelding('png', true)),
                        video: bewaakt(exporteerVideo),
                    }}
                    voortgang={voortgang}
                    ffmpeg={ffmpeg}
                    maxVideoSeconden={maxVideoSeconden}
                    merkkit={merkkit}
                    bevindingen={bevindingen}
                    onHerstel={herstel}
                    onHerstelAlles={() => staat.vervang((p) => herstelAlles(p, controleContext))}
                />
            )}
            {kiezerElement}
            <ExportPoort
                open={!!poort}
                onOpenChange={(o) => !o && setPoort(null)}
                bevindingen={bevindingen.filter((b) => b.niveau === 'rood')}
                superAdmin={superAdmin}
                onHerstel={herstel}
                onHerstelAlles={() => staat.vervang((p) => herstelAlles(p, controleContext))}
                onExporteer={async (reden) => {
                    const actie = poort?.actie;
                    setPoort(null);

                    if (reden) {
                        await verstuur(ontwerpen.merkcontrole.url(item.id), { json: { reden, bevindingen: bevindingen.filter((b) => b.niveau === 'rood').map((b) => b.tekst) } });
                    }

                    if (actie) {
                        await actie();
                    }
                }}
            />
            {afdruk.element}
        </MerkContext.Provider>
    );
}

/** Rode merkcontrole bij exporteren: herstellen, of (Super Admin) met reden toch exporteren. */
function ExportPoort({
    open,
    onOpenChange,
    bevindingen,
    superAdmin,
    onHerstel,
    onHerstelAlles,
    onExporteer,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    bevindingen: Bevinding[];
    superAdmin: boolean;
    onHerstel: (b: Bevinding) => void;
    onHerstelAlles: () => void;
    onExporteer: (reden?: string) => Promise<void>;
}) {
    const [reden, setReden] = useState('');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-lg" data-test="studio-exportpoort">
                <DialogHeader>
                    <DialogTitle>{bevindingen.length ? 'Eerst de merkcontrole' : 'Klaar om te exporteren'}</DialogTitle>
                    <DialogDescription>
                        {bevindingen.length
                            ? 'Dit ontwerp wijkt af van de huisstijl. Herstel de rode punten; waar het automatisch kan, staat er een knop bij.'
                            : 'Alle rode punten zijn hersteld.'}
                    </DialogDescription>
                </DialogHeader>
                {bevindingen.length > 0 && (
                    <>
                        <ul className="space-y-1.5">
                            {bevindingen.map((b, i) => (
                                <li key={i} className="flex items-start gap-2 rounded-lg border p-2.5 text-sm">
                                    <span className={cn('mt-1.5 size-2.5 shrink-0 rounded-full', NIVEAU_KLEUR.rood)} aria-hidden />
                                    <span className="min-w-0 flex-1">{b.tekst}</span>
                                    {b.herstel && (
                                        <button type="button" onClick={() => onHerstel(b)} className="shrink-0 font-semibold text-violet hover:underline">
                                            Herstel
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                        {bevindingen.some((b) => b.herstel) && (
                            <button type="button" data-test="studio-poort-herstel" onClick={onHerstelAlles} className="h-10 w-full rounded-lg bg-violet text-sm font-semibold text-white">
                                Alles herstellen wat kan
                            </button>
                        )}
                        {superAdmin && (
                            <div className="space-y-2 border-t pt-3">
                                <label className="block space-y-1">
                                    <span className="text-xs font-semibold text-muted-foreground">Super Admin: toch exporteren, met reden (komt in het logboek)</span>
                                    <textarea className="min-h-16 w-full rounded-lg border bg-background px-3 py-2 text-sm" value={reden} onChange={(e) => setReden(e.target.value)} />
                                </label>
                                <button
                                    type="button"
                                    disabled={reden.trim().length < 5}
                                    onClick={() => void onExporteer(reden.trim())}
                                    className="h-10 w-full rounded-lg border text-sm font-semibold hover:border-rood disabled:opacity-50"
                                >
                                    Toch exporteren
                                </button>
                            </div>
                        )}
                    </>
                )}
                {bevindingen.length === 0 && (
                    <button type="button" data-test="studio-poort-exporteer" onClick={() => void onExporteer()} className="h-10 w-full rounded-lg bg-violet text-sm font-semibold text-white">
                        Nu exporteren
                    </button>
                )}
            </DialogContent>
        </Dialog>
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
    exporteerPng: () => void;
    soort: 'ontwerp' | 'sjabloon';
    item: OntwerpItem;
    melding: { tekst: string; fout?: boolean } | null;
    exports: Export[];
    fontsKlaar: number;
    wijzig: (id: string, patch: Partial<Laag>, sleutel?: string) => void;
    superAdmin: boolean;
    niveau: 'groen' | 'oranje' | 'rood';
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
    const mediaLagen = pagina.lagen.filter((l) => (l.soort === 'foto' || l.soort === 'video') && (l.vak || !l.vergrendeld));

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
                            <button type="button" data-test="studio-export-png" onClick={p.exporteerPng} disabled={!!p.bezig} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-card px-4 text-sm font-semibold">
                                <Download className="size-4" aria-hidden /> PNG
                            </button>
                        )}
                    </>
                }
            />
            <Kaart compact>
                <LivePagina key={p.fontsKlaar} pagina={pagina} W={W} H={H} className="mx-auto max-w-sm" />
                <p className="mt-2 flex items-center gap-2 text-sm">
                    <span className={cn('size-2.5 rounded-full', NIVEAU_KLEUR[p.niveau])} aria-hidden />
                    Merkcontrole: {{ groen: 'in orde', oranje: 'let op', rood: 'herstel nodig voor export' }[p.niveau]}
                </p>
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

/** Knop in de werkbalk: met tekst als er ruimte is, anders een icoon met tooltip. */
function WerkKnop({ label, icoon, compact, onClick, disabled, test, actief, className }: { label: string; icoon: ReactNode; compact: boolean; onClick: () => void; disabled?: boolean; test?: string; actief?: boolean; className?: string }) {
    const knop = (
        <button
            type="button"
            data-test={test}
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            aria-pressed={actief}
            className={cn('inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border bg-card text-sm font-semibold hover:border-violet disabled:opacity-40', compact ? 'w-9 justify-center' : 'px-2.5', actief && 'border-violet text-violet', className)}
        >
            {icoon}
            {!compact && <span>{label}</span>}
        </button>
    );

    return compact ? (
        <Tooltip>
            <TooltipTrigger asChild>{knop}</TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    ) : (
        knop
    );
}

const PANEEL_LABEL: Record<Paneel, string> = { eigenschappen: 'Eigenschappen', lagen: 'Lagen', merk: 'Merkcontrole', exports: 'Exports' };

function VolledigeEditor(
    p: Gemeenschappelijk & {
        selectie: string | null;
        setSelectie: (id: string | null) => void;
        gekozen: Laag | null;
        uitsnedeModus: boolean;
        setUitsnedeModus: (a: boolean) => void;
        kaderBewerken: string | null;
        setKaderBewerken: (id: string | null) => void;
        voegLaagToe: (s: Soort, extra?: Partial<Laag>) => Laag;
        openKiezer: (s: BronSoort, doel: 'nieuw' | 'laag') => void;
        creatorVideos: CreatorVideo[];
        heeftVideo: boolean;
        speelt: boolean;
        setSpeelt: (s: boolean) => void;
        exporteer: { jpg: () => void; alle: () => void; video: () => void };
        voortgang: number | null;
        ffmpeg: boolean;
        maxVideoSeconden: number;
        merkkit: Merkkit;
        bevindingen: Bevinding[];
        onHerstel: (b: Bevinding) => void;
        onHerstelAlles: () => void;
    },
) {
    const { staat, pagina, paginaIndex, W, H, selectie, setSelectie, gekozen, wijzig, superAdmin, merkkit } = p;
    const [rootRef, breedte] = useBreedte<HTMLDivElement>();
    // Werkelijk beschikbare breedte (naast de zijbalk), niet de breedte van het venster.
    const compact = breedte < 1180;
    // Heel krap (tablet met zijbalk): ongedaan maken en opnieuw gaan in het menu Meer.
    const krap = breedte < 640;
    const zijpaneel = breedte >= 900;
    const [paneel, setPaneel] = useState<Paneel>('eigenschappen');
    const [lade, setLade] = useState(false);
    const [meer, setMeer] = useState(false);
    const [exportOpen, setExportOpen] = useState(false);
    const [hulplijnen, setHulplijnen] = useState(true);
    const [bovenVak, setBovenVak] = useState<Laag | null>(null);
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
    const ruimte = vrijeRuimte(merkkit, p.item.format, W, H);
    const vast = gekozen ? staatVast(gekozen, superAdmin) && p.kaderBewerken !== gekozen.id : false;
    const stijlTekst = gekozen?.soort === 'tekst' && !!gekozen.stijl && !gekozen.vrij;
    const videoKan = useMemo(() => opnameFormaat() !== null, []);

    const openPaneel = (pn: Paneel) => {
        setPaneel(pn);

        if (!zijpaneel) {
            setLade(true);
        }
    };

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

    // Transformer op de gekozen laag; niet op wat vast staat (merkslot) of vergrendeld is.
    useEffect(() => {
        const tr = trRef.current;
        const node = selectie ? stageRef.current?.findOne(`#${selectie}`) : null;

        if (!tr) {
            return;
        }

        const kader = gekozen && p.kaderBewerken === gekozen.id;
        tr.nodes(node && gekozen && (kader || (!gekozen.vergrendeld && !vast)) && !p.uitsnedeModus ? [node] : []);
        tr.getLayer()?.batchDraw();
    }, [selectie, gekozen, vast, p.uitsnedeModus, p.kaderBewerken, pagina, p.fontsKlaar]);

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
            } else if (gekozen && !vast && (e.key === 'Delete' || e.key === 'Backspace')) {
                e.preventDefault();
                staat.verwijder(paginaIndex, gekozen.id);
                setSelectie(null);
            } else if (gekozen && !gekozen.vergrendeld && !vast && e.key.startsWith('Arrow')) {
                e.preventDefault();
                const d = e.shiftKey ? 10 : 1;
                const dx = e.key === 'ArrowLeft' ? -d : e.key === 'ArrowRight' ? d : 0;
                const dy = e.key === 'ArrowUp' ? -d : e.key === 'ArrowDown' ? d : 0;
                wijzig(gekozen.id, { x: gekozen.x + dx, y: gekozen.y + dy }, 'pijl');
            } else if (e.key === 'Escape') {
                setSelectie(null);
                p.setUitsnedeModus(false);
                p.setKaderBewerken(null);
            }
        };
        window.addEventListener('keydown', toets);

        return () => window.removeEventListener('keydown', toets);
    }, [p, staat, gekozen, vast, paginaIndex, setSelectie, wijzig]);

    // Vastklikken: midden en randen van het canvas, de marges en veilige zones, en de andere lagen; anders het raster.
    const sleepStart = useCallback(
        (node: Konva.Node) => {
            const laag = inhoudRef.current;

            if (!laag) {
                return;
            }

            const v = [0, W / 2, W, ruimte.links, ruimte.rechts];
            const h = [0, H / 2, H, ruimte.marge, H - ruimte.marge, ruimte.boven, ruimte.onder];
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
        [W, H, ruimte.links, ruimte.rechts, ruimte.marge, ruimte.boven, ruimte.onder],
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
            const raster = merkkit.raster || 1;

            if (sv) {
                node.x(node.x() + sv.verschil);
                nieuw.push({ punten: [sv.lijn, -H, sv.lijn, H * 2] });
            } else {
                node.x(Math.round(node.x() / raster) * raster);
            }

            if (sh) {
                node.y(node.y() + sh.verschil);
                nieuw.push({ punten: [-W, sh.lijn, W * 2, sh.lijn] });
            } else {
                node.y(Math.round(node.y() / raster) * raster);
            }

            setLijnen(nieuw);
        },
        [z, W, H, merkkit.raster],
    );

    const tabs: Paneel[] = p.soort === 'ontwerp' ? ['eigenschappen', 'lagen', 'merk', 'exports'] : ['eigenschappen', 'lagen', 'merk'];

    const panelen = (
        <div className="min-w-0 space-y-4">
            <div role="tablist" className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1">
                {tabs.map((t) => (
                    <button
                        key={t}
                        type="button"
                        role="tab"
                        data-test={`studio-paneel-${t}`}
                        aria-selected={paneel === t}
                        onClick={() => setPaneel(t)}
                        className={cn('flex h-8 min-w-0 items-center justify-center gap-1.5 truncate rounded-md px-2 text-sm font-semibold', paneel === t ? 'bg-card text-violet shadow-sm' : 'text-muted-foreground hover:text-foreground')}
                    >
                        {t === 'merk' && <span className={cn('size-2 shrink-0 rounded-full', NIVEAU_KLEUR[p.niveau])} aria-hidden />}
                        <span className="truncate">{t === 'merk' ? 'Merkcontrole' : PANEEL_LABEL[t]}</span>
                        {t === 'lagen' && <span className="text-xs tabular-nums opacity-70">{pagina.lagen.length}</span>}
                    </button>
                ))}
            </div>
            {paneel === 'eigenschappen' && (
                <Eigenschappen
                    laag={gekozen}
                    pagina={pagina}
                    W={W}
                    H={H}
                    formaat={p.item.format}
                    superAdmin={superAdmin}
                    creatorVideos={p.creatorVideos}
                    uitsnedeModus={p.uitsnedeModus}
                    onUitsnedeModus={p.setUitsnedeModus}
                    kaderBewerken={!!gekozen && p.kaderBewerken === gekozen.id}
                    onKaderBewerken={(aan) => p.setKaderBewerken(aan && gekozen ? gekozen.id : null)}
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
                    superAdmin={superAdmin}
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
            {paneel === 'merk' && (
                <MerkcontrolePaneel
                    bevindingen={p.bevindingen}
                    onHerstel={p.onHerstel}
                    onHerstelAlles={p.onHerstelAlles}
                    onKies={(b) => {
                        p.setPaginaIndex(b.pagina);

                        if (b.laagId) {
                            setSelectie(b.laagId);
                        }
                    }}
                />
            )}
            {paneel === 'exports' && <ExportLijst exports={p.exports} />}
        </div>
    );

    const tekstTransformer = stijlTekst ? ['middle-left', 'middle-right'] : undefined;

    return (
        <div ref={rootRef} className="flex h-[calc(100dvh-5rem)] min-h-0 min-w-0 flex-col overflow-hidden" data-test="studio-editor" data-merkniveau={p.niveau}>
            {/* Werkbalk: titel kapt af, knoppen worden iconen als de ruimte krap is; Opslaan blijft altijd zichtbaar. */}
            <div className="flex min-w-0 items-center gap-1.5 border-b px-3 py-2" data-test="studio-werkbalk">
                <Link href={p.soort === 'ontwerp' ? ontwerpen.index.url() : sjablonen.index.url()} className="grid size-9 shrink-0 place-items-center rounded-lg hover:bg-muted" aria-label="Terug naar het overzicht">
                    <ArrowLeft className="size-4" />
                </Link>
                <input
                    aria-label="Naam"
                    title={p.naam}
                    value={p.naam}
                    onChange={(e) => {
                        p.setNaam(e.target.value);
                        staat.setGewijzigd(true);
                    }}
                    className="h-9 w-0 min-w-16 flex-1 truncate rounded-lg border border-transparent bg-transparent px-2 font-heading font-bold text-ellipsis hover:border-border focus:border-violet focus:outline-none"
                />
                {!krap && (
                    <>
                        <WerkKnop compact label="Ongedaan maken (Ctrl+Z)" icoon={<Undo2 className="size-4" />} onClick={staat.ongedaan} disabled={!staat.kanOngedaan} />
                        <WerkKnop compact label="Opnieuw (Ctrl+Shift+Z)" icoon={<Redo2 className="size-4" />} onClick={staat.opnieuw} disabled={!staat.kanOpnieuw} />
                    </>
                )}
                {!compact && <WerkKnop compact label="Uitzoomen" icoon={<Minus className="size-4" />} onClick={() => setZoom(Math.max(0.05, z / 1.25))} />}
                <button type="button" className="h-9 w-14 shrink-0 rounded-lg border bg-card text-sm font-semibold tabular-nums hover:border-violet" onClick={() => setZoom(null)} title="Passend in het scherm">
                    {Math.round(z * 100)}%
                </button>
                {!compact && <WerkKnop compact label="Inzoomen" icoon={<Plus className="size-4" />} onClick={() => setZoom(Math.min(3, z * 1.25))} />}
                {p.heeftVideo && <WerkKnop compact label={p.speelt ? 'Video pauzeren' : 'Video afspelen'} icoon={p.speelt ? <Pause className="size-4" /> : <Play className="size-4" />} onClick={() => p.setSpeelt(!p.speelt)} />}
                <WerkKnop
                    compact={compact}
                    label="Merkcontrole"
                    test="studio-merkknop"
                    icoon={
                        <span className="relative">
                            <ShieldCheck className="size-4" />
                            <span className={cn('absolute -top-1 -right-1 size-2 rounded-full ring-2 ring-card', NIVEAU_KLEUR[p.niveau])} aria-hidden />
                        </span>
                    }
                    onClick={() => openPaneel('merk')}
                />
                {p.soort === 'sjabloon' ? (
                    <WerkKnop compact={compact} label="Gebruiken" icoon={<LayoutTemplate className="size-4" />} onClick={async () => (await p.opslaan()) && router.post(sjablonen.gebruiken.url(p.item.id))} />
                ) : (
                    <div className="relative shrink-0">
                        <WerkKnop compact={compact} label="Exporteren" test="studio-exporteren" icoon={<Download className="size-4" />} onClick={() => setExportOpen(!exportOpen)} />
                        {exportOpen && (
                            <div className="absolute right-0 z-30 mt-1 w-72 space-y-1 rounded-xl border bg-popover p-2 shadow-lg" onClick={() => setExportOpen(false)}>
                                <MenuKnop test="studio-export-png" onClick={p.exporteerPng}>
                                    PNG{staat.paginas.length > 1 ? `, pagina ${paginaIndex + 1}` : ''} ({W} x {H})
                                </MenuKnop>
                                <MenuKnop onClick={p.exporteer.jpg}>JPG{staat.paginas.length > 1 ? `, pagina ${paginaIndex + 1}` : ''}</MenuKnop>
                                {staat.paginas.length > 1 && <MenuKnop onClick={p.exporteer.alle}>Alle pagina's als PNG plus zip</MenuKnop>}
                                {p.heeftVideo && (
                                    <MenuKnop test="studio-export-video" onClick={p.exporteer.video} disabled={!videoKan}>
                                        Video (opnemen, hooguit {p.maxVideoSeconden} s){!videoKan ? ': niet in deze browser' : p.ffmpeg ? ', daarna MP4' : ', als webm'}
                                    </MenuKnop>
                                )}
                                <p className="px-2 pt-1 text-xs text-muted-foreground">Exporteren slaat eerst op en doet de merkcontrole. Bestanden blijven privé in MeStudio.</p>
                            </div>
                        )}
                    </div>
                )}
                <div className="relative shrink-0">
                    <WerkKnop compact label="Meer" test="studio-meer" icoon={<MoreHorizontal className="size-4" />} onClick={() => setMeer(!meer)} />
                    {meer && (
                        <div className="absolute right-0 z-30 mt-1 w-64 space-y-1 rounded-xl border bg-popover p-2 shadow-lg" onClick={() => setMeer(false)}>
                            {krap && (
                                <>
                                    <MenuKnop onClick={staat.ongedaan} disabled={!staat.kanOngedaan}>
                                        Ongedaan maken
                                    </MenuKnop>
                                    <MenuKnop onClick={staat.opnieuw} disabled={!staat.kanOpnieuw}>
                                        Opnieuw
                                    </MenuKnop>
                                </>
                            )}
                            {compact && <MenuKnop onClick={() => setZoom(Math.min(3, z * 1.25))}>Inzoomen</MenuKnop>}
                            {compact && <MenuKnop onClick={() => setZoom(Math.max(0.05, z / 1.25))}>Uitzoomen</MenuKnop>}
                            <MenuKnop onClick={() => setHulplijnen(!hulplijnen)}>{hulplijnen ? 'Marges en veilige zones verbergen' : 'Marges en veilige zones tonen'}</MenuKnop>
                            {!zijpaneel && (
                                <>
                                    <MenuKnop onClick={() => openPaneel('eigenschappen')}>Eigenschappen</MenuKnop>
                                    <MenuKnop onClick={() => openPaneel('lagen')}>Lagen</MenuKnop>
                                    {p.soort === 'ontwerp' && <MenuKnop onClick={() => openPaneel('exports')}>Exports</MenuKnop>}
                                </>
                            )}
                            {p.soort === 'ontwerp' && (
                                <MenuKnop onClick={async () => (await p.opslaan()) && router.post(ontwerpen.sjabloon.url(p.item.id), { name: p.naam })}>Opslaan als sjabloon</MenuKnop>
                            )}
                        </div>
                    )}
                </div>
                {!zijpaneel && <WerkKnop compact label="Eigenschappen en lagen" test="studio-open-eigenschappen" icoon={<Grid3x3 className="size-4" />} onClick={() => openPaneel('eigenschappen')} />}
                <button
                    type="button"
                    data-test="studio-opslaan"
                    onClick={() => void p.opslaan()}
                    disabled={!!p.bezig}
                    className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg bg-violet px-3 text-sm font-semibold text-white hover:bg-violet/90 disabled:opacity-60"
                >
                    <Save className="size-4" /> {p.bezig === 'opslaan' ? 'Opslaan...' : staat.gewijzigd ? 'Opslaan' : 'Opgeslagen'}
                </button>
            </div>

            <div className="flex min-h-0 min-w-0 flex-1">
                {/* Toevoegen */}
                <div className="w-[72px] shrink-0 space-y-0.5 overflow-y-auto border-r p-1.5" aria-label="Toevoegen">
                    <Gereedschap label="Tekst" test="studio-tekst-erbij" onClick={() => p.voegLaagToe('tekst', { kleur: isDonker(pagina.achtergrond) ? '#FFFFFF' : '#3A3659' })}>
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
                <div className="relative flex min-w-0 flex-1 flex-col">
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
                                            p.setKaderBewerken(null);
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
                                            kaderBewerken={p.kaderBewerken}
                                            speelt={p.speelt}
                                            onKies={(id) => {
                                                if (id !== selectie) {
                                                    p.setUitsnedeModus(false);
                                                    p.setKaderBewerken(null);
                                                }

                                                setSelectie(id);
                                            }}
                                            onWijzig={(id, patch, sleutel) => wijzig(id, patch, sleutel)}
                                            onSleepStart={sleepStart}
                                            onSleep={sleep}
                                            onSleepEind={() => setLijnen([])}
                                            onBovenVak={setBovenVak}
                                            onDubbel={(l) => {
                                                if (l.soort === 'tekst') {
                                                    openPaneel('eigenschappen');
                                                    setTimeout(() => tekstRef.current?.focus(), 50);
                                                } else if (isVastVak(l)) {
                                                    // Dubbelklik op een vak vervangt alleen de inhoud.
                                                    setSelectie(l.id);
                                                    p.openKiezer(l.soort as BronSoort, 'laag');
                                                } else if ((l.soort === 'foto' || l.soort === 'video') && l.bron) {
                                                    p.setUitsnedeModus(true);
                                                }
                                            }}
                                        />
                                    </Layer>
                                    <Layer listening={false}>
                                        {hulplijnen && (
                                            <Group>
                                                {ruimte.veiligBoven > 0 && <Rect width={W} height={ruimte.veiligBoven} fill="#D9303E" opacity={0.08} />}
                                                {ruimte.veiligOnder > 0 && <Rect y={H - ruimte.veiligOnder} width={W} height={ruimte.veiligOnder} fill="#D9303E" opacity={0.08} />}
                                                {ruimte.veiligBoven > 0 && <Text x={ruimte.links} y={ruimte.veiligBoven - 34 / Math.max(z, 0.3)} text="Veilige zone Instagram" fontSize={14 / z} fill="#D9303E" fontStyle="700" opacity={0.8} />}
                                                {ruimte.veiligOnder > 0 && <Text x={ruimte.links} y={H - ruimte.veiligOnder + 8 / z} text="Veilige zone Instagram" fontSize={14 / z} fill="#D9303E" fontStyle="700" opacity={0.8} />}
                                                <Rect
                                                    x={ruimte.links}
                                                    y={ruimte.marge}
                                                    width={ruimte.rechts - ruimte.links}
                                                    height={H - 2 * ruimte.marge}
                                                    stroke="#614ED9"
                                                    strokeWidth={1 / z}
                                                    dash={[6 / z, 6 / z]}
                                                    opacity={0.55}
                                                />
                                            </Group>
                                        )}
                                        {lijnen.map((l, i) => (
                                            <Line key={i} points={l.punten} stroke="#D9303E" strokeWidth={1 / z} dash={[6 / z, 4 / z]} />
                                        ))}
                                    </Layer>
                                    <Layer>
                                        <Transformer
                                            ref={trRef}
                                            rotateEnabled={!stijlTekst || superAdmin}
                                            keepRatio={gekozen?.soort === 'logo'}
                                            enabledAnchors={
                                                tekstTransformer ??
                                                (gekozen?.soort === 'logo'
                                                    ? ['top-left', 'top-right', 'bottom-left', 'bottom-right']
                                                    : ['top-left', 'top-center', 'top-right', 'middle-right', 'middle-left', 'bottom-left', 'bottom-center', 'bottom-right'])
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
                    {bovenVak && !p.uitsnedeModus && (
                        <p className="pointer-events-none absolute inset-x-0 bottom-3 mx-auto w-fit rounded-full bg-aubergine-dark/90 px-3 py-1.5 text-sm text-white shadow" role="status">
                            Sleep om de {bovenVak.soort === 'video' ? 'video' : 'foto'} te verschuiven, scroll om te zoomen
                        </p>
                    )}
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
                    {p.uitsnedeModus && <p className="border-t bg-violet/10 px-3 py-1.5 text-sm text-violet">Sleep de foto of video binnen het kader. Esc of een klik ernaast is klaar.</p>}
                    {p.kaderBewerken && <p className="border-t bg-violet/10 px-3 py-1.5 text-sm text-violet">Vak bewerken (Super Admin): je past het kader zelf aan. Esc is klaar.</p>}
                    {p.voortgang !== null && (
                        <div className="border-t px-3 py-2" role="status">
                            <p className="text-sm font-semibold">Video opnemen... {Math.round(p.voortgang * 100)}% (laat dit tabblad open)</p>
                            <div className="mt-1 h-2 overflow-hidden rounded-full bg-muted">
                                <div className="h-full bg-brand-gradient" style={{ width: `${p.voortgang * 100}%` }} />
                            </div>
                        </div>
                    )}
                </div>

                {zijpaneel && (
                    <aside className="w-80 shrink-0 overflow-x-hidden overflow-y-auto border-l p-4" data-test="studio-zijpaneel">
                        {panelen}
                    </aside>
                )}
            </div>

            {!zijpaneel && (
                <Sheet open={lade} onOpenChange={setLade}>
                    <SheetContent side="right" className="w-[340px] overflow-x-hidden overflow-y-auto p-4 sm:max-w-sm">
                        <SheetHeader className="p-0">
                            <SheetTitle>{PANEEL_LABEL[paneel]}</SheetTitle>
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
