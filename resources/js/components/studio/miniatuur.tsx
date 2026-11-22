import { useEffect, useRef, useState } from 'react';
import { Layer, Stage } from 'react-konva';
import { cn } from '@/lib/utils';
import { PaginaInhoud } from './canvas';
import type { OntwerpItem, Pagina } from './typen';
import { STATUS_LABEL } from './typen';

/** Live getekende pagina op elke breedte (vult de ouder). */
export function LivePagina({ pagina, W, H, className }: { pagina: Pagina; W: number; H: number; className?: string }) {
    const ref = useRef<HTMLDivElement>(null);
    const [breedte, setBreedte] = useState(0);

    useEffect(() => {
        const el = ref.current;

        if (!el) {
            return;
        }

        const ro = new ResizeObserver(() => setBreedte(el.clientWidth));
        ro.observe(el);

        return () => ro.disconnect();
    }, []);

    const schaal = breedte / W;

    return (
        <div ref={ref} className={cn('w-full', className)} style={{ aspectRatio: `${W} / ${H}` }}>
            {breedte > 0 && (
                <Stage width={breedte} height={H * schaal} scaleX={schaal} scaleY={schaal} listening={false}>
                    <Layer listening={false}>
                        <PaginaInhoud pagina={pagina} W={W} H={H} modus="weergave" />
                    </Layer>
                </Stage>
            )}
        </div>
    );
}

/**
 * Miniatuur van een ontwerp of sjabloon: opgeslagen voorvertoning, of live getekend als die er niet is.
 * Altijd in een vak van 4:5, zodat een raster gelijke rijen houdt.
 */
export function Miniatuur({ item, className, vullen = false }: { item: OntwerpItem; className?: string; vullen?: boolean }) {
    const verhouding = item.width / item.height;
    // Passend (heel ontwerp zichtbaar) of vullend (zoals het raster van een Instagram-profiel bijsnijdt).
    const breedte = (vullen ? Math.max(1, verhouding / 0.8) : Math.min(1, verhouding / 0.8)) * 100;

    return (
        <div className={cn('flex aspect-[4/5] items-center justify-center overflow-hidden rounded-lg bg-muted', className)}>
            <div className={cn('shrink-0 overflow-hidden', !vullen && 'shadow-sm')} style={{ width: `${breedte}%`, aspectRatio: `${item.width} / ${item.height}` }}>
                {item.preview ? (
                    <img src={item.preview} alt="" loading="lazy" className="size-full" />
                ) : item.pages?.[0] ? (
                    <LivePagina pagina={item.pages[0]} W={item.width} H={item.height} />
                ) : null}
            </div>
        </div>
    );
}

const STATUS_KLEUR: Record<string, string> = {
    concept: 'bg-zinc-200 text-zinc-700',
    klaar: 'bg-emerald-50 text-emerald-700',
    ingepland: 'bg-violet/10 text-violet',
    geplaatst: 'bg-emerald-100 text-emerald-800',
};

/** Status van een post, in dezelfde vorm als StatusBadge van de keten. */
export function StudioStatus({ status }: { status: string | null | undefined }) {
    if (!status) {
        return null;
    }

    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap', STATUS_KLEUR[status] ?? 'bg-violet/10 text-violet')}>
            {STATUS_LABEL[status] ?? status}
        </span>
    );
}
