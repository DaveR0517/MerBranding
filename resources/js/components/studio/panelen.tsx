/** Panelen van de editor: eigenschappen van de gekozen laag en het lagenpaneel (volgorde, verbergen, vergrendelen). */
import {
    AlignCenter,
    AlignCenterHorizontal,
    AlignCenterVertical,
    AlignLeft,
    AlignRight,
    ArrowDown,
    ArrowUp,
    Copy,
    Eye,
    EyeOff,
    Image as ImageIcon,
    Lock,
    LockOpen,
    Move,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { RechtenWaarschuwing } from './bronkiezer';
import type { CreatorVideo, Laag, Pagina } from './typen';
import { LETTERTYPES, PALET } from './typen';

const invoer = 'h-10 w-full rounded-lg border bg-background px-2.5 text-sm focus:border-violet focus:outline-none';

export function Veld({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
    return (
        <label className={cn('block space-y-1', className)}>
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
    return (
        <div className="space-y-1">
            <span className="text-xs font-semibold text-muted-foreground">{label}</span>
            <div className="flex flex-wrap gap-1.5" role="radiogroup" aria-label={label}>
                {PALET.map((p) => (
                    <button
                        key={p.kleur}
                        type="button"
                        role="radio"
                        aria-checked={waarde?.toUpperCase() === p.kleur}
                        title={`${p.naam} ${p.kleur}`}
                        aria-label={p.naam}
                        onClick={() => onZet(p.kleur)}
                        className={cn('size-8 rounded-full border-2 shadow-sm', waarde?.toUpperCase() === p.kleur ? 'border-violet ring-2 ring-violet/40' : 'border-black/10')}
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

export function Eigenschappen({
    laag,
    pagina,
    W,
    H,
    creatorVideos,
    uitsnedeModus,
    onUitsnedeModus,
    onWijzig,
    onPagina,
    onKiesBron,
    tekstRef,
}: {
    laag: Laag | null;
    pagina: Pagina;
    W: number;
    H: number;
    creatorVideos: CreatorVideo[];
    uitsnedeModus: boolean;
    onUitsnedeModus: (aan: boolean) => void;
    onWijzig: (patch: Partial<Laag>, sleutel?: string) => void;
    onPagina: (patch: Partial<Pagina>) => void;
    onKiesBron: (soort: 'foto' | 'video' | 'logo') => void;
    tekstRef?: React.RefObject<HTMLTextAreaElement | null>;
}) {
    if (!laag) {
        return (
            <div className="space-y-4">
                <p className="text-sm text-muted-foreground">Kies een laag op het canvas of in het lagenpaneel. Zonder keuze pas je de pagina aan.</p>
                <PaletKiezer label="Achtergrond van de pagina" waarde={pagina.achtergrond} onZet={(k) => onPagina({ achtergrond: k })} />
                <p className="text-xs text-muted-foreground">
                    Formaat {W} bij {H} pixels.
                </p>
            </div>
        );
    }

    const u = laag.uitsnede ?? { x: 0.5, y: 0.5, zoom: 1 };
    const creatorVideo = laag.bron?.soort === 'creator' ? creatorVideos.find((v) => v.id === (laag.bron as { id: number }).id) : undefined;

    return (
        <div className="space-y-4" data-test="studio-eigenschappen">
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
                    <div className="grid grid-cols-2 gap-2">
                        <Veld label="Lettertype" className="col-span-2">
                            <select className={invoer} value={laag.lettertype} onChange={(e) => onWijzig({ lettertype: e.target.value })}>
                                {LETTERTYPES.map((l) => (
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
                                        {{ 400: 'Normaal', 500: 'Medium', 600: 'Halfvet', 700: 'Vet', 800: 'Extra vet', 900: 'Zwart' }[g]}
                                    </option>
                                ))}
                            </select>
                        </Veld>
                        <Getal label="Regelafstand" waarde={laag.regelafstand ?? 1.2} min={0.8} max={3} stap={0.05} onZet={(n) => onWijzig({ regelafstand: n }, 'regel')} />
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
                    </div>
                    <PaletKiezer label="Kleur" waarde={laag.kleur} onZet={(k) => onWijzig({ kleur: k })} />
                </>
            )}

            {(laag.soort === 'foto' || laag.soort === 'video') && (
                <div className="space-y-3">
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
                                <input type="range" min={1} max={3} step={0.01} value={u.zoom} onChange={(e) => onWijzig({ uitsnede: { ...u, zoom: Number(e.target.value) } }, 'zoom')} className="w-full accent-violet" />
                            </Veld>
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    aria-pressed={uitsnedeModus}
                                    onClick={() => onUitsnedeModus(!uitsnedeModus)}
                                    className={cn('inline-flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-semibold', uitsnedeModus ? 'border-violet bg-violet/10 text-violet' : 'hover:border-violet')}
                                >
                                    <Move className="size-4" aria-hidden />
                                    {uitsnedeModus ? 'Klaar met verschuiven' : 'Verschuiven in het vak'}
                                </button>
                                <button type="button" onClick={() => onWijzig({ uitsnede: { x: 0.5, y: 0.5, zoom: 1 } })} className="h-10 rounded-lg border px-3 text-sm font-semibold hover:border-violet">
                                    Midden
                                </button>
                            </div>
                        </>
                    )}
                    {creatorVideo && <RechtenWaarschuwing video={creatorVideo} />}
                    <Getal label="Hoekafronding" waarde={laag.hoekafronding ?? 0} min={0} max={Math.min(laag.breedte, laag.hoogte) / 2} onZet={(n) => onWijzig({ hoekafronding: n }, 'hoek')} />
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

            {laag.soort === 'verloop' && (
                <>
                    <p className="text-xs text-muted-foreground">Merkverloop van rood naar violet. Standaard onder 135 graden.</p>
                    <div className="grid grid-cols-2 gap-2">
                        <Getal label="Hoek (graden)" waarde={laag.hoek ?? 135} min={0} max={360} onZet={(n) => onWijzig({ hoek: n }, 'verloophoek')} />
                        <Getal label="Hoekafronding" waarde={laag.hoekafronding ?? 0} min={0} max={Math.min(laag.breedte, laag.hoogte) / 2} onZet={(n) => onWijzig({ hoekafronding: n }, 'hoek')} />
                    </div>
                    <PaletKiezer label="Van" waarde={laag.van} onZet={(k) => onWijzig({ van: k })} />
                    <PaletKiezer label="Naar" waarde={laag.naar} onZet={(k) => onWijzig({ naar: k })} />
                </>
            )}

            {laag.soort === 'logo' && (
                <button type="button" onClick={() => onKiesBron('logo')} className="h-10 rounded-lg border px-3 text-sm font-semibold hover:border-violet">
                    Ander logo
                </button>
            )}

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
            </div>
        </div>
    );
}

const SOORT_LABEL: Record<string, string> = { tekst: 'Tekst', foto: 'Foto', video: 'Video', vorm: 'Vorm', logo: 'Logo', verloop: 'Verloop' };

export function LagenPaneel({
    pagina,
    selectie,
    onKies,
    onWijzig,
    onVerschuif,
    onDupliceer,
    onVerwijder,
}: {
    pagina: Pagina;
    selectie: string | null;
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
            {lagen.map((l, i) => (
                <li
                    key={l.id}
                    className={cn('flex items-center gap-1 rounded-lg border bg-card p-1 pl-2', selectie === l.id && 'border-violet bg-violet/5')}
                >
                    <button type="button" onClick={() => onKies(l.id)} className="min-w-0 flex-1 py-1.5 text-left text-sm">
                        <span className={cn('block truncate font-semibold', !l.zichtbaar && 'text-muted-foreground line-through')}>{l.naam}</span>
                        <span className="block truncate text-xs text-muted-foreground">
                            {SOORT_LABEL[l.soort]}
                            {l.vak ? ', vak' : ''}
                        </span>
                    </button>
                    <IconKnop label={l.zichtbaar ? 'Verbergen' : 'Tonen'} onClick={() => onWijzig(l.id, { zichtbaar: !l.zichtbaar })}>
                        {l.zichtbaar ? <Eye className="size-4" /> : <EyeOff className="size-4" />}
                    </IconKnop>
                    <IconKnop label={l.vergrendeld ? 'Ontgrendelen' : 'Vergrendelen'} onClick={() => onWijzig(l.id, { vergrendeld: !l.vergrendeld })}>
                        {l.vergrendeld ? <Lock className="size-4 text-violet" /> : <LockOpen className="size-4" />}
                    </IconKnop>
                    <IconKnop label="Naar voren" disabled={i === 0} onClick={() => onVerschuif(l.id, 1)}>
                        <ArrowUp className="size-4" />
                    </IconKnop>
                    <IconKnop label="Naar achteren" disabled={i === lagen.length - 1} onClick={() => onVerschuif(l.id, -1)}>
                        <ArrowDown className="size-4" />
                    </IconKnop>
                    <IconKnop label="Dupliceren" onClick={() => onDupliceer(l.id)}>
                        <Copy className="size-4" />
                    </IconKnop>
                    <IconKnop label="Verwijderen" onClick={() => onVerwijder(l.id)}>
                        <Trash2 className="size-4" />
                    </IconKnop>
                </li>
            ))}
        </ul>
    );
}

function IconKnop({ label, onClick, disabled, children }: { label: string; onClick: () => void; disabled?: boolean; children: ReactNode }) {
    return (
        <button type="button" aria-label={label} title={label} disabled={disabled} onClick={onClick} className="grid size-8 shrink-0 place-items-center rounded-md hover:bg-muted disabled:opacity-30">
            {children}
        </button>
    );
}
