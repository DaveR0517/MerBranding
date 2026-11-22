import { Head, useForm } from '@inertiajs/react';
import { Lock, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Kaart, Pagina, PaginaKop, Rij, Sectie, Tabs } from '@/components/pagina';
import type { Merkkit, StijlSleutel } from '@/components/studio/typen';
import { STIJLEN } from '@/components/studio/typen';
import { datumTijd } from '@/lib/datum';
import merkkitRoutes from '@/routes/team/studio/merkkit';

type Props = {
    merkkit: Merkkit;
    logoBestanden: string[];
    magBewerken: boolean;
    wijzigingen: { op: string | null; door: string | null; aantal: number; velden: string[] }[];
};

type Tab = 'kleuren' | 'stijlen' | 'formaten' | 'controle';

const veld = 'h-10 w-full min-w-0 rounded-lg border bg-background px-2.5 text-sm disabled:opacity-70';

const CONTROLE_LABEL: Record<keyof Merkkit['controle'], string> = {
    min_tekst: 'Minimale lettergrootte (px op 1080 breed)',
    contrast_klein: 'Contrast kleine tekst (WCAG, x:1)',
    contrast_groot: 'Contrast grote tekst (x:1)',
    groot_vanaf_scherm_px: 'Grote tekst vanaf (px op het scherm)',
    schermbreedte: 'Schermbreedte telefoon (px)',
    logo_min_breedte: 'Logo minimaal breed (px op 1080)',
    max_stijlen: 'Tekststijlen per pagina, hooguit',
};

