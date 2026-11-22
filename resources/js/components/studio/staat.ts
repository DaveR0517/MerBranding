/**
 * Staat van een ontwerp in de editor: pagina's met lagen, plus ongedaan maken en opnieuw.
 * Kleine wijzigingen kort na elkaar aan dezelfde eigenschap (typen, schuiven) tellen als één stap.
 */
import { useCallback, useRef, useState } from 'react';
import type { Laag, Pagina } from './typen';
import { nieuwId } from './typen';

const MAX_STAPPEN = 60;

export function useOntwerpStaat(begin: Pagina[]) {
    const [stand, setStand] = useState<{ stappen: Pagina[][]; plek: number }>({ stappen: [begin], plek: 0 });
    const laatste = useRef<{ sleutel: string; tijd: number } | null>(null);
    const [gewijzigd, setGewijzigd] = useState(false);
    const paginas = stand.stappen[stand.plek];

    const zet = useCallback((maak: (p: Pagina[]) => Pagina[], sleutel?: string) => {
        setStand((s) => {
            const nu = s.stappen[s.plek];
            const nieuw = maak(nu);

            if (nieuw === nu) {
                return s;
            }

            const tijd = Date.now();
            const samenvoegen = sleutel && laatste.current?.sleutel === sleutel && tijd - laatste.current.tijd < 800;
            laatste.current = sleutel ? { sleutel, tijd } : null;
            const basis = s.stappen.slice(0, samenvoegen ? s.plek : s.plek + 1);
            const stappen = [...basis, nieuw].slice(-MAX_STAPPEN);

            return { stappen, plek: stappen.length - 1 };
        });
        setGewijzigd(true);
    }, []);

    const wijzigLaag = useCallback(
        (pagina: number, id: string, patch: Partial<Laag>, sleutel?: string) =>
            zet(
                (p) => p.map((pg, i) => (i === pagina ? { ...pg, lagen: pg.lagen.map((l) => (l.id === id ? { ...l, ...patch } : l)) } : pg)),
                sleutel ? `${id}:${sleutel}` : undefined,
            ),
        [zet],
    );

    const wijzigPagina = useCallback(
        (pagina: number, patch: Partial<Pagina>, sleutel?: string) => zet((p) => p.map((pg, i) => (i === pagina ? { ...pg, ...patch } : pg)), sleutel),
        [zet],
    );

    const voegToe = useCallback((pagina: number, laag: Laag) => zet((p) => p.map((pg, i) => (i === pagina ? { ...pg, lagen: [...pg.lagen, laag] } : pg))), [zet]);

    const verwijder = useCallback((pagina: number, id: string) => zet((p) => p.map((pg, i) => (i === pagina ? { ...pg, lagen: pg.lagen.filter((l) => l.id !== id) } : pg))), [zet]);

    const dupliceer = useCallback(
        (pagina: number, id: string): string => {
            const kopieId = nieuwId('kopie');
            zet((p) =>
                p.map((pg, i) => {
                    if (i !== pagina) {
                        return pg;
                    }

                    const plek = pg.lagen.findIndex((l) => l.id === id);
                    const kopie = { ...pg.lagen[plek], id: kopieId, x: pg.lagen[plek].x + 30, y: pg.lagen[plek].y + 30, naam: `${pg.lagen[plek].naam} (kopie)` };

                    return { ...pg, lagen: [...pg.lagen.slice(0, plek + 1), kopie, ...pg.lagen.slice(plek + 1)] };
                }),
            );

            return kopieId;
        },
        [zet],
    );

    /** Volgorde: +1 is een laag naar voren (boven), -1 naar achteren. */
    const verschuif = useCallback(
        (pagina: number, id: string, richting: 1 | -1) =>
            zet((p) =>
                p.map((pg, i) => {
                    if (i !== pagina) {
                        return pg;
                    }

                    const lagen = [...pg.lagen];
                    const van = lagen.findIndex((l) => l.id === id);
                    const naar = van + richting;

                    if (naar < 0 || naar >= lagen.length) {
                        return pg;
                    }

                    [lagen[van], lagen[naar]] = [lagen[naar], lagen[van]];

                    return { ...pg, lagen };
                }),
            ),
        [zet],
    );

    const paginaErbij = useCallback(
        (na: number, kopie: boolean) =>
            zet((p) => {
                const nieuw: Pagina = kopie
                    ? { ...p[na], lagen: p[na].lagen.map((l) => ({ ...l, id: nieuwId(l.soort) })) }
                    : { achtergrond: p[na]?.achtergrond ?? '#FFFFFF', lagen: [] };

                return [...p.slice(0, na + 1), nieuw, ...p.slice(na + 1)];
            }),
        [zet],
    );

    const paginaWeg = useCallback((index: number) => zet((p) => (p.length > 1 ? p.filter((_, i) => i !== index) : p)), [zet]);

    /** Alle pagina's in één stap aanpassen (bijvoorbeeld herstel door de merkcontrole). */
    const vervang = useCallback((maak: (p: Pagina[]) => Pagina[]) => zet(maak), [zet]);

    const ongedaan = useCallback(() => setStand((s) => ({ ...s, plek: Math.max(0, s.plek - 1) })), []);
    const opnieuw = useCallback(() => setStand((s) => ({ ...s, plek: Math.min(s.stappen.length - 1, s.plek + 1) })), []);

    return {
        paginas,
        wijzigLaag,
        wijzigPagina,
        voegToe,
        verwijder,
        dupliceer,
        verschuif,
        paginaErbij,
        paginaWeg,
        vervang,
        ongedaan,
        opnieuw,
        kanOngedaan: stand.plek > 0,
        kanOpnieuw: stand.plek < stand.stappen.length - 1,
        gewijzigd,
        setGewijzigd,
    };
}

export type OntwerpStaat = ReturnType<typeof useOntwerpStaat>;
