/**
 * Tekent één pagina van een ontwerp met react-konva. Dezelfde componenten dienen voor de editor (slepen,
 * kiezen), voor miniaturen (alleen kijken) en voor de export op ware grootte. Eén bron voor hoe een laag eruitziet.
 */
import Konva from 'konva';
import { useEffect, useRef } from 'react';
import { Ellipse, Group, Image as KImage, Rect, Text } from 'react-konva';
import { weergaveTekst } from './merk';
import { useAfbeelding, useVideo } from './media';
import type { Laag, Pagina } from './typen';
import { bronUrl } from './typen';

export type Modus = 'editor' | 'weergave' | 'export';

type Context = Konva.Context;

/** Pad van een rechthoek met afgeronde hoeken (ook voor de uitsnede van een vak). */
function afgerond(ctx: Context, w: number, h: number, r: number) {
    const s = Math.max(0, Math.min(r, w / 2, h / 2));
    ctx.beginPath();
    ctx.moveTo(s, 0);
    ctx.arcTo(w, 0, w, h, s);
    ctx.arcTo(w, h, 0, h, s);
    ctx.arcTo(0, h, 0, 0, s);
    ctx.arcTo(0, 0, w, 0, s);
    ctx.closePath();
}

/** Punten van een lineair verloop met een CSS-hoek (135 graden: van linksboven naar rechtsonder). */
export function verloopPunten(w: number, h: number, hoek: number) {
    const a = (hoek * Math.PI) / 180;
    const dx = Math.sin(a);
    const dy = -Math.cos(a);
    const lengte = Math.abs(w * dx) + Math.abs(h * dy);

    return {
        start: { x: w / 2 - (dx * lengte) / 2, y: h / 2 - (dy * lengte) / 2 },
        eind: { x: w / 2 + (dx * lengte) / 2, y: h / 2 + (dy * lengte) / 2 },
    };
}

/** Media vult het vak ("cover"); x en y van de uitsnede is het punt van de media in het midden van het vak. */
export function dekking(w: number, h: number, mw: number, mh: number, u: { x: number; y: number; zoom: number }) {
    const schaal = Math.max(w / mw, h / mh) * Math.max(1, u.zoom);
    const dw = mw * schaal;
    const dh = mh * schaal;
    const klem = (v: number, min: number) => Math.min(0, Math.max(min, v));

    return { dw, dh, x: klem(w / 2 - u.x * dw, w - dw), y: klem(h / 2 - u.y * dh, h - dh) };
}

type Handlers = {
    onKies?: (id: string, laag: Laag) => void;
    onWijzig?: (id: string, patch: Partial<Laag>, sleutel?: string) => void;
    onSleepStart?: (node: Konva.Node) => void;
    onSleep?: (node: Konva.Node) => void;
    onSleepEind?: () => void;
    onDubbel?: (laag: Laag) => void;
    /** Muis boven een gevuld vak (de editor toont dan de hint om te verschuiven). */
    onBovenVak?: (laag: Laag | null) => void;
};

/** Vak uit een sjabloon of merkbasis: het kader staat vast, alleen de inhoud verschuift en zoomt. */
export function isVastVak(l: Laag): boolean {
    return !!l.vak && (l.soort === 'foto' || l.soort === 'video') && l.vergrendeld;
}

