/** Gegevens van de beheerschermen (BedrijfsbeheerPresenter). Gedeeld door ondernemer en team. */

export type Tijdvak = { weekday: number; starts_at: string; ends_at: string };

export type Vestiging = {
    id: number;
    name: string;
    address: string | null;
    city: string | null;
    experience: string | null;
    active: number;
    windows: Tijdvak[];
};

export type Gebruiker = {
    id: number;
    name: string;
    email: string;
    role: "eigenaar" | "lid";
    two_factor: boolean;
    invited_until: string | null;
};

export type Boeking = {
    id: number;
    date: string;
    kind:
        | "toekennen"
        | "reserveren"
        | "verbruiken"
        | "vrijgeven"
        | "correctie"
        | string;
    points: number;
    reason: string;
    collaboration: string | null;
    balance: number;
};

export type Abonnement = {
    plan_id: number;
    name: string;
    price_cents: number;
    points_per_month: number;
    is_proposal: boolean;
    starts_on: string;
    cancels_on: string | null;
    status: string;
};

export type Instellingen = {
    self_confirm_visits: boolean;
    owner_approves_content: boolean;
};

export type Verzoek = { id: number; title: string; created_at: string };

/** Een route uit Wayfinder: url en methode. */
export type Actie = {
    url: string;
    method: "get" | "post" | "put" | "patch" | "delete";
};

export const DAGEN = [
    "maandag",
    "dinsdag",
    "woensdag",
    "donderdag",
    "vrijdag",
    "zaterdag",
    "zondag",
];
