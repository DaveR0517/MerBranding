import { datum, datumTijd } from '@/lib/datum';
import type { SamenwerkingKort } from '@/types/samenwerking';

/** Wat de creator nu moet doen, per status. aanZet = de creator is aan de beurt. */
export function volgendeStap(k: SamenwerkingKort): {
    tekst: string;
    aanZet: boolean;
} {
    switch (k.status.value) {
        case 'creator_gevonden':
            return { tekst: 'Kies een moment voor je bezoek.', aanZet: true };
        case 'bezoek_gepland':
            return {
                tekst: `Bezoek op ${datumTijd(k.visit_at)}. Tik op de dag zelf op Ik ben er.`,
                aanZet: true,
            };
        case 'bezoek_geweest':
            return {
                tekst: 'Film je video en tik op Klaar met filmen.',
                aanZet: true,
            };
        case 'content_maken':
            return {
                tekst: 'Maak je video en lever hem aan voor de deadline.',
                aanZet: true,
            };
        case 'feedback':
            return {
                tekst: 'Je hebt feedback. Pas je video aan en lever opnieuw aan.',
                aanZet: true,
            };
        case 'ter_controle':
            return {
                tekst: 'Je video wordt gecontroleerd. Even wachten.',
                aanZet: false,
            };
        case 'goedgekeurd':
            return {
                tekst: `Plaats je video op ${datum(k.publish_on)} en koppel de link.`,
                aanZet: true,
            };
        case 'live':
            return {
                tekst: 'Je post staat live. Laat hem 14 dagen staan.',
                aanZet: false,
            };
        case 'afgerond':
            return { tekst: 'Klaar. Dank je wel!', aanZet: false };
        case 'geannuleerd':
            return { tekst: 'Deze klus gaat niet door.', aanZet: false };
        case 'niet_verschenen':
            return {
                tekst: 'Je was er niet op het afgesproken moment.',
                aanZet: false,
            };
        default:
            return { tekst: 'Wacht op het team.', aanZet: false };
    }
}