function MediaVak({ laag, modus, uitsnedeModus, onWijzig, onBovenVak }: { laag: Laag; modus: Modus; uitsnedeModus: boolean } & Handlers) {
    const url = bronUrl(laag.bron);
    const img = useAfbeelding(laag.soort === 'foto' ? url : null);
    const vid = useVideo(laag.soort === 'video' ? url : null);
    const media = laag.soort === 'video' ? vid : img;
    const w = laag.breedte;
    const h = laag.hoogte;
    const r = laag.hoekafronding ?? 0;
    const u = laag.uitsnede ?? { x: 0.5, y: 0.5, zoom: 1 };

    if (!media) {
        if (modus === 'export') {
            return null;
        }

        const label = laag.vakNaam || (laag.soort === 'video' ? 'Videovak' : 'Fotovak');

        return (
            <Group clipFunc={(ctx) => afgerond(ctx, w, h, r)}>
                <Rect width={w} height={h} fill="#E4E1F0" />
                <Rect width={w} height={h} stroke="#6D6794" strokeWidth={Math.max(2, Math.min(w, h) * 0.008)} dash={[18, 12]} cornerRadius={r} />
                <Text
                    width={w}
                    height={h}
                    align="center"
                    verticalAlign="middle"
                    padding={16}
                    text={`${laag.soort === 'video' ? '▶ ' : ''}${label}`}
                    fontFamily="Montserrat Variable"
                    fontStyle="700"
                    fontSize={Math.max(18, Math.min(w, h) * 0.07)}
                    fill="#6D6794"
                />
            </Group>
        );
    }

    const mw = media instanceof HTMLVideoElement ? media.videoWidth : media.naturalWidth;
    const mh = media instanceof HTMLVideoElement ? media.videoHeight : media.naturalHeight;
    const d = dekking(w, h, mw || w, mh || h, u);
    // Een vast vak verschuift de inhoud direct bij slepen; een losse foto alleen in de modus "verschuiven".
    const inhoudSleepbaar = modus === 'editor' && (isVastVak(laag) || uitsnedeModus);
    const zetCursor = (e: Konva.KonvaEventObject<MouseEvent>, cursor: string) => {
        const c = e.target.getStage()?.container();

        if (c) {
            c.style.cursor = cursor;
        }
    };

    return (
        <Group clipFunc={(ctx) => afgerond(ctx, w, h, r)}>
            <KImage
                image={media}
                x={d.x}
                y={d.y}
                width={d.dw}
                height={d.dh}
                draggable={inhoudSleepbaar}
                onMouseEnter={(e) => {
                    if (inhoudSleepbaar) {
                        zetCursor(e, 'move');
                        onBovenVak?.(laag);
                    }
                }}
                onMouseLeave={(e) => {
                    zetCursor(e, '');
                    onBovenVak?.(null);
                }}
                onWheel={(e) => {
                    if (!inhoudSleepbaar) {
                        return;
                    }

                    // Zoomen met muiswiel of knijpen op het touchpad, minimaal 100% (vult het vak).
                    e.evt.preventDefault();
                    e.cancelBubble = true;
                    const zoom = Math.min(4, Math.max(1, u.zoom * (e.evt.deltaY < 0 ? 1.06 : 1 / 1.06)));
                    onWijzig?.(laag.id, { uitsnede: { ...u, zoom } }, 'zoom');
                }}
                onDragStart={(e) => {
                    e.cancelBubble = true;
                }}
                onDragMove={(e) => {
                    // Begrensd op de uitsnede: er ontstaat nooit een lege rand in het vak.
                    e.cancelBubble = true;
                    e.target.x(Math.min(0, Math.max(w - d.dw, e.target.x())));
                    e.target.y(Math.min(0, Math.max(h - d.dh, e.target.y())));
                }}
                onDragEnd={(e) => {
                    e.cancelBubble = true;
                    onWijzig?.(laag.id, { uitsnede: { ...u, x: (w / 2 - e.target.x()) / d.dw, y: (h / 2 - e.target.y()) / d.dh } });
                }}
            />
        </Group>
    );
}

function Logo({ laag }: { laag: Laag }) {
    const img = useAfbeelding(bronUrl(laag.bron));

    return img ? <KImage image={img} width={laag.breedte} height={laag.hoogte} /> : null;
}

function Inhoud({ laag, modus, uitsnedeModus, ...h }: { laag: Laag; modus: Modus; uitsnedeModus: boolean } & Handlers) {
    switch (laag.soort) {
        case 'tekst':
            return (
                <Text
                    width={laag.breedte}
                    text={weergaveTekst(laag)}
                    fontFamily={laag.lettertype ?? 'Montserrat Variable'}
                    fontStyle={String(laag.gewicht ?? 400)}
                    fontSize={laag.grootte ?? 48}
                    fill={laag.kleur ?? '#3A3659'}
                    align={laag.uitlijning ?? 'left'}
                    lineHeight={laag.regelafstand ?? 1.2}
                />
            );
        case 'vorm':
            return laag.vorm === 'cirkel' ? (
                <Ellipse x={laag.breedte / 2} y={laag.hoogte / 2} radiusX={laag.breedte / 2} radiusY={laag.hoogte / 2} fill={laag.vulling} />
            ) : (
                <Rect width={laag.breedte} height={laag.hoogte} fill={laag.vulling} cornerRadius={laag.hoekafronding ?? 0} />
            );
        case 'verloop': {
            const p = verloopPunten(laag.breedte, laag.hoogte, laag.hoek ?? 135);

            return (
                <Rect
                    width={laag.breedte}
                    height={laag.hoogte}
                    cornerRadius={laag.hoekafronding ?? 0}
                    fillLinearGradientStartPoint={p.start}
                    fillLinearGradientEndPoint={p.eind}
                    fillLinearGradientColorStops={[0, laag.van ?? '#D9303E', 1, laag.naar ?? '#614ED9']}
                />
            );
        }
        case 'logo':
            return <Logo laag={laag} />;
        case 'foto':
        case 'video':
            return <MediaVak laag={laag} modus={modus} uitsnedeModus={uitsnedeModus} {...h} />;
    }
}