/** MeStudio, Merkkit: de ene bron voor de huisstijl. Iedereen ziet hem; alleen Super Admin past hem aan (logboek). */
export default function MerkkitScherm({ merkkit, logoBestanden, magBewerken, wijzigingen }: Props) {
    const [tab, setTab] = useState<Tab>('kleuren');
    const form = useForm({
        palet: merkkit.palet,
        verloop: merkkit.verloop,
        stijlen: merkkit.stijlen,
        logos: merkkit.logos,
        formaten: Object.fromEntries(Object.entries(merkkit.formaten).map(([k, f]) => [k, { marge: f.marge, veilig_boven: f.veilig_boven, veilig_onder: f.veilig_onder, schaal: f.schaal }])),
        raster: merkkit.raster,
        controle: merkkit.controle,
    });
    const d = form.data;
    const uit = !magBewerken;
    const fout = Object.values(form.errors)[0];

    const zetStijl = (s: StijlSleutel, veldNaam: string, waarde: unknown) => form.setData('stijlen', { ...d.stijlen, [s]: { ...d.stijlen[s], [veldNaam]: waarde } });

    return (
        <>
            <Head title="Merkkit" />
            <Pagina>
                <PaginaKop
                    boven="MeStudio"
                    titel="Merkkit"
                    omschrijving="De ene bron voor de huisstijl in MeStudio: kleuren, tekststijlen, logo's, marges en veilige zones. Editor, sjablonen en merkcontrole lezen alles hieruit."
                    acties={
                        magBewerken && (
                            <button
                                type="button"
                                data-test="merkkit-opslaan"
                                disabled={form.processing || !form.isDirty}
                                onClick={() => form.put(merkkitRoutes.opslaan.url(), { preserveScroll: true })}
                                className="inline-flex h-10 items-center justify-center rounded-lg bg-violet px-4 text-sm font-semibold text-white hover:bg-violet/90 disabled:opacity-50"
                            >
                                {form.processing ? 'Opslaan...' : 'Merkkit opslaan'}
                            </button>
                        )
                    }
                    extra={
                        <>
                            {uit && (
                                <p className="flex items-center gap-2 rounded-lg bg-violet/10 p-3 text-sm text-violet">
                                    <Lock className="size-4 shrink-0" aria-hidden /> Alleen Super Admin past de merkkit aan. Zo blijft alles MEBRANDING.
                                </p>
                            )}
                            {fout && (
                                <p role="alert" className="text-sm text-rood">
                                    {fout}
                                </p>
                            )}
                            <Tabs
                                tabs={[
                                    { waarde: 'kleuren', label: "Kleuren en logo's" },
                                    { waarde: 'stijlen', label: 'Tekststijlen' },
                                    { waarde: 'formaten', label: 'Marges en zones' },
                                    { waarde: 'controle', label: 'Merkcontrole' },
                                ]}
                                actief={tab}
                                onKies={setTab}
                            />
                        </>
                    }
                />

                {tab === 'kleuren' && (
                    <div className="grid gap-5 lg:grid-cols-2">
                        <Kaart>
                            <Sectie titel="Palet" omschrijving="Alleen deze kleuren zijn te kiezen in de editor.">
                                <ul className="space-y-2">
                                    {d.palet.map((p, i) => (
                                        <li key={i} className="flex items-center gap-2">
                                            <input
                                                type="color"
                                                aria-label={`Kleur ${p.naam}`}
                                                disabled={uit}
                                                value={p.kleur}
                                                onChange={(e) => form.setData('palet', d.palet.map((x, j) => (j === i ? { ...x, kleur: e.target.value.toUpperCase() } : x)))}
                                                className="size-10 shrink-0 cursor-pointer rounded-lg border bg-card p-1"
                                            />
                                            <input aria-label="Naam" disabled={uit} className={veld} value={p.naam} onChange={(e) => form.setData('palet', d.palet.map((x, j) => (j === i ? { ...x, naam: e.target.value } : x)))} />
                                            <span className="w-20 shrink-0 font-mono text-xs text-muted-foreground">{p.kleur}</span>
                                            {magBewerken && d.palet.length > 2 && (
                                                <button type="button" aria-label={`${p.naam} weghalen`} onClick={() => form.setData('palet', d.palet.filter((_, j) => j !== i))} className="grid size-9 shrink-0 place-items-center rounded-md hover:bg-muted">
                                                    <Trash2 className="size-4" />
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                                {magBewerken && d.palet.length < 12 && (
                                    <button type="button" onClick={() => form.setData('palet', [...d.palet, { kleur: '#614ED9', naam: 'Nieuwe kleur' }])} className="inline-flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-semibold hover:border-violet">
                                        <Plus className="size-4" aria-hidden /> Kleur erbij
                                    </button>
                                )}
                            </Sectie>
                        </Kaart>
                        <div className="space-y-5">
                            <Kaart>
                                <Sectie titel="Merkverloop">
                                    <div className="h-14 rounded-lg" style={{ background: `linear-gradient(${d.verloop.hoek}deg, ${d.verloop.van}, ${d.verloop.naar})` }} />
                                    <div className="grid grid-cols-3 gap-2">
                                        {(['van', 'naar'] as const).map((k) => (
                                            <label key={k} className="space-y-1">
                                                <span className="text-xs font-semibold text-muted-foreground">{k === 'van' ? 'Van' : 'Naar'}</span>
                                                <input type="color" disabled={uit} value={d.verloop[k]} onChange={(e) => form.setData('verloop', { ...d.verloop, [k]: e.target.value.toUpperCase() })} className="h-10 w-full rounded-lg border bg-card p-1" />
                                            </label>
                                        ))}
                                        <label className="space-y-1">
                                            <span className="text-xs font-semibold text-muted-foreground">Hoek</span>
                                            <input type="number" disabled={uit} className={veld} value={d.verloop.hoek} onChange={(e) => form.setData('verloop', { ...d.verloop, hoek: Number(e.target.value) })} />
                                        </label>
                                    </div>
                                </Sectie>
                            </Kaart>
                            <Kaart>
                                <Sectie titel="Logo's" omschrijving="Uit public/brand. Wit op donker, aubergine op licht.">
                                    {(['wit', 'aubergine', 'verloop'] as const).map((v) => (
                                        <label key={v} className="flex items-center gap-3">
                                            <span className={`grid h-12 w-28 shrink-0 place-items-center rounded-lg p-2 ${v === 'wit' ? 'bg-aubergine-dark' : 'bg-[#F6F5F9]'}`}>
                                                <img src={`/brand/${d.logos[v]}`} alt="" className="max-h-8 max-w-full" />
                                            </span>
                                            <span className="w-20 shrink-0 text-sm font-semibold capitalize">{v}</span>
                                            <select disabled={uit} className={veld} value={d.logos[v]} onChange={(e) => form.setData('logos', { ...d.logos, [v]: e.target.value })}>
                                                {logoBestanden.map((b) => (
                                                    <option key={b} value={b}>
                                                        {b}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                    ))}
                                </Sectie>
                            </Kaart>
                        </div>
                    </div>
                )}

                {tab === 'stijlen' && (
                    <Kaart>
                        <Sectie titel="Tekststijlen" omschrijving="Maten gelden voor 1080 breed; per formaat vermenigvuldigd met de schaal. Lettertypes: alleen Montserrat en Noto Sans.">
                            <div className="space-y-3">
                                {STIJLEN.map((s) => {
                                    const st = d.stijlen[s];

                                    return (
                                        <div key={s} className="grid gap-2 rounded-xl border p-3 md:grid-cols-[1fr_auto]">
                                            <div className="grid min-w-0 grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                                                <label className="space-y-1">
                                                    <span className="text-xs font-semibold text-muted-foreground">Naam</span>
                                                    <input disabled={uit} className={veld} value={st.naam} onChange={(e) => zetStijl(s, 'naam', e.target.value)} />
                                                </label>
                                                <label className="space-y-1">
                                                    <span className="text-xs font-semibold text-muted-foreground">Lettertype</span>
                                                    <select disabled={uit} className={veld} value={st.lettertype} onChange={(e) => zetStijl(s, 'lettertype', e.target.value)}>
                                                        {merkkit.lettertypes.map((l) => (
                                                            <option key={l} value={l}>
                                                                {l.replace(' Variable', '')}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </label>
                                                <label className="space-y-1">
                                                    <span className="text-xs font-semibold text-muted-foreground">Grootte</span>
                                                    <input type="number" disabled={uit} className={veld} value={st.grootte} onChange={(e) => zetStijl(s, 'grootte', Number(e.target.value))} />
                                                </label>
                                                <label className="space-y-1">
                                                    <span className="text-xs font-semibold text-muted-foreground">Gewicht</span>
                                                    <select disabled={uit} className={veld} value={st.gewicht} onChange={(e) => zetStijl(s, 'gewicht', Number(e.target.value))}>
                                                        {[400, 500, 600, 700, 800, 900].map((g) => (
                                                            <option key={g}>{g}</option>
                                                        ))}
                                                    </select>
                                                </label>
                                                <label className="space-y-1">
                                                    <span className="text-xs font-semibold text-muted-foreground">Regelafstand</span>
                                                    <input type="number" step={0.05} disabled={uit} className={veld} value={st.regelafstand} onChange={(e) => zetStijl(s, 'regelafstand', Number(e.target.value))} />
                                                </label>
                                                <label className="flex items-end gap-2 pb-2.5 text-sm">
                                                    <input type="checkbox" disabled={uit} className="size-4 accent-violet" checked={st.hoofdletters} onChange={(e) => zetStijl(s, 'hoofdletters', e.target.checked)} />
                                                    Hoofdletters
                                                </label>
                                            </div>
                                            <p
                                                className="truncate self-center rounded-lg bg-aubergine-dark px-3 py-2 text-white md:w-56"
                                                style={{ fontFamily: `'${st.lettertype}'`, fontWeight: st.gewicht, fontSize: Math.min(40, Math.max(14, st.grootte / 3)), textTransform: st.hoofdletters ? 'uppercase' : 'none' }}
                                            >
                                                {s === 'cijfer' ? '1.234' : st.naam}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>
                        </Sectie>
                    </Kaart>
                )}

                {tab === 'formaten' && (
                    <Kaart>
                        <Sectie titel="Marges, veilige zones en schaal per formaat" omschrijving="Story en Reel: boven en onder vrij voor de knoppen en de naam van Instagram. Slepen klikt vast op de marges en op het raster.">
                            <div className="space-y-2">
                                {Object.entries(merkkit.formaten).map(([k, f]) => (
                                    <div key={k} className="grid grid-cols-2 items-end gap-2 rounded-xl border p-3 sm:grid-cols-5">
                                        <p className="col-span-2 text-sm font-semibold sm:col-span-1">
                                            {f.naam}
                                            <span className="block text-xs font-normal text-muted-foreground tabular-nums">
                                                {f.breedte} x {f.hoogte}
                                            </span>
                                        </p>
                                        {(['marge', 'veilig_boven', 'veilig_onder', 'schaal'] as const).map((v) => (
                                            <label key={v} className="space-y-1">
                                                <span className="text-xs font-semibold text-muted-foreground">{{ marge: 'Marge', veilig_boven: 'Vrij boven', veilig_onder: 'Vrij onder', schaal: 'Schaal tekst' }[v]}</span>
                                                <input
                                                    type="number"
                                                    step={v === 'schaal' ? 0.01 : 1}
                                                    disabled={uit}
                                                    className={veld}
                                                    value={d.formaten[k][v]}
                                                    onChange={(e) => form.setData('formaten', { ...d.formaten, [k]: { ...d.formaten[k], [v]: Number(e.target.value) } })}
                                                />
                                            </label>
                                        ))}
                                    </div>
                                ))}
                                <label className="flex max-w-xs items-center gap-2 text-sm">
                                    <span className="shrink-0 font-semibold">Raster (px)</span>
                                    <input type="number" disabled={uit} className={veld} value={d.raster} onChange={(e) => form.setData('raster', Number(e.target.value))} />
                                </label>
                            </div>
                        </Sectie>
                    </Kaart>
                )}

                {tab === 'controle' && (
                    <div className="grid gap-5 lg:grid-cols-2">
                        <Kaart>
                            <Sectie titel="Regels van de merkcontrole" omschrijving="Rood houdt een export tegen. Contrast volgens WCAG.">
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {(Object.keys(CONTROLE_LABEL) as (keyof Merkkit['controle'])[]).map((k) => (
                                        <label key={k} className="space-y-1">
                                            <span className="text-xs font-semibold text-muted-foreground">{CONTROLE_LABEL[k]}</span>
                                            <input
                                                type="number"
                                                step={k.startsWith('contrast') ? 0.1 : 1}
                                                disabled={uit}
                                                className={veld}
                                                value={d.controle[k]}
                                                onChange={(e) => form.setData('controle', { ...d.controle, [k]: Number(e.target.value) })}
                                            />
                                        </label>
                                    ))}
                                </div>
                            </Sectie>
                        </Kaart>
                        <Sectie titel="Laatste wijzigingen" omschrijving="Elke wijziging staat met oud en nieuw in het logboek.">
                            {wijzigingen.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Nog niets gewijzigd: de standaard uit de code geldt.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {wijzigingen.map((w, i) => (
                                        <li key={i}>
                                            <Rij titel={`${w.door ?? 'Onbekend'}: ${w.aantal} ${w.aantal === 1 ? 'waarde' : 'waarden'}`} onder={`${datumTijd(w.op)}, ${w.velden.join(', ')}`} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Sectie>
                    </div>
                )}
            </Pagina>
        </>
    );
}
