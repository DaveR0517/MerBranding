/**
 * Panelen van de editor: eigenschappen van de gekozen laag (tekststijlen uit de merkkit, vaste vakken),
 * het lagenpaneel (volgorde, verbergen, vergrendelen) en de merkcontrole.
 */
import {
    AlignCenter,
    AlignCenterHorizontal,
    AlignCenterVertical,
    AlignLeft,
    AlignRight,
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    Copy,
    Eye,
    EyeOff,
    Image as ImageIcon,
    Lock,
    LockOpen,
    Move,
    Trash2,
    Wrench,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { RechtenWaarschuwing } from './bronkiezer';
import { isVastVak } from './canvas';
import { useMerk } from './merk';
import type { Bevinding, Niveau, Regel } from './merkcontrole';
import { REGELS } from './merkcontrole';
import type { CreatorVideo, Laag, Pagina, StijlSleutel } from './typen';
import { STIJLEN, stijlWaarden } from './typen';

const invoer = 'h-10 w-full min-w-0 rounded-lg border bg-background px-2.5 text-sm focus:border-violet focus:outline-none';

/** Staat deze laag vast voor deze gebruiker? Merkelementen en vaste vakken: alleen Super Admin past ze aan. */
export function staatVast(l: Laag, superAdmin: boolean): boolean {
    return !superAdmin && (!!l.merk || isVastVak(l));
}

export function Veld({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
    return (
        <label className={cn('block min-w-0 space-y-1', className)}>
            <span className="text-xs font-semibold text-muted-foreground">{label}</span>
            {children}
        </label>
    );
}

function Getal({ label, waarde, onZet, min, max, stap = 1 }: { label: string; waarde: number; onZet: (n: number) => void; min?: number; max?: number; stap?: number }) {
    return (
        <Veld label={label}>
            <input
                type="number"
                className={invoer}
                value={Math.round(waarde * 100) / 100}
                min={min}
                max={max}
                step={stap}
                onChange={(e) => e.target.value !== '' && onZet(Number(e.target.value))}
            />
        </Veld>
    );
}

export function PaletKiezer({ label, waarde, onZet }: { label: string; waarde?: string; onZet: (k: string) => void }) {
    const { palet } = useMerk();

    return (
        <div className="space-y-1">
            <span className="text-xs font-semibold text-muted-foreground">{label}</span>
            <div className="flex flex-wrap gap-1.5" role="radiogroup" aria-label={label}>
                {palet.map((p) => (
                    <button
                        key={p.kleur}
                        type="button"
                        role="radio"
                        aria-checked={waarde?.toUpperCase() === p.kleur.toUpperCase()}
                        title={`${p.naam} ${p.kleur}`}
                        aria-label={p.naam}
                        onClick={() => onZet(p.kleur)}
                        className={cn('size-8 rounded-full border-2 shadow-sm', waarde?.toUpperCase() === p.kleur.toUpperCase() ? 'border-violet ring-2 ring-violet/40' : 'border-black/10')}
                        style={{ background: p.kleur }}
                    />
                ))}
            </div>
        </div>
    );
}

function Knop({ label, onClick, actief, children }: { label: string; onClick: () => void; actief?: boolean; children: ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            title={label}
            aria-pressed={actief}
            className={cn('grid size-10 place-items-center rounded-lg border hover:border-violet', actief && 'border-violet bg-violet/10 text-violet')}
        >
            {children}
        </button>
    );
}

function Vast({ children }: { children: ReactNode }) {
    return (
        <p className="flex gap-2 rounded-lg bg-violet/10 p-2.5 text-sm text-violet">
            <Lock className="mt-0.5 size-4 shrink-0" aria-hidden />
            <span>{children}</span>
        </p>
    );
}

export function Eigenschappen({
    laag,
    pagina,
    W,
    H,
    formaat,
    superAdmin,
    creatorVideos,
    uitsnedeModus,
    onUitsnedeModus,
    kaderBewerken,
    onKaderBewerken,
    onWijzig,
    onPagina,
    onKiesBron,
    tekstRef,
}: {
    laag: Laag | null;
    pagina: Pagina;
    W: number;
    H: number;
    formaat: string;
    superAdmin: boolean;
    creatorVideos: CreatorVideo[];
    uitsnedeModus: boolean;
    onUitsnedeModus: (aan: boolean) => void;
    kaderBewerken: boolean;
    onKaderBewerken: (aan: boolean) => void;
    onWijzig: (patch: Partial<Laag>, sleutel?: string) => void;
    onPagina: (patch: Partial<Pagina>) => void;
    onKiesBron: (soort: 'foto' | 'video' | 'logo') => void;
    tekstRef?: React.RefObject<HTMLTextAreaElement | null>;
}) {
    const merk = useMerk();

    if (!laag) {
        return (
            <div className="space-y-4">
                <p className="text-sm text-muted-foreground">Kies een laag op het canvas of in het lagenpaneel. Zonder keuze pas je de pagina aan.</p>
                <PaletKiezer label="Achtergrond van de pagina" waarde={pagina.achtergrond} onZet={(k) => onPagina({ achtergrond: k })} />
                <p className="text-xs text-muted-foreground">
                    Formaat {W} bij {H} pixels, marge {merk.formaten[formaat]?.marge ?? 60} px.
                </p>
            </div>
        );
    }

    const u = laag.uitsnede ?? { x: 0.5, y: 0.5, zoom: 1 };
    const creatorVideo = laag.bron?.soort === 'creator' ? creatorVideos.find((v) => v.id === (laag.bron as { id: number }).id) : undefined;
    const vast = staatVast(laag, superAdmin);
    const vastVak = isVastVak(laag);
    const kiesStijl = (stijl: StijlSleutel) => onWijzig({ stijl, vrij: false, ...stijlWaarden(merk, stijl, formaat) });

    return (
        <div className="min-w-0 space-y-4" data-test="studio-eigenschappen">
            <Veld label="Naam van de laag">
                <input className={invoer} value={laag.naam} onChange={(e) => onWijzig({ naam: e.target.value }, 'naam')} />
            </Veld>

            {laag.soort === 'tekst' && (
                <>
                    <Veld label="Tekst">
                        <textarea
                            ref={tekstRef}
                            data-test="studio-tekst"
                            className={cn(invoer, 'h-24 py-2')}
                            value={laag.tekst}
                            onChange={(e) => onWijzig({ tekst: e.target.value }, 'tekst')}
                        />
                    </Veld>
                    <div className="space-y-1">
                        <span className="text-xs font-semibold text-muted-foreground">Tekststijl</span>
                        <div className="grid grid-cols-3 gap-1" role="radiogroup" aria-label="Tekststijl">
                            {STIJLEN.map((s) => (
                                <button
                                    key={s}
                                    type="button"
                                    role="radio"
                                    data-test={`studio-stijl-${s}`}
                                    aria-checked={laag.stijl === s && !laag.vrij}
                                    onClick={() => kiesStijl(s)}
                                    className={cn(
                                        'h-10 truncate rounded-lg border px-1 text-sm font-semibold',
                                        laag.stijl === s && !laag.vrij ? 'border-violet bg-violet text-white' : 'hover:border-violet',
                                    )}
                                >
                                    {merk.stijlen[s].naam}
                                </button>
                            ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {laag.vrij || !laag.stijl
                                ? 'Geen merkstijl: kies er een.'
                                : `${laag.lettertype?.replace(' Variable', '')}, ${laag.grootte} px, gewicht ${laag.gewicht}${laag.hoofdletters ? ', hoofdletters' : ''}`}
                        </p>
                    </div>
                    <div className="space-y-1">
                        <span className="text-xs font-semibold text-muted-foreground">Uitlijning</span>
                        <div className="flex gap-1">
                            <Knop label="Links" actief={laag.uitlijning === 'left'} onClick={() => onWijzig({ uitlijning: 'left' })}>
                                <AlignLeft className="size-4" />
                            </Knop>
                            <Knop label="Midden" actief={laag.uitlijning === 'center'} onClick={() => onWijzig({ uitlijning: 'center' })}>
                                <AlignCenter className="size-4" />
                            </Knop>
                            <Knop label="Rechts" actief={laag.uitlijning === 'right'} onClick={() => onWijzig({ uitlijning: 'right' })}>
                                <AlignRight className="size-4" />
                            </Knop>
                        </div>
                    </div>
                    <PaletKiezer label="Kleur" waarde={laag.kleur} onZet={(k) => onWijzig({ kleur: k })} />
                    {superAdmin && (
                        <details className="rounded-lg border p-2.5" open={!!laag.vrij}>
                            <summary className="cursor-pointer text-sm font-semibold">Geavanceerd (Super Admin)</summary>
                            <div className="mt-2 space-y-2">
                                <label className="flex items-center gap-2 text-sm">
                                    <input type="checkbox" className="size-4 accent-violet" checked={!!laag.vrij} onChange={(e) => onWijzig({ vrij: e.target.checked })} />
                                    Vrije grootte en gewicht (wijkt af van de merkstijl)
                                </label>
                                {laag.vrij && (
                                    <div className="grid grid-cols-2 gap-2">
                                        <Veld label="Lettertype" className="col-span-2">
                                            <select className={invoer} value={laag.lettertype} onChange={(e) => onWijzig({ lettertype: e.target.value })}>
                                                {merk.lettertypes.map((l) => (
                                                    <option key={l} value={l}>
                                                        {l.replace(' Variable', '')}
                                                    </option>
                                                ))}
                                            </select>
                                        </Veld>
                                        <Getal label="Grootte" waarde={laag.grootte ?? 48} min={8} max={600} onZet={(n) => onWijzig({ grootte: n }, 'grootte')} />
                                        <Veld label="Gewicht">
                                            <select className={invoer} value={laag.gewicht} onChange={(e) => onWijzig({ gewicht: Number(e.target.value) })}>
                                                {[400, 500, 600, 700, 800, 900].map((g) => (
                                                    <option key={g} value={g}>
                                                        {g}
                                                    </option>
                                                ))}
                                            </select>
                                        </Veld>
                                        <Getal label="Regelafstand" waarde={laag.regelafstand ?? 1.2} min={0.8} max={3} stap={0.05} onZet={(n) => onWijzig({ regelafstand: n }, 'regel')} />
                                    </div>
                                )}
                            </div>
                        </details>
                    )}
                </>
            )}

            {(laag.soort === 'foto' || laag.soort === 'video') && (
                <div className="space-y-3">
                    {vastVak && !kaderBewerken && <Vast>Het kader van dit vak staat vast. Sleep in het vak om de {laag.soort === 'video' ? 'video' : 'foto'} te verschuiven; zoom met de schuif of het muiswiel.</Vast>}
                    <div className="flex flex-wrap gap-2">
                        <button type="button" data-test="studio-kies-bron" onClick={() => onKiesBron(laag.soort as 'foto' | 'video')} className="inline-flex h-10 items-center gap-2 rounded-lg bg-violet px-3 text-sm font-semibold text-white">
                            <ImageIcon className="size-4" aria-hidden />
                            {laag.bron ? (laag.soort === 'video' ? 'Andere video' : 'Andere foto') : laag.soort === 'video' ? 'Video plaatsen' : 'Foto plaatsen'}
                        </button>
                        {laag.bron && (
                            <button type="button" onClick={() => onWijzig({ bron: null })} className="h-10 rounded-lg border px-3 text-sm font-semibold hover:border-violet">
                                Vak leegmaken
                            </button>
                        )}
                    </div>
                    {laag.bron && (
                        <>
                            <Veld label={`Zoom in het vak (${Math.round(u.zoom * 100)}%)`}>
                                <input type="range" min={1} max={4} step={0.01} value={u.zoom} onChange={(e) => onWijzig({ uitsnede: { ...u, zoom: Number(e.target.value) } }, 'zoom')} className="w-full accent-violet" />
                            </Veld>
                            <div className="flex flex-wrap gap-2">
                                {!vastVak && (
                                    <button
                                        type="button"
                                        aria-pressed={uitsnedeModus}
                                        onClick={() => onUitsnedeModus(!uitsnedeModus)}
                                        className={cn('inline-flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-semibold', uitsnedeModus ? 'border-violet bg-violet/10 text-violet' : 'hover:border-violet')}
                                    >
                                        <Move className="size-4" aria-hidden />
                                        {uitsnedeModus ? 'Klaar met verschuiven' : 'Verschuiven in het kader'}
                                    </button>
                                )}
                                <button type="button" onClick={() => onWijzig({ uitsnede: { x: 0.5, y: 0.5, zoom: 1 } })} className="h-10 rounded-lg border px-3 text-sm font-semibold hover:border-violet">
                                    Uitsnede in het midden
                                </button>
                            </div>
                        </>
                    )}
                    {creatorVideo && <RechtenWaarschuwing video={creatorVideo} />}
                    {vastVak && superAdmin && (
                        <button
                            type="button"
                            aria-pressed={kaderBewerken}
                            onClick={() => onKaderBewerken(!kaderBewerken)}
                            className={cn('inline-flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-semibold', kaderBewerken ? 'border-violet bg-violet/10 text-violet' : 'hover:border-violet')}
                        >
                            <Wrench className="size-4" aria-hidden />
                            {kaderBewerken ? 'Klaar met vak bewerken' : 'Vak bewerken (Super Admin)'}
                        </button>
                    )}
                    {(!vastVak || kaderBewerken) && (
                        <Getal label="Hoekafronding" waarde={laag.hoekafronding ?? 0} min={0} max={Math.min(laag.breedte, laag.hoogte) / 2} onZet={(n) => onWijzig({ hoekafronding: n }, 'hoek')} />
                    )}
                </div>
            )}

            {laag.soort === 'vorm' && (
                <>
                    <Veld label="Vorm">
                        <select className={invoer} value={laag.vorm} onChange={(e) => onWijzig({ vorm: e.target.value as 'rechthoek' | 'cirkel' })}>
                            <option value="rechthoek">Rechthoek</option>
                            <option value="cirkel">Cirkel</option>
                        </select>
                    </Veld>
                    <PaletKiezer label="Vulling" waarde={laag.vulling} onZet={(k) => onWijzig({ vulling: k })} />
                    {laag.vorm !== 'cirkel' && <Getal label="Hoekafronding" waarde={laag.hoekafronding ?? 0} min={0} max={Math.min(laag.breedte, laag.hoogte) / 2} onZet={(n) => onWijzig({ hoekafronding: n }, 'hoek')} />}
                </>
            )}

            {laag.soort === 'verloop' && !vast && (
                <>
                    <p className="text-xs text-muted-foreground">
                        Merkverloop, onder {merk.verloop.hoek} graden.
                    </p>
                    <Getal label="Hoekafronding" waarde={laag.hoekafronding ?? 0} min={0} max={Math.min(laag.breedte, laag.hoogte) / 2} onZet={(n) => onWijzig({ hoekafronding: n }, 'hoek')} />
                </>
            )}

            {laag.soort === 'logo' && !vast && (
                <button type="button" onClick={() => onKiesBron('logo')} className="h-10 rounded-lg border px-3 text-sm font-semibold hover:border-violet">
                    Ander logo
                </button>
            )}

            {vast && !vastVak && <Vast>Dit merkelement staat vast (merkslot). Alleen Super Admin kan het verplaatsen of weghalen.</Vast>}

            {((!vast && !vastVak) || kaderBewerken) && (
                <div className="space-y-2 border-t pt-3">
                    <span className="text-xs font-semibold text-muted-foreground">Plaats en maat</span>
                    <div className="grid grid-cols-2 gap-2">
                        <Getal label="X" waarde={laag.x} onZet={(n) => onWijzig({ x: n }, 'x')} />
                        <Getal label="Y" waarde={laag.y} onZet={(n) => onWijzig({ y: n }, 'y')} />
                        <Getal label="Breedte" waarde={laag.breedte} min={10} onZet={(n) => onWijzig({ breedte: n }, 'b')} />
                        {laag.soort !== 'tekst' && <Getal label="Hoogte" waarde={laag.hoogte} min={10} onZet={(n) => onWijzig({ hoogte: n }, 'h')} />}
                        <Getal label="Draaien (graden)" waarde={laag.rotatie} min={-360} max={360} onZet={(n) => onWijzig({ rotatie: n }, 'r')} />
                        <Veld label={`Dekking (${Math.round((laag.dekking ?? 1) * 100)}%)`}>
                            <input type="range" min={0.05} max={1} step={0.05} value={laag.dekking ?? 1} onChange={(e) => onWijzig({ dekking: Number(e.target.value) }, 'dekking')} className="h-10 w-full accent-violet" />
                        </Veld>
                    </div>
                    <div className="flex gap-1">
                        <Knop label="Horizontaal in het midden" onClick={() => onWijzig({ x: Math.round((W - laag.breedte) / 2) })}>
                            <AlignCenterVertical className="size-4" />
                        </Knop>
                        <Knop label="Verticaal in het midden" onClick={() => onWijzig({ y: Math.round((H - laag.hoogte) / 2) })}>
                            <AlignCenterHorizontal className="size-4" />
                        </Knop>
                    </div>
                </div>
            )}

            {(!vast || superAdmin) && (
                <div className="space-y-2 border-t pt-3">
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" className="size-4 accent-violet" checked={!!laag.vak} onChange={(e) => onWijzig({ vak: e.target.checked, vakNaam: laag.vakNaam ?? laag.naam })} />
                        Dit is een vak (in een sjabloon vul je dit in)
                    </label>
                    {laag.vak && (
                        <Veld label="Naam van het vak">
                            <input className={invoer} value={laag.vakNaam ?? ''} onChange={(e) => onWijzig({ vakNaam: e.target.value }, 'vaknaam')} />
                        </Veld>
                    )}
                    {laag.vak && (laag.soort === 'foto' || laag.soort === 'video') && superAdmin && (
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" className="size-4 accent-violet" checked={laag.vergrendeld} onChange={(e) => onWijzig({ vergrendeld: e.target.checked })} />
                            Kader vastzetten (alleen de inhoud verschuift)
                        </label>
                    )}
                    {superAdmin && (
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" className="size-4 accent-violet" checked={!!laag.merk} onChange={(e) => onWijzig({ merk: e.target.checked, vergrendeld: e.target.checked || laag.vergrendeld })} />
                            Merkelement (vast voor het team)
                        </label>
                    )}
                </div>
            )}
        </div>
    );
}

const SOORT_LABEL: Record<string, string> = { tekst: 'Tekst', foto: 'Foto', video: 'Video', vorm: 'Vorm', logo: 'Logo', verloop: 'Verloop' };

export function LagenPaneel({
    pagina,
    selectie,
    superAdmin,
    onKies,
    onWijzig,
    onVerschuif,
    onDupliceer,
    onVerwijder,
}: {
    pagina: Pagina;
    selectie: string | null;
    superAdmin: boolean;
    onKies: (id: string) => void;
    onWijzig: (id: string, patch: Partial<Laag>) => void;
    onVerschuif: (id: string, richting: 1 | -1) => void;
    onDupliceer: (id: string) => void;
    onVerwijder: (id: string) => void;
}) {
    const lagen = [...pagina.lagen].reverse();

    if (lagen.length === 0) {
        return <p className="text-sm text-muted-foreground">Nog geen lagen. Voeg links tekst, een foto of een vorm toe.</p>;
    }

    return (
        <ul className="space-y-1.5" aria-label="Lagen, bovenste eerst">
            {lagen.map((l, i) => {
                const vast = staatVast(l, superAdmin);

                return (
                    <li key={l.id} className={cn('flex min-w-0 items-center gap-0.5 rounded-lg border bg-card p-1 pl-2', selectie === l.id && 'border-violet bg-violet/5')}>
                        <button type="button" onClick={() => onKies(l.id)} className="min-w-0 flex-1 py-1.5 text-left text-sm">
                            <span className={cn('block truncate font-semibold', !l.zichtbaar && 'text-muted-foreground line-through')}>{l.naam}</span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {SOORT_LABEL[l.soort]}
                                {l.merk ? ', merk' : l.vak ? ', vak' : ''}
                            </span>
                        </button>
                        {!vast && (
                            <IconKnop label={l.zichtbaar ? 'Verbergen' : 'Tonen'} onClick={() => onWijzig(l.id, { zichtbaar: !l.zichtbaar })}>
                                {l.zichtbaar ? <Eye className="size-4" /> : <EyeOff className="size-4" />}
                            </IconKnop>
                        )}
                        <IconKnop label={vast ? 'Staat vast (merkslot)' : l.vergrendeld ? 'Ontgrendelen' : 'Vergrendelen'} disabled={vast} onClick={() => onWijzig(l.id, { vergrendeld: !l.vergrendeld })}>
                            {l.vergrendeld ? <Lock className="size-4 text-violet" /> : <LockOpen className="size-4" />}
                        </IconKnop>
                        <IconKnop label="Naar voren" disabled={i === 0} onClick={() => onVerschuif(l.id, 1)}>
                            <ArrowUp className="size-4" />
                        </IconKnop>
                        <IconKnop label="Naar achteren" disabled={i === lagen.length - 1} onClick={() => onVerschuif(l.id, -1)}>
                            <ArrowDown className="size-4" />
                        </IconKnop>
                        {!vast && (
                            <>
                                <IconKnop label="Dupliceren" onClick={() => onDupliceer(l.id)}>
                                    <Copy className="size-4" />
                                </IconKnop>
                                <IconKnop label="Verwijderen" onClick={() => onVerwijder(l.id)}>
                                    <Trash2 className="size-4" />
                                </IconKnop>
                            </>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

function IconKnop({ label, onClick, disabled, children }: { label: string; onClick: () => void; disabled?: boolean; children: ReactNode }) {
    return (
        <button type="button" aria-label={label} title={label} disabled={disabled} onClick={onClick} className="grid size-8 shrink-0 place-items-center rounded-md hover:bg-muted disabled:opacity-40">
            {children}
        </button>
    );
}

export const NIVEAU_KLEUR: Record<Niveau, string> = { groen: 'bg-emerald-500', oranje: 'bg-amber-500', rood: 'bg-rood' };

/** Paneel Merkcontrole: per regel groen, oranje of rood, met herstel waar dat automatisch kan. */
export function MerkcontrolePaneel({
    bevindingen,
    onHerstel,
    onHerstelAlles,
    onKies,
}: {
    bevindingen: Bevinding[];
    onHerstel: (b: Bevinding) => void;
    onHerstelAlles: () => void;
    onKies: (b: Bevinding) => void;
}) {
    const perRegel = (Object.keys(REGELS) as Regel[]).map((r) => {
        const lijst = bevindingen.filter((b) => b.regel === r);
        const niveau: Niveau = lijst.some((b) => b.niveau === 'rood') ? 'rood' : lijst.length ? 'oranje' : 'groen';

        return { regel: r, lijst, niveau };
    });
    const herstelbaar = bevindingen.some((b) => b.herstel && b.niveau === 'rood');

    return (
        <div className="space-y-2" data-test="studio-merkcontrole">
            {herstelbaar && (
                <button type="button" onClick={onHerstelAlles} data-test="studio-herstel-alles" className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-violet text-sm font-semibold text-white">
                    <Wrench className="size-4" aria-hidden /> Alles herstellen wat kan
                </button>
            )}
            <ul className="space-y-1.5">
                {perRegel.map(({ regel, lijst, niveau }) => (
                    <li key={regel} className="rounded-lg border bg-card p-2.5" data-niveau={niveau}>
                        <p className="flex items-center gap-2 text-sm font-semibold">
                            <span className={cn('size-2.5 shrink-0 rounded-full', NIVEAU_KLEUR[niveau])} aria-hidden />
                            <span className="min-w-0 flex-1">{REGELS[regel]}</span>
                            {niveau === 'groen' && <CheckCircle2 className="size-4 text-emerald-600" aria-label="In orde" />}
                        </p>
                        {lijst.length > 0 && (
                            <ul className="mt-1.5 space-y-1.5 pl-4">
                                {lijst.map((b, i) => (
                                    <li key={i} className="text-sm">
                                        <button type="button" onClick={() => onKies(b)} className="text-left text-muted-foreground hover:text-foreground">
                                            <span className={cn('font-semibold', b.niveau === 'rood' ? 'text-rood' : 'text-amber-700')}>{b.niveau === 'rood' ? 'Rood' : 'Oranje'}:</span> {b.tekst}
                                        </button>
                                        {b.herstel && (
                                            <button type="button" onClick={() => onHerstel(b)} className="ml-1 font-semibold text-violet hover:underline">
                                                Herstel
                                            </button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </li>
                ))}
            </ul>
            <p className="text-xs text-muted-foreground">Rood houdt de export tegen. Oranje is een waarschuwing.</p>
        </div>
    );
}
