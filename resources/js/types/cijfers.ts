/** Vormen zoals App\Services\Cijfers en de controllers van de module cijfers en geld ze leveren. */
export type Metriek =
    | "views"
    | "reach"
    | "likes"
    | "comments"
    | "shares"
    | "saves";
export type Bron = "handmatig" | "instagram" | "tiktok";

export type Meting = {
    id: number;
    value: number;
    source: Bron;
    fetched_at: string;
    is_estimate: boolean;
    reviewed: boolean;
};

export type PublicatieCijfers = {
    id: number;
    collaboration_id: number;
    platform: string;
    url: string;
    posted_at: string;
    metrics: Partial<Record<Metriek, Meting>>;
    sources: Bron[];
    fetched_at: string | null;
    is_estimate: boolean;
    unreviewed: boolean;
    interaction: number | null;
};

export type PlatformTotaal = {
    platform: string;
    posts: number;
    posts_met_cijfers: number;
    totals: Record<Metriek, number | null>;
    sources: Bron[];
    is_estimate: boolean;
    fetched_at: string | null;
};

export type Metrieken = Record<Metriek, string>;

export type Maandrapport = {
    maand: string;
    maand_label: string;
    lopend: boolean;
    bedrijf: { id: number; name: string };
    pakket: string | null;
    tellingen: {
        aangevraagd: number;
        live: number;
        afgerond: number;
        creators: number;
    };
    samenwerkingen: {
        id: number;
        number: string;
        title: string;
        location: string;
        creator: string | null;
        status: { value: string; label: string; number: number | null };
        live_at: string | null;
    }[];
    publicaties: (PublicatieCijfers & {
        title: string;
        creator: string | null;
    })[];
    platforms: PlatformTotaal[];
    tegoed: {
        toegekend: number;
        gereserveerd: number;
        verbruikt: number;
        vrijgegeven: number;
        correcties: number;
        saldo_einde: number;
        boekingen: {
            at: string;
            kind: string;
            points: number;
            reason: string;
        }[];
    };
    ongebruikt_tegoed: "meenemen" | "vervallen";
};

export type Maand = { value: string; label: string };
