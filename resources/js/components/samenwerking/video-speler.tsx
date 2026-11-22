import { useImperativeHandle, useRef } from 'react';
import type { Ref } from 'react';
import type { FeedbackPunt, Versie } from '@/types/samenwerking';

export type VideoSpelerHandle = { tijd: () => number; naar: (s: number) => void };

export function tijdcode(seconden: number | null): string {
    if (seconden === null) {
        return 'algemeen';
    }

    return `${Math.floor(seconden / 60)}:${String(seconden % 60).padStart(2, '0')}`;
}

/**
 * Videospeler met feedback op tijdcode. Met watermark=true (ondernemer kijkt mee voor goedkeuring)
 * ligt er een zichtbaar watermerk over het beeld en is downloaden uit. Het echte watermerk in het
 * videobestand komt met Bunny Stream (fase 2, opslag).
 */
export function VideoSpeler({
    versie,
    ref,
}: {
    versie: Versie;
    ref?: Ref<VideoSpelerHandle>;
}) {
    const video = useRef<HTMLVideoElement>(null);

    useImperativeHandle(ref, () => ({
        tijd: () => Math.floor(video.current?.currentTime ?? 0),
        naar: (s: number) => {
            if (video.current) {
                video.current.currentTime = s;
                void video.current.play();
            }
        },
    }));

    return (
        <div className="space-y-3">
            <div className="relative mx-auto aspect-[9/16] max-h-[70vh] overflow-hidden rounded-xl bg-black">
                <video
                    ref={video}
                    src={versie.url}
                    controls
                    playsInline
                    preload="metadata"
                    controlsList={versie.watermark ? 'nodownload' : undefined}
                    onContextMenu={versie.watermark ? (e) => e.preventDefault() : undefined}
                    className="size-full object-contain"
                    data-test="video"
                />
                {versie.watermark && (
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-0 grid place-items-center"
                    >
                        <span className="-rotate-30 font-heading text-3xl font-extrabold tracking-widest text-white/35 select-none">
                            VOORBEELD
                            <br />
                            MEBRANDING
                        </span>
                    </div>
                )}
            </div>
            {versie.watermark && (
                <p className="text-xs text-muted-foreground">
                    Voorbeeld met watermerk. Na goedkeuring zie je de video
                    zonder watermerk en kun je hem downloaden.
                </p>
            )}
            {versie.download_url && (
                <a
                    href={versie.download_url}
                    className="inline-flex text-sm font-semibold text-violet underline"
                >
                    Video downloaden
                </a>
            )}
        </div>
    );
}

export function FeedbackLijst({
    punten,
    onKlik,
}: {
    punten: FeedbackPunt[];
    onKlik?: (s: number) => void;
}) {
    if (punten.length === 0) {
        return null;
    }

    return (
        <ul className="space-y-2">
            {punten.map((p) => (
                <li key={p.id} className="rounded-lg border bg-card p-3 text-sm">
                    <button
                        type="button"
                        disabled={p.timecode === null || !onKlik}
                        onClick={() => p.timecode !== null && onKlik?.(p.timecode)}
                        className="mr-2 rounded bg-violet/10 px-1.5 py-0.5 font-mono text-xs font-semibold text-violet"
                    >
                        {tijdcode(p.timecode)}
                    </button>
                    {p.body}
                    <span className="mt-1 block text-xs text-muted-foreground">
                        {p.author}
                        {p.from_owner ? ' (ondernemer, alleen voor het team)' : ''}
                    </span>
                </li>
            ))}
        </ul>
    );
}
