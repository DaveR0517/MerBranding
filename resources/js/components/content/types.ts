/** Gedeelde types van de module content en koppelingen. */

export type KoppelingStatus =
    | 'niet_gekoppeld'
    | 'handmatig'
    | 'gekoppeld'
    | 'verlopen'
    | 'fout';

export type Account = {
    platform: string;
    naam: string;
    ingesteld: boolean;
    status: KoppelingStatus;
    handle: string | null;
    professioneel: boolean;
    toestemming: boolean;
    toestemming_op: string | null;
    toestemming_versie: string | null;
    verloopt_op: string | null;
    laatste_sync: string | null;
    waarschuwing: 'verloopt' | 'verlopen' | 'fout' | null;
};

export type Toestemmingstekst = {
    versie: string;
    wat: string[];
    niet: string[];
    met_wie: string;
    hoe_lang: string;
};

export type KalenderItem = {
    soort: 'bezoek' | 'publicatie' | 'live' | 'notitie';
    datum: string;
    tijd: string | null;
    titel: string;
    id?: number;
    samenwerking_id?: number;
    vestiging?: string;
    creator?: string | null;
    status?: string;
    omschrijving?: string | null;
    door?: string | null;
};

export type Kalender = {
    maand: string;
    label: string;
    vorige: string;
    volgende: string;
    items: KalenderItem[];
};

export type Idee = {
    id: number;
    title: string;
    description: string | null;
    status: 'idee' | 'gepland' | 'gebruikt';
    source: 'ondernemer' | 'team';
    created_at: string | null;
    collaboration: { id: number; title: string; number: string } | null;
};

export type Recht = {
    id: number;
    kind: 'organisch' | 'advertentie';
    starts_on: string;
    ends_on: string;
    days_left: number;
    status: 'actief' | 'verlopen' | 'ingetrokken';
    ad_code: string | null;
    is_proposal: boolean;
    extension_requested_at: string | null;
    collaboration: {
        id: number;
        title: string;
        number: string;
        creator: string | null;
    };
};

export type StandaardRechten = {
    organisch: number;
    advertentie: number;
    melden: number[];
};
