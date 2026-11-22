/** Gedeelde typen en namen voor de screeningschermen. */
export type Kanaal = {
    platform: string;
    handle: string;
    followers: string | number | null;
    business: boolean;
};
export type Aanmelding = {
    id: number;
    name: string;
    email: string;
    city: string | null;
    status: string;
    kind: string | null;
    channels: Kanaal[];
    created_at: string | null;
    due_at: string | null;
    overdue: boolean;
};

export const soortNaam: Record<string, string> = {
    influencer: "Influencer",
    ugc: "UGC",
    beide: "Beide",
};
export const platformKort: Record<string, string> = {
    tiktok: "TT",
    instagram: "IG",
    youtube: "YT",
    facebook: "FB",
    snapchat: "SC",
};
