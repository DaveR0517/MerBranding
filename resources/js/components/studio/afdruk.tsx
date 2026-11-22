/**
 * Export op ware grootte: een verborgen podium tekent een pagina op 1 op 1 (1080 breed is echt 1080),
 * zonder hulplijnen of lege vakken. Afbeeldingen als PNG of JPG; video's opgenomen in de browser
 * (canvas.captureStream plus het geluid van de video via Web Audio, MediaRecorder).
 */
import type Konva from 'konva';
import { useCallback, useRef, useState } from 'react';
import { Layer, Stage } from 'react-konva';
import { PaginaInhoud } from './canvas';
import { laadPagina } from './media';
import type { Pagina } from './typen';

type Taak = { pagina: Pagina; W: number; H: number; speelt: boolean };

const volgendBeeld = () => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));

/** Eén bron per video-element in de audiograaf (Web Audio staat maar één koppeling per element toe). */
const audioBronnen = new WeakMap<HTMLVideoElement, MediaElementAudioSourceNode>();
let audioContext: AudioContext | null = null;

export function opnameFormaat(): { mime: string; extensie: 'webm' | 'mp4' } | null {
    if (typeof MediaRecorder === 'undefined' || !('captureStream' in HTMLCanvasElement.prototype)) {
        return null;
    }

    for (const mime of ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4;codecs=avc1,mp4a', 'video/mp4']) {
        if (MediaRecorder.isTypeSupported(mime)) {
            return { mime, extensie: mime.startsWith('video/mp4') ? 'mp4' : 'webm' };
        }
    }

    return null;
}

export function useAfdruk() {
    const [taak, setTaak] = useState<Taak | null>(null);
    const laagRef = useRef<Konva.Layer>(null);

    const teken = useCallback(async (t: Taak): Promise<Konva.Layer> => {
        await document.fonts.ready;
        setTaak(t);

        for (let i = 0; i < 60 && !laagRef.current; i++) {
            await volgendBeeld();
        }

        await volgendBeeld();
        laagRef.current!.batchDraw();

        return laagRef.current!;
    }, []);

    /** Pagina als canvas van precies W bij H pixels. */
    const maakCanvas = useCallback(
        async (pagina: Pagina, W: number, H: number): Promise<HTMLCanvasElement> => {
            await laadPagina(pagina);
            const laag = await teken({ pagina, W, H, speelt: false });
            const bron = laag.toCanvas({ x: 0, y: 0, width: W, height: H, pixelRatio: 1 });
            const doel = document.createElement('canvas');
            doel.width = W;
            doel.height = H;
            doel.getContext('2d')!.drawImage(bron, 0, 0, W, H);
            setTaak(null);

            return doel;
        },
        [teken],
    );

    /** Neem de pagina op als video: zo lang als de langste video, hooguit `maxSeconden`. */
    const neemOp = useCallback(
        async (pagina: Pagina, W: number, H: number, maxSeconden: number, opVoortgang: (deel: number) => void): Promise<{ blob: Blob; extensie: 'webm' | 'mp4' }> => {
            const formaat = opnameFormaat();

            if (!formaat) {
                throw new Error('Deze browser kan geen video opnemen. Gebruik Chrome, Edge of Firefox op een laptop.');
            }

            const videos = await laadPagina(pagina);

            if (videos.length === 0) {
                throw new Error('Er staat geen video in dit ontwerp.');
            }

            const duur = Math.min(maxSeconden, Math.max(...videos.map((v) => (Number.isFinite(v.duration) ? v.duration : maxSeconden))));
            const laag = await teken({ pagina, W, H, speelt: true });

            // Opnamecanvas op exact formaat; de laag van Konva kan een hogere pixeldichtheid hebben.
            const doek = document.createElement('canvas');
            doek.width = W;
            doek.height = H;
            const ctx = doek.getContext('2d')!;
            const bronDoek = laag.getNativeCanvasElement();
            const stroom = doek.captureStream(30);

            audioContext ??= new AudioContext();
            await audioContext.resume();
            const geluid = audioContext.createMediaStreamDestination();

            for (const v of videos) {
                let bron = audioBronnen.get(v);

                if (!bron) {
                    bron = audioContext.createMediaElementSource(v);
                    audioBronnen.set(v, bron);
                }

                bron.connect(geluid);
                v.pause();
                v.loop = false;
                v.muted = false;
                v.currentTime = 0;
            }

            geluid.stream.getAudioTracks().forEach((t) => stroom.addTrack(t));

            const recorder = new MediaRecorder(stroom, { mimeType: formaat.mime, videoBitsPerSecond: 8_000_000 });
            const stukken: Blob[] = [];
            recorder.ondataavailable = (e) => e.data.size > 0 && stukken.push(e.data);
            const klaar = new Promise<void>((r) => (recorder.onstop = () => r()));

            let doorgaan = true;
            const lus = () => {
                if (!doorgaan) {
                    return;
                }

                ctx.drawImage(bronDoek, 0, 0, W, H);
                requestAnimationFrame(lus);
            };
            lus();

            await Promise.all(videos.map((v) => v.play()));
            recorder.start(500);
            const begin = performance.now();

            await new Promise<void>((r) => {
                const tik = setInterval(() => {
                    const verstreken = (performance.now() - begin) / 1000;
                    opVoortgang(Math.min(1, verstreken / duur));

                    if (verstreken >= duur || videos.every((v) => v.ended)) {
                        clearInterval(tik);
                        r();
                    }
                }, 100);
            });

            recorder.stop();
            await klaar;
            doorgaan = false;

            for (const v of videos) {
                audioBronnen.get(v)?.disconnect();
                v.muted = true;
                v.loop = true;
                v.pause();
                v.currentTime = 0;
            }

            setTaak(null);

            return { blob: new Blob(stukken, { type: formaat.mime.split(';')[0] }), extensie: formaat.extensie };
        },
        [teken],
    );

    const element = taak ? (
        <div aria-hidden style={{ position: 'fixed', left: -100000, top: 0, pointerEvents: 'none' }}>
            <Stage width={taak.W} height={taak.H}>
                <Layer ref={laagRef} listening={false}>
                    <PaginaInhoud pagina={taak.pagina} W={taak.W} H={taak.H} modus="export" speelt={taak.speelt} />
                </Layer>
            </Stage>
        </div>
    ) : null;

    return { element, maakCanvas, neemOp };
}

export function naarBlob(canvas: HTMLCanvasElement, soort: 'png' | 'jpg', kwaliteit = 0.92): Promise<Blob> {
    return new Promise((klaar, fout) => canvas.toBlob((b) => (b ? klaar(b) : fout(new Error('Afbeelding maken mislukt.'))), soort === 'png' ? 'image/png' : 'image/jpeg', kwaliteit));
}

/** Kleine voorvertoning (JPEG, 360 breed) voor het overzicht. */
export function voorvertoning(canvas: HTMLCanvasElement): string {
    const breedte = 360;
    const klein = document.createElement('canvas');
    klein.width = breedte;
    klein.height = Math.round((canvas.height / canvas.width) * breedte);
    klein.getContext('2d')!.drawImage(canvas, 0, 0, klein.width, klein.height);

    return klein.toDataURL('image/jpeg', 0.8);
}
