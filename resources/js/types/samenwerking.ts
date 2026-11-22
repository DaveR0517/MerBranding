/** Vormen zoals App\Http\Presenters\SamenwerkingPresenter ze levert. */
export type StatusInfo = { value: string; label: string; number: number | null };

export type SamenwerkingKort = {
    id: number;
    number: string;
    title: string;
    status: StatusInfo;
    company: string;
    location: string;
    creator: string | null;
    creator_level: string | null;
    visit_at: string | null;
    publish_on: string | null;
    points?: number;
    updated_at: string;
};

export type FeedbackPunt = {
    id: number;
    timecode: number | null;
    body: string;
    author: string;
    from_owner: boolean;
};

export type Versie = {
    id: number;
    version: number;
    url: string;
    download_url: string | null;
    watermark: boolean;
    caption: string;
    label_check: {
        geslaagd: boolean;
        bijschrift: boolean;
        platformlabel: boolean;
        tekst_in_beeld: boolean;
        uitleg: string;
    };
    outcome: 'feedback' | 'goedgekeurd' | null;
    submitted_at: string;
    feedback: FeedbackPunt[];
};

export type Stap = { number: number; label: string; done: boolean; current: boolean };

export type SamenwerkingVol = SamenwerkingKort & {
    briefing: string;
    alcohol: boolean;
    period_from: string | null;
    period_to: string | null;
    location_address: string;
    experience: string | null;
    visit_windows: { weekday: number; starts_at: string; ends_at: string; label: string }[];
    visited_at: string | null;
    content_deadline_at: string | null;
    live_at: string | null;
    measure_until: string | null;
    cancel_reason: string | null;
    reward: string;
    fee_cents: number;
    versions: Versie[];
    publications: { platform: string; url: string; posted_at: string }[];
    review: { rating: number; body: string | null } | null;
    steps: Stap[];
    payout?: { amount_cents: number; status: string; run_on: string } | null;
    invitations?: { id: number; creator_id: number; creator: string; answer: string | null; expires_at: string; open: boolean }[];
    log?: { at: string; user: string; event: string; from: string | null; to: string | null; reason: string | null; data: Record<string, unknown> | null }[];
    ledger?: { at: string; kind: string; points: number; reason: string }[];
    company_id?: number;
};
