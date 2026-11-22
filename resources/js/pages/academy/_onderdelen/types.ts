/** Gegevens zoals App\Http\Presenters\AcademyPresenter ze naar de schermen stuurt (student en team). */

export type Dag = {
    id: number;
    title: string;
    date: string;
    starts_at: string;
    ends_at: string;
    location_name: string;
    address: string | null;
    city: string;
    teacher_name: string | null;
    max_participants: number;
    taken: number;
    free: number;
    waitlist: number;
    price_cents: number;
    exam_required: boolean;
    status: 'open' | 'afgesloten' | 'geannuleerd';
    notes: string | null;
    is_today: boolean;
    is_past: boolean;
};

export type Criterium = { label: string; hint: string };

export type Inzending = {
    id: number;
    label: string | null;
    link: string | null;
    note: string | null;
    attempt: number;
    video_url: string | null;
    scores: Record<string, number> | null;
    feedback: string | null;
    assessor: string | null;
    assessed_at: string | null;
    created_at: string;
};

export type Examen = {
    id: number;
    status: 'open' | 'ingeleverd' | 'voldoende' | 'verbeteren' | 'onvoldoende';
    attempt: number;
    due_at: string | null;
    submitted_at: string | null;
    scores: Record<string, number> | null;
    average: number | null;
    feedback: string | null;
    assessor: string | null;
    assessed_at: string | null;
    videos: Inzending[];
};

export type Certificaat = { id: number; number: string; issued_at: string; check_url: string };

export type InschrijvingStatus = 'ingeschreven' | 'wachtlijst' | 'aanwezig' | 'afwezig' | 'geslaagd' | 'afgemeld';

export type Inschrijving = {
    id: number;
    status: InschrijvingStatus;
    status_name: string;
    place: number | null;
    paid: boolean;
    paid_at: string | null;
    offer_expires_at: string | null;
    checked_in_at: string | null;
    can_check_in: boolean;
    can_cancel: boolean;
    qr_svg: string | null;
    day: Dag;
    exam: Examen | null;
    certificate: Certificaat | null;
};
