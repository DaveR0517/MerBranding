/**
 * MeStudio: de vorm van een ontwerp. Eén bron voor lagen, bronnen, palet en formaten; de editor,
 * de miniaturen en de export gebruiken allemaal deze typen.
 */
import { video as creatorVideoRoute } from '@/routes';
import mediatheek from '@/routes/team/studio/mediatheek';

export type Soort = 'tekst' | 'foto' | 'video' | 'vorm' | 'logo' | 'verloop';

/** Waar een foto, video of logo vandaan komt. Opgeslagen als verwijzing, nooit als vaste url. */
export type Bron = { soort: 'media'; id: number } | { soort: 'creator'; id: number } | { soort: 'logo'; naam: string };

export type Laag = {
    id: string;
    soort: Soort;
    naam: string;
    x: number;
    y: number;
    breedte: number;
    hoogte: number;
    rotatie: number;
    zichtbaar: boolean;
    vergrendeld: boolean;
    dekking?: number;
    /** Benoemd vak in een sjabloon: hier zet je een foto, video of eigen tekst in. */
    vak?: boolean;
    vakNaam?: string | null;
    // tekst
    tekst?: string;
    lettertype?: string;
    grootte?: number;
    gewicht?: number;
    kleur?: string;
    uitlijning?: 'left' | 'center' | 'right';
    regelafstand?: number;
    // foto, video, logo
    bron?: Bron | null;
    /** Uitsnede in het vak: x en y is het punt van de media in het midden van het vak (0 tot 1), zoom vanaf 1 (vult het vak). */
    uitsnede?: { x: number; y: number; zoom: number };
    // vorm en verloop
    vorm?: 'rechthoek' | 'cirkel';
    vulling?: string;
    hoekafronding?: number;
    van?: string;
    naar?: string;
    hoek?: number;
};

export type Pagina = { achtergrond: string; lagen: Laag[] };

export type Formaat = { naam: string; breedte: number; hoogte: number };

export type OntwerpItem = {
    id: number;
    name: string;
    format: string;
    format_naam: string;
    width: number;
    height: number;
    aantal_paginas: number;
    pages: Pagina[] | null;
    status: string | null;
    scheduled_at?: string | null;
    caption?: string | null;
    feed_position?: number | null;
    post_url?: string | null;
    posted_at?: string | null;
    preview?: string | null;
    maker?: string | null;
    updated_at?: string | null;
    heeft_video?: boolean;
    is_builtin?: boolean;
    archived?: boolean;
    vakken?: number;
};

export type Media = {
    id: number;
    kind: 'foto' | 'video' | 'logo';
    name: string;
    mime: string | null;
    size: number;
    width: number | null;
    height: number | null;
    url: string;
    created_at: string | null;
    maker: string | null;
};

export type Gebruiksrecht = { kind: string; starts_on: string | null; ends_on: string | null; status: string; is_proposal: boolean };

export type CreatorVideo = {
    id: number;
    titel: string | null;
    nummer: string | null;
    bedrijf: string | null;
    creator: string | null;
    url: string;
    mime: string | null;
    gebruiksrechten: Gebruiksrecht[];
};

export type Logo = { naam: string; label: string; url: string };

export type Export = {
    id: number;
    kind: 'png' | 'jpg' | 'webm' | 'mp4';
    page: number;
    size: number;
    status: 'klaar' | 'omzetten' | 'mislukt' | 'alleen_webm';
    message: string | null;
    created_at: string | null;
    url: string;
};

/** Vaste huisstijlkleuren. */
export const PALET = [
    { kleur: '#3A3659', naam: 'Aubergine' },
    { kleur: '#2E2B48', naam: 'Aubergine donker' },
    { kleur: '#6D6794', naam: 'Lila' },
    { kleur: '#614ED9', naam: 'Violet' },
    { kleur: '#D9303E', naam: 'Rood' },
    { kleur: '#FFFFFF', naam: 'Wit' },
    { kleur: '#F6F5F9', naam: 'Licht' },
    { kleur: '#2A2840', naam: 'Inkt' },
];