/** Herteken de laag zolang er video's spelen. */
function useVideoAnimatie(ref: React.RefObject<Konva.Group | null>, actief: boolean) {
    useEffect(() => {
        const laag = ref.current?.getLayer();

        if (!actief || !laag) {
            return;
        }

        const anim = new Konva.Animation(() => undefined, laag);
        anim.start();

        return () => {
            anim.stop();
        };
    }, [ref, actief]);
}

export function PaginaInhoud({
    pagina,
    W,
    H,
    modus,
    selectie = null,
    uitsnedeModus = false,
    kaderBewerken = null,
    speelt = false,
    ...handlers
}: {
    pagina: Pagina;
    W: number;
    H: number;
    modus: Modus;
    selectie?: string | null;
    uitsnedeModus?: boolean;
    /** Super Admin past het kader van deze (vergrendelde) laag aan. */
    kaderBewerken?: string | null;
    /** Video's spelen (editor: stil, export: met geluid; de opname bepaalt dat). */
    speelt?: boolean;
} & Handlers) {
    const ref = useRef<Konva.Group>(null);
    const heeftVideo = pagina.lagen.some((l) => l.soort === 'video' && l.bron && l.zichtbaar);
    useVideoAnimatie(ref, speelt && heeftVideo);

    const interactief = modus === 'editor';

    return (
        <Group ref={ref}>
            <Rect name="achtergrond" width={W} height={H} fill={pagina.achtergrond || '#FFFFFF'} listening={interactief} />
            {pagina.lagen.map((laag) => {
                const kader = kaderBewerken === laag.id;
                // Alleen events van de laag zelf; die van de inhoud van een vak (de foto) niet.
                const eigen = (e: Konva.KonvaEventObject<Event>) => e.target === e.currentTarget;

                return (
                    <Group
                        key={laag.id}
                        id={laag.id}
                        name="laag"
                        x={laag.x}
                        y={laag.y}
                        rotation={laag.rotatie}
                        opacity={laag.dekking ?? 1}
                        visible={laag.zichtbaar}
                        // Vergrendeld: niet te kiezen op het canvas (wel in het lagenpaneel). Een vast vak wel: je verschuift er de inhoud.
                        listening={interactief && (!laag.vergrendeld || isVastVak(laag) || kader)}
                        draggable={interactief && (kader || (!laag.vergrendeld && !(uitsnedeModus && selectie === laag.id)))}
                        onMouseDown={(e) => {
                            if (!interactief) {
                                return;
                            }

                            e.cancelBubble = true;
                            handlers.onKies?.(laag.id, laag);
                        }}
                        onTap={(e) => {
                            e.cancelBubble = true;
                            handlers.onKies?.(laag.id, laag);
                        }}
                        onDblClick={() => handlers.onDubbel?.(laag)}
                        onDblTap={() => handlers.onDubbel?.(laag)}
                        onDragStart={(e) => eigen(e) && handlers.onSleepStart?.(e.target)}
                        onDragMove={(e) => eigen(e) && handlers.onSleep?.(e.target)}
                        onDragEnd={(e) => {
                            if (!eigen(e)) {
                                return;
                            }

                            handlers.onSleepEind?.();
                            handlers.onWijzig?.(laag.id, { x: Math.round(e.target.x()), y: Math.round(e.target.y()) });
                        }}
                        onTransformEnd={(e) => {
                            const n = e.target;
                            const sx = n.scaleX();
                            const sy = n.scaleY();
                            n.scale({ x: 1, y: 1 });
                            const patch: Partial<Laag> = {
                                x: Math.round(n.x()),
                                y: Math.round(n.y()),
                                rotatie: Math.round(n.rotation() * 10) / 10,
                                breedte: Math.max(10, Math.round(laag.breedte * sx)),
                                hoogte: Math.max(10, Math.round(laag.hoogte * sy)),
                            };

                            // Tekst op een merkstijl houdt zijn grootte; alleen vrije tekst (Super Admin) schaalt mee.
                            if (laag.soort === 'tekst' && (!laag.stijl || laag.vrij) && Math.abs(sy - 1) > 0.01) {
                                patch.grootte = Math.max(8, Math.round((laag.grootte ?? 48) * sy));
                            }

                            handlers.onWijzig?.(laag.id, patch);
                        }}
                    >
                        <Inhoud laag={laag} modus={modus} uitsnedeModus={uitsnedeModus && selectie === laag.id} {...handlers} />
                    </Group>
                );
            })}
        </Group>
    );
}
