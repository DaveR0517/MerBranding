/**
 * Merkcontrole: toetst een ontwerp aan de merkkit. Rood blokkeert de export (Super Admin kan met reden toch),
 * oranje is een waarschuwing. Waar het automatisch kan, levert een bevinding een herstel mee.
 * Alle grenzen komen uit de merkkit (één bron), maten gelden voor 1080 breed en schalen mee met het formaat.
 */
import type { Kader } from './merk';
import { contrast, dichtsteKleur, isDonker, kaderVan, meng, vrijeRuimte } from './merk';
import type { Laag, Merkkit, Pagina, StijlSleutel } from './typen';
import { STIJLEN, nieuwId, stijlWaarden } from './typen';

export type Niveau = 'groen' | 'oranje' | 'rood';

export type Regel = 'logo' | 'contrast' | 'grootte' | 'marge' | 'veilig' | 'stijlen' | 'palet' | 'merkstijl';

export const REGELS: Record<Regel, string> = {
    logo: 'Logo aanwezig en groot genoeg',
    contrast: 'Contrast tekst en achtergrond',
    grootte: 'Minimale lettergrootte',
    marge: 'Tekst en logo binnen de marges',
    veilig: 'Niets belangrijks in de veilige zones',
    stijlen: 'Hooguit drie tekststijlen per pagina',
    palet: 'Alleen kleuren uit het palet',
    merkstijl: 'Tekst op een merkstijl',
};

export type Bevinding = {
    regel: Regel;
    niveau: Niveau;
    tekst: string;
    pagina: number;
    laagId?: string;
    herstel?: (paginas: Pagina[]) => Pagina[];
};

type Context = { merk: Merkkit; formaat: string; W: number; H: number };

const wijzigLaag = (pagina: number, id: string, patch: (l: Laag) => Partial<Laag>) => (p: Pagina[]) =>
    p.map((pg, i) => (i === pagina ? { ...pg, lagen: pg.lagen.map((l) => (l.id === id ? { ...l, ...patch(l) } : l)) } : pg));

function binnen(k: Kader, x: number, y: number) {
    return x >= k.x && x <= k.x + k.breedte && y >= k.y && y <= k.y + k.hoogte;
}

function bevat(l: Laag, x: number, y: number): boolean {
    if (l.soort === 'vorm' && l.vorm === 'cirkel') {
        const rx = l.breedte / 2;
        const ry = l.hoogte / 2;

        return ((x - l.x - rx) / rx) ** 2 + ((y - l.y - ry) / ry) ** 2 <= 1;
    }

    return binnen(kaderVan(l), x, y);
}

/**
 * Mogelijke achtergrondkleuren op een punt onder laag `index`. Een foto of video kan alles zijn: dan rekenen we met
 * zwart en wit (een halfdoorzichtig vlak erboven telt mee). `foto` geeft aan dat er beeld onder ligt.
 */
function achtergrondOp(pagina: Pagina, index: number, x: number, y: number): { kleuren: string[]; foto: boolean } {
    for (let i = index - 1; i >= 0; i--) {
        const l = pagina.lagen[i];

        if (!l.zichtbaar || l.soort === 'tekst' || l.soort === 'logo' || !bevat(l, x, y)) {
            continue;
        }

        if ((l.soort === 'foto' || l.soort === 'video') && !l.bron) {
            continue; // leeg vak wordt niet geëxporteerd
        }

        const eigen = l.soort === 'verloop' ? [l.van ?? '#D9303E', l.naar ?? '#614ED9'] : l.soort === 'vorm' ? [l.vulling ?? '#FFFFFF'] : ['#000000', '#FFFFFF'];
        const dekking = l.dekking ?? 1;
        const foto = l.soort === 'foto' || l.soort === 'video';

        if (dekking >= 0.99) {
            return { kleuren: eigen, foto };
        }

        const onder = achtergrondOp(pagina, i, x, y);

        return { kleuren: eigen.flatMap((k) => onder.kleuren.map((o) => meng(k, o, dekking))), foto: foto || onder.foto };
    }

    return { kleuren: [pagina.achtergrond || '#FFFFFF'], foto: false };
}

/** Logo onderaan links binnen de vrije ruimte; wit op donker, aubergine op licht. */
function logoErbij(c: Context, pagina: number) {
    return (p: Pagina[]) => {
        const r = vrijeRuimte(c.merk, c.formaat, c.W, c.H);
        const breedte = Math.round(280 * (c.W / 1080));
        const hoogte = Math.round(breedte / 6.274);
        const logo: Laag = {
            id: nieuwId('logo'),
            soort: 'logo',
            naam: 'Logo',
            x: r.links,
            y: r.onder - hoogte,
            breedte,
            hoogte,
            rotatie: 0,
            zichtbaar: true,
            vergrendeld: true,
            merk: true,
            bron: { soort: 'logo', naam: isDonker(p[pagina].achtergrond) ? c.merk.logos.wit : c.merk.logos.aubergine },
        };

        return p.map((pg, i) => (i === pagina ? { ...pg, lagen: [...pg.lagen, logo] } : pg));
    };
}

