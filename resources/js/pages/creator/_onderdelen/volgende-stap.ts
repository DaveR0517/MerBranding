import { datum, datumTijd } from '@/lib/datum';
import type { SamenwerkingKort } from '@/types/samenwerking';

/**
 * Wat de creator nu moet doen, per status, kort genoeg voor één regel op de telefoon.
 * aanZet = de creator is aan de beurt. Het klusscherm geeft de volledige uitleg.
 */
export function volgendeStap(k: SamenwerkingKort): {
    tekst: string;
    aanZet: boolean;
} {
    switch (k.status.value) {
        case 'creator_gevonden':
            return { tekst: 'Kies een moment voor je bezoek', aanZet: true };
        case 'bezoek_gepland':
            return { tekst: `Bezoek ${datumTijd(k.visit_at)}`, aanZet: true };
        case 'bezoek_geweest':
            return { tekst: 'Film en tik op Klaar met filmen', aanZet: true };
        case 'content_maken':
            return { tekst: 'Lever je video aan', aanZet: true };
        case 'feedback':
            return { tekst: 'Feedback: pas je video aan', aanZet: true };
        case 'ter_controle':
            return {
                tekst: 'Wordt gecontroleerd, even wachten',
                aanZet: false,
            };
        case 'goedgekeurd':
            return {
                tekst: `Plaatsen op ${datum(k.publish_on)}`,
                aanZet: true,
            };
        case 'live':
            return {
                tekst: 'Live, laat je post 14 dagen staan',
                aanZet: false,
            };
        case 'afgerond':
            return { tekst: 'Afgerond, dank je wel', aanZet: false };
        case 'geannuleerd':
            return { tekst: 'Gaat niet door', aanZet: false };
        case 'niet_verschenen':
            return { tekst: 'Niet verschenen', aanZet: false };
        default:
            return { tekst: 'Wacht op het team', aanZet: false };
    }
}
