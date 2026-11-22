/**
 * Gedeelde cache van afbeeldingen en video-elementen. De editor, de miniaturen en de export tekenen
 * hetzelfde element, zodat een export niet opnieuw hoeft te laden en een video overal hetzelfde beeld toont.
 */
import { useEffect, useState } from 'react';
import type { Pagina } from './typen';
import { bronUrl } from './typen';

const beloftes = new Map<string, Promise<HTMLImageElement>>();
const geladen = new Map<string, HTMLImageElement>();
const videos = new Map<string, HTMLVideoElement>();
const videoKlaar = new Set<string>();

/** Svg zonder breedte en hoogte (onze logo's) krijgt die uit de viewBox, anders tekent het canvas niets. */
async function svgAlsBlob(url: string): Promise<string> {
    const tekst = await (await fetch(url)).text();
    const box = tekst.match(/viewBox="([\d.\s-]+)"/)?.[1]?.trim().split(/\s+/).map(Number);
    const metMaat = box && !/<svg[^>]*\swidth=/.test(tekst) ? tekst.replace('<svg', `<svg width="${box[2] * 4}" height="${box[3] * 4}"`) : tekst;

    return URL.createObjectURL(new Blob([metMaat], { type: 'image/svg+xml' }));
}

export function laadAfbeelding(url: string): Promise<HTMLImageElement> {
    const bestaand = beloftes.get(url);

    if (bestaand) {
        return bestaand;
    }

    const belofte = (async () => {
        const img = new Image();
        img.decoding = 'async';
        img.src = url.split('?')[0].endsWith('.svg') ? await svgAlsBlob(url) : url;
        await img.decode().catch(
            () =>
                new Promise<void>((klaar, fout) => {
                    img.onload = () => klaar();
                    img.onerror = () => fout(new Error(`Afbeelding laadt niet: ${url}`));
                }),
        );
        geladen.set(url, img);

        return img;
    })();
    beloftes.set(url, belofte);
    belofte.catch(() => beloftes.delete(url));

    return belofte;
}

export function useAfbeelding(url: string | null): HTMLImageElement | null {
    const [img, setImg] = useState<HTMLImageElement | null>(() => (url ? (geladen.get(url) ?? null) : null));

    useEffect(() => {
        if (!url) {
            setImg(null);

            return;
        }

        let actief = true;
        laadAfbeelding(url)
            .then((i) => actief && setImg(i))
            .catch(() => actief && setImg(null));

        return () => {
            actief = false;
        };
    }, [url]);

    return url ? (geladen.get(url) ?? img) : null;
}

/** Eén video-element per url: stil en herhalend in de editor, met geluid tijdens het opnemen. */
export function videoElement(url: string): HTMLVideoElement {
    let v = videos.get(url);

    if (!v) {
        v = document.createElement('video');
        v.src = url;
        v.muted = true;
        v.loop = true;
        v.playsInline = true;
        v.preload = 'auto';
        v.addEventListener('loadeddata', () => videoKlaar.add(url));
        videos.set(url, v);
    }

    return v;
}

export function laadVideo(url: string): Promise<HTMLVideoElement> {
    const v = videoElement(url);

    if (videoKlaar.has(url) || v.readyState >= 2) {
        return Promise.resolve(v);
    }

    return new Promise((klaar, fout) => {
        v.addEventListener('loadeddata', () => klaar(v), { once: true });
        v.addEventListener('error', () => fout(new Error('Deze video speelt niet af in deze browser.')), { once: true });
    });
}

export function useVideo(url: string | null): HTMLVideoElement | null {
    const [, setVersie] = useState(0);

    useEffect(() => {
        if (!url) {
            return;
        }

        let actief = true;
        laadVideo(url)
            .then(() => actief && setVersie((n) => n + 1))
            .catch(() => undefined);

        return () => {
            actief = false;
        };
    }, [url]);

    return url && (videoKlaar.has(url) || videoElement(url).readyState >= 2) ? videoElement(url) : null;
}

/** Alle media van een pagina laden, voordat de export tekent. */
export async function laadPagina(pagina: Pagina): Promise<HTMLVideoElement[]> {
    const lijst: HTMLVideoElement[] = [];
    await Promise.all(
        pagina.lagen
            .filter((l) => l.zichtbaar)
            .map(async (l) => {
                const url = bronUrl(l.bron);

                if (!url) {
                    return;
                }

                if (l.soort === 'video') {
                    lijst.push(await laadVideo(url));
                } else if (l.soort === 'foto' || l.soort === 'logo') {
                    await laadAfbeelding(url).catch(() => undefined);
                }
            }),
    );

    return lijst;
}
