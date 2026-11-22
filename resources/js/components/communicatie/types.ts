import type { StatusInfo } from '@/types/samenwerking';

/** Gegevens van de module communicatie, zoals App\Http\Presenters\CommunicatiePresenter ze levert. */

export type Gesprekregel = {
    id: number;
    number: string;
    title: string;
    company: string;
    location: string;
    creator: string | null;
    status: StatusInfo;
    afgerond: boolean;
    last: { body: string; at: string; author: string | null } | null;
    unread: number;
    url: string;
};

export type Bericht = {
    id: number;
    body: string;
    at: string;
    mine: boolean;
    author: string;
    role: 'team' | 'ondernemer' | 'creator' | 'systeem';
};

export type Kanaal = {
    key: string;
    name: string;
    visibility: string;
    can_write: boolean;
    messages: Bericht[];
};

export type Gesprek = {
    id: number;
    number: string;
    title: string;
    company: string;
    location: string;
    creator: string | null;
    status: StatusInfo;
    url: string;
    post_url: string;
    channels: Kanaal[];
};

export type Omgeving = 'team' | 'ondernemer' | 'creator';

export type Artikel = {
    id: number;
    slug: string;
    title: string;
    category: string | null;
    html: string;
    body?: string;
    audiences?: string[];
    position?: number;
};

export type VraagKort = {
    id: number;
    number: string;
    subject: string;
    status: 'open' | 'beantwoord' | 'gesloten';
    status_label: string;
    priority: 'laag' | 'normaal' | 'hoog';
    portal: Omgeving;
    user: string | null;
    company: string | null;
    assignee: string | null;
    updated_at: string;
    created_at: string;
};

export type Vraag = VraagKort & {
    assigned_to: number | null;
    collaboration: { id: number; number: string; title: string } | null;
    replies: { id: number; body: string; internal: boolean; author: string; mine_side: boolean; at: string }[];
    respond_before?: string;
};
