/**
 * Merkslot in de browser: de merkkit als context (één bron, van de server) en de rekenhulpjes die de editor
 * en de merkcontrole delen: contrast volgens WCAG, de omtrek van een laag en de vrije ruimte van een formaat.
 */
import Konva from 'konva';
import { createContext, useContext } from 'react';
import type { Laag, Merkkit } from './typen';

export const MerkContext = createContext<Merkkit | null>(null);

export function useMerk(): Merkkit {
    const merk = useContext(MerkContext);

    if (!merk) {
        throw new Error('MerkContext ontbreekt');
    }

    return merk;
}

// Kleur en contrast

export function rgb(hex: string): [number, number, number] {
    const h = hex.replace('#', '');
    const n = parseInt(h.length === 3 ? h.split('').map((c) => c + c).join('') : h.slice(0, 6), 16);

    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

export function hex([r, g, b]: number[]): string {
    return `#${[r, g, b].map((v) => Math.round(Math.max(0, Math.min(255, v))).toString(16).padStart(2, '0')).join('')}`.toUpperCase();
}

export function luminantie(kleur: string): number {
    const [r, g, b] = rgb(kleur).map((v) => {
        const c = v / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrast(a: string, b: string): number {
    const [l1, l2] = [luminantie(a), luminantie(b)].sort((x, y) => y - x);

    return (l1 + 0.05) / (l2 + 0.05);
}

export function meng(boven: string, onder: string, dekking: number): string {
    const a = rgb(boven);
    const b = rgb(onder);

    return hex(a.map((v, i) => v * dekking + b[i] * (1 - dekking)));
}

export function isDonker(kleur: string): boolean {
    return luminantie(kleur) < 0.25;
}

export function dichtsteKleur(kleur: string, palet: string[]): string {
    const [r, g, b] = rgb(kleur);

    return palet.reduce((beste, p) => {
        const [pr, pg, pb] = rgb(p);
        const [br, bg, bb] = rgb(beste);

        return (pr - r) ** 2 + (pg - g) ** 2 + (pb - b) ** 2 < (br - r) ** 2 + (bg - g) ** 2 + (bb - b) ** 2 ? p : beste;
    }, palet[0]);
}

// Vorm van een laag

export function weergaveTekst(l: Laag): string {
    return l.hoofdletters ? (l.tekst ?? '').toLocaleUpperCase('nl-NL') : (l.tekst ?? '');
}

/** Echte hoogte van een tekstlaag (zoals het canvas hem afbreekt). */
export function tekstHoogte(l: Laag): number {
    const t = new Konva.Text({
        text: weergaveTekst(l),
        width: l.breedte,
        fontSize: l.grootte ?? 48,
        fontFamily: l.lettertype ?? 'Montserrat Variable',
        fontStyle: String(l.gewicht ?? 400),
        lineHeight: l.regelafstand ?? 1.2,
    });
    const h = t.height();
    t.destroy();

    return h;
}

export type Kader = { x: number; y: number; breedte: number; hoogte: number };

/** Omtrek van een laag op het canvas, met draaiing meegerekend. */
export function kaderVan(l: Laag): Kader {
    const h = l.soort === 'tekst' ? tekstHoogte(l) : l.hoogte;
    const w = l.breedte;

    if (!l.rotatie) {
        return { x: l.x, y: l.y, breedte: w, hoogte: h };
    }

    const a = (l.rotatie * Math.PI) / 180;
    const hoeken = [
        [0, 0],
        [w, 0],
        [w, h],
        [0, h],
    ].map(([px, py]) => [l.x + px * Math.cos(a) - py * Math.sin(a), l.y + px * Math.sin(a) + py * Math.cos(a)]);
    const xs = hoeken.map((p) => p[0]);
    const ys = hoeken.map((p) => p[1]);

    return { x: Math.min(...xs), y: Math.min(...ys), breedte: Math.max(...xs) - Math.min(...xs), hoogte: Math.max(...ys) - Math.min(...ys) };
}

/** Vrije ruimte van een formaat: binnen de marges en buiten de veilige zones. */
export function vrijeRuimte(merk: Merkkit, formaat: string, W: number, H: number) {
    const f = merk.formaten[formaat] ?? { marge: 60, veilig_boven: 0, veilig_onder: 0 };

    return {
        marge: f.marge,
        links: f.marge,
        rechts: W - f.marge,
        boven: Math.max(f.marge, f.veilig_boven),
        onder: H - Math.max(f.marge, f.veilig_onder),
        veiligBoven: f.veilig_boven,
        veiligOnder: f.veilig_onder,
    };
}