export const LETTERTYPES = ['Montserrat Variable', 'Noto Sans Variable'];

export const STATUS_LABEL: Record<string, string> = { concept: 'Concept', klaar: 'Klaar', ingepland: 'Ingepland', geplaatst: 'Geplaatst' };

/** Url van een bron. Logo's staan openbaar in /brand; de rest via beveiligde routes. */
export function bronUrl(bron: Bron | null | undefined): string | null {
    if (!bron) {
        return null;
    }

    switch (bron.soort) {
        case 'media':
            return mediatheek.bestand.url(bron.id);
        case 'creator':
            return creatorVideoRoute.url(bron.id);
        case 'logo':
            return `/brand/${bron.naam}`;
    }
}

let teller = 0;

export function nieuwId(soort: string): string {
    teller += 1;

    return `${soort}-${Date.now().toString(36)}${teller}`;
}

/** Nieuwe laag in het midden van het canvas. */
export function nieuweLaag(soort: Soort, W: number, H: number, extra: Partial<Laag> = {}): Laag {
    const basis = { id: nieuwId(soort), soort, rotatie: 0, zichtbaar: true, vergrendeld: false };
    const midden = (b: number, h: number) => ({ x: Math.round((W - b) / 2), y: Math.round((H - h) / 2), breedte: b, hoogte: h });

    switch (soort) {
        case 'tekst':
            return { ...basis, naam: 'Tekst', ...midden(W * 0.8, 96), tekst: 'Jouw tekst', lettertype: 'Montserrat Variable', grootte: 80, gewicht: 800, kleur: '#3A3659', uitlijning: 'center', regelafstand: 1.2, ...extra };
        case 'foto':
        case 'video':
            return { ...basis, naam: soort === 'foto' ? 'Foto' : 'Video', ...midden(W * 0.7, H * 0.5), bron: null, uitsnede: { x: 0.5, y: 0.5, zoom: 1 }, hoekafronding: 24, ...extra };
        case 'vorm':
            return { ...basis, naam: extra.vorm === 'cirkel' ? 'Cirkel' : 'Rechthoek', ...midden(W * 0.4, W * 0.4), vorm: 'rechthoek', vulling: '#614ED9', hoekafronding: 0, dekking: 1, ...extra };
        case 'verloop':
            return { ...basis, naam: 'Merkverloop', ...midden(W, H), x: 0, y: 0, van: '#D9303E', naar: '#614ED9', hoek: 135, hoekafronding: 0, ...extra };
        case 'logo':
            return { ...basis, naam: 'Logo', ...midden(W * 0.4, (W * 0.4) / 6.274), bron: { soort: 'logo', naam: 'mebranding-logo-wit.svg' }, ...extra };
    }
}

/** Telt hashtags zoals Instagram (hooguit 30 per post). */
export function telHashtags(tekst: string): number {
    return (tekst.match(/(^|\s)#[\p{L}\p{N}_]+/gu) ?? []).length;
}

/** Fetch met de sessie en CSRF van Laravel; geeft JSON terug of gooit een leesbare fout. */
export async function verstuur<T>(url: string, opties: { method?: string; json?: unknown; formulier?: FormData } = {}): Promise<T> {
    const xsrf = decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
    const antwoord = await fetch(url, {
        method: opties.method ?? 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf,
            ...(opties.json !== undefined ? { 'Content-Type': 'application/json' } : {}),
        },
        body: opties.formulier ?? (opties.json !== undefined ? JSON.stringify(opties.json) : undefined),
    });

    if (!antwoord.ok) {
        const data = await antwoord.json().catch(() => null);
        const eerste = data?.errors ? Object.values(data.errors as Record<string, string[]>)[0]?.[0] : null;
        throw new Error(eerste ?? data?.message ?? `Er ging iets mis (${antwoord.status}).`);
    }

    return antwoord.json() as Promise<T>;
}

export function bestandsgrootte(bytes: number): string {
    return bytes > 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(bytes / 1024))} kB`;
}