/** Schuif (en zo nodig versmal) een laag tot hij binnen de vrije ruimte valt. */
function naarBinnen(c: Context, pagina: number, l: Laag) {
    const r = vrijeRuimte(c.merk, c.formaat, c.W, c.H);

    return wijzigLaag(pagina, l.id, (laag) => {
        const breedte = Math.min(laag.breedte, r.rechts - r.links);
        const k = kaderVan({ ...laag, breedte });
        const hoogte = Math.min(k.hoogte, r.onder - r.boven);
        const ratio = laag.soort === 'logo' ? breedte / laag.breedte : 1;

        return {
            breedte,
            ...(laag.soort === 'logo' ? { hoogte: Math.round(laag.hoogte * ratio) } : {}),
            x: Math.round(Math.min(Math.max(laag.x, r.links), r.rechts - breedte)),
            y: Math.round(Math.min(Math.max(laag.y, r.boven), r.onder - hoogte)),
        };
    });
}

export function controleer(paginas: Pagina[], c: Context): Bevinding[] {
    const { merk, formaat, W, H } = c;
    const regels = merk.controle;
    const schaal = W / 1080;
    const palet = merk.palet.map((p) => p.kleur.toUpperCase());
    const r = vrijeRuimte(merk, formaat, W, H);
    const uit: Bevinding[] = [];

    paginas.forEach((pagina, pi) => {
        const nr = paginas.length > 1 ? `Pagina ${pi + 1}: ` : '';
        const zichtbaar = pagina.lagen.filter((l) => l.zichtbaar);
        const logos = zichtbaar.filter((l) => l.soort === 'logo');

        // Logo
        if (logos.length === 0) {
            uit.push({ regel: 'logo', niveau: 'rood', pagina: pi, tekst: `${nr}geen logo.`, herstel: logoErbij(c, pi) });
        }

        for (const l of logos) {
            const min = regels.logo_min_breedte * schaal;

            if (l.breedte < min - 0.5) {
                uit.push({
                    regel: 'logo',
                    niveau: 'rood',
                    pagina: pi,
                    laagId: l.id,
                    tekst: `${nr}logo is ${Math.round(l.breedte)} px breed, minimaal ${Math.round(min)}.`,
                    herstel: wijzigLaag(pi, l.id, (x) => ({ breedte: Math.round(min), hoogte: Math.round((x.hoogte * min) / x.breedte) })),
                });
            }
        }

        // Palet (foto's en video's uitgezonderd)
        const buitenPalet = (k?: string) => !!k && !palet.includes(k.toUpperCase());

        if (buitenPalet(pagina.achtergrond)) {
            uit.push({
                regel: 'palet',
                niveau: 'rood',
                pagina: pi,
                tekst: `${nr}achtergrond ${pagina.achtergrond} hoort niet bij het palet.`,
                herstel: (p) => p.map((pg, i) => (i === pi ? { ...pg, achtergrond: dichtsteKleur(pg.achtergrond, palet) } : pg)),
            });
        }

        for (const l of zichtbaar) {
            const velden = (l.soort === 'tekst' ? ['kleur'] : l.soort === 'vorm' ? ['vulling'] : l.soort === 'verloop' ? ['van', 'naar'] : []) as (keyof Laag)[];
            const fout = velden.filter((v) => buitenPalet(l[v] as string | undefined));

            if (fout.length) {
                uit.push({
                    regel: 'palet',
                    niveau: 'rood',
                    pagina: pi,
                    laagId: l.id,
                    tekst: `${nr}"${l.naam}" heeft een kleur buiten het palet.`,
                    herstel: wijzigLaag(pi, l.id, (x) => Object.fromEntries(fout.map((v) => [v, dichtsteKleur(x[v] as string, palet)]))),
                });
            }
        }

        // Tekst: stijl, grootte, contrast
        const teksten = zichtbaar.filter((l) => l.soort === 'tekst' && (l.tekst ?? '').trim() !== '');
        const gebruikt = new Set(teksten.map((l) => (l.stijl && !l.vrij ? l.stijl : `vrij-${l.grootte}-${l.gewicht}-${l.lettertype}`)));

        if (gebruikt.size > regels.max_stijlen) {
            uit.push({ regel: 'stijlen', niveau: 'rood', pagina: pi, tekst: `${nr}${gebruikt.size} tekststijlen, hooguit ${regels.max_stijlen}. Kies voor een paar teksten dezelfde stijl.` });
        }

        for (const l of teksten) {
            const index = pagina.lagen.indexOf(l);
            const grootte = l.grootte ?? 48;

            if (!l.stijl || l.vrij) {
                const dichtst = STIJLEN.reduce<StijlSleutel>((b, s) => (Math.abs(merk.stijlen[s].grootte - grootte / schaal) < Math.abs(merk.stijlen[b].grootte - grootte / schaal) ? s : b), 'tekst');
                uit.push({
                    regel: 'merkstijl',
                    niveau: 'oranje',
                    pagina: pi,
                    laagId: l.id,
                    tekst: `${nr}"${l.naam}" heeft geen merkstijl.`,
                    herstel: wijzigLaag(pi, l.id, () => ({ stijl: dichtst, vrij: false, ...stijlWaarden(merk, dichtst, formaat) })),
                });
            }

            if (grootte / schaal < regels.min_tekst - 0.5) {
                uit.push({
                    regel: 'grootte',
                    niveau: 'rood',
                    pagina: pi,
                    laagId: l.id,
                    tekst: `${nr}"${l.naam}" is te klein (${Math.round(grootte / schaal)} px op 1080, minimaal ${regels.min_tekst}).`,
                    herstel: wijzigLaag(pi, l.id, (x) => (x.stijl && merk.stijlen[x.stijl].grootte >= regels.min_tekst ? { vrij: false, ...stijlWaarden(merk, x.stijl, formaat) } : { grootte: Math.ceil(regels.min_tekst * schaal) })),
                });
            }

            const k = kaderVan(l);
            const punten = [0.2, 0.5, 0.8].map((f) => [k.x + k.breedte * f, k.y + k.hoogte / 2]);
            const achter = punten.map(([x, y]) => achtergrondOp(pagina, index, x, y));
            const laagste = Math.min(...achter.flatMap((a) => a.kleuren.map((kl) => contrast(l.kleur ?? '#000000', kl))));
            const schermPx = (grootte * regels.schermbreedte) / W;
            const nodig = schermPx >= regels.groot_vanaf_scherm_px ? regels.contrast_groot : regels.contrast_klein;

            if (laagste < nodig) {
                const opFoto = achter.some((a) => a.foto);
                const kleuren = achter.flatMap((a) => a.kleuren);
                const beste = palet.reduce((b, p) => (Math.min(...kleuren.map((kl) => contrast(p, kl))) > Math.min(...kleuren.map((kl) => contrast(b, kl))) ? p : b), palet[0]);
                uit.push({
                    regel: 'contrast',
                    niveau: opFoto ? 'oranje' : 'rood',
                    pagina: pi,
                    laagId: l.id,
                    tekst: opFoto
                        ? `${nr}"${l.naam}" staat op een foto of video: controleer zelf of het leesbaar is (of leg er een vlak onder).`
                        : `${nr}"${l.naam}" heeft te weinig contrast (${laagste.toFixed(1).replace('.', ',')}:1, minimaal ${String(nodig).replace('.', ',')}:1).`,
                    herstel: opFoto ? undefined : wijzigLaag(pi, l.id, () => ({ kleur: beste })),
                });
            }
        }

        // Marges en veilige zones: tekst en logo
        for (const l of zichtbaar.filter((x) => x.soort === 'tekst' || x.soort === 'logo')) {
            const k = kaderVan(l);
            const buitenMarge = k.x < r.links - 0.5 || k.x + k.breedte > r.rechts + 0.5 || k.y < r.marge - 0.5 || k.y + k.hoogte > H - r.marge + 0.5;
            const inVeilig = (r.veiligBoven > 0 && k.y < r.veiligBoven - 0.5) || (r.veiligOnder > 0 && k.y + k.hoogte > H - r.veiligOnder + 0.5);

            if (buitenMarge) {
                uit.push({ regel: 'marge', niveau: 'rood', pagina: pi, laagId: l.id, tekst: `${nr}"${l.naam}" valt buiten de marge van ${r.marge} px.`, herstel: naarBinnen(c, pi, l) });
            } else if (inVeilig) {
                uit.push({ regel: 'veilig', niveau: 'rood', pagina: pi, laagId: l.id, tekst: `${nr}"${l.naam}" staat in de veilige zone van Instagram (knoppen en naam).`, herstel: naarBinnen(c, pi, l) });
            }
        }
    });

    return uit;
}

export function niveauVan(bevindingen: Bevinding[]): Niveau {
    return bevindingen.some((b) => b.niveau === 'rood') ? 'rood' : bevindingen.some((b) => b.niveau === 'oranje') ? 'oranje' : 'groen';
}

/** Alle automatische herstellingen achter elkaar (opnieuw controleren daarna, want een herstel kan een ander oplossen). */
export function herstelAlles(paginas: Pagina[], c: Context): Pagina[] {
    let p = paginas;

    for (let ronde = 0; ronde < 3; ronde++) {
        const te = controleer(p, c).filter((b) => b.herstel && b.niveau === 'rood');

        if (te.length === 0) {
            break;
        }

        // Per ronde één herstel per laag, zodat herstellingen elkaar niet overschrijven.
        const gezien = new Set<string>();

        for (const b of te) {
            const sleutel = `${b.pagina}:${b.laagId ?? b.regel}`;

            if (!gezien.has(sleutel)) {
                gezien.add(sleutel);
                p = b.herstel!(p);
            }
        }
    }

    return p;
}
