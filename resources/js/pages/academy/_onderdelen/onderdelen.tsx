import type { ReactNode } from 'react';
import { euro } from '@/lib/datum';
import type { Criterium, Dag } from './types';

/** Datum van een cursusdag (JJJJ-MM-DD) als "zaterdag 24 oktober". */
export function dagDatum(iso: string, lang = true): string {
    return new Date(`${iso}T12:00:00`).toLocaleDateString('nl-NL', lang ? { weekday: 'long', day: 'numeric', month: 'long' } : { weekday: 'short', day: 'numeric', month: 'short' });
}

export function Plekken({ dag }: { dag: Dag }) {
    if (dag.status !== 'open') {
        return <span className="text-muted-foreground">{dag.status === 'afgesloten' ? 'Afgesloten' : 'Geannuleerd'}</span>;
    }

    return dag.free > 0 ? (
        <span className="font-semibold text-emerald-700 dark:text-emerald-400">nog {dag.free} {dag.free === 1 ? 'plek' : 'plekken'}</span>
    ) : (
        <span className="font-semibold text-rood">Vol, wachtlijst</span>
    );
}

/** Korte regel met datum, tijd, locatie en docent. */
export function DagRegel({ dag }: { dag: Dag }) {
    return (
        <>
            {dagDatum(dag.date)}, {dag.starts_at} - {dag.ends_at} · {dag.location_name}, {dag.city}
            {dag.teacher_name ? ` · met ${dag.teacher_name}` : ''}
        </>
    );
}

export function Prijs({ dag }: { dag: Dag }) {
    return (
        <span>
            {euro(dag.price_cents)} <span className="text-xs text-muted-foreground">incl. btw (voorstel)</span>
        </span>
    );
}

/** Uitslag per criterium, 1 tot 5. */
export function Uitslag({ criteria, scores }: { criteria: Criterium[]; scores: Record<string, number> }) {
    return (
        <dl className="grid grid-cols-2 gap-2 sm:grid-cols-3">
            {criteria.map((c) => (
                <div key={c.label} className={`rounded-xl border p-2.5 ${scores[c.label] === 1 ? 'border-rood/50 bg-rood/5' : 'bg-muted/40'}`}>
                    <dt className="text-xs font-semibold text-muted-foreground">{c.label}</dt>
                    <dd className="font-heading text-xl font-extrabold">{scores[c.label] ?? '-'}</dd>
                </div>
            ))}
        </dl>
    );
}

export function Beoordelingslijst({ criteria, onder }: { criteria: Criterium[]; onder?: ReactNode }) {
    return (
        <div>
            <ul className="space-y-1.5 text-sm">
                {criteria.map((c) => (
                    <li key={c.label} className="flex flex-wrap gap-x-2">
                        <span className="font-semibold">{c.label}</span>
                        {c.hint && <span className="text-muted-foreground">{c.hint}</span>}
                    </li>
                ))}
            </ul>
            {onder && <p className="mt-2 text-sm text-muted-foreground">{onder}</p>}
        </div>
    );
}

export function gemiddeld(getal: number | null): string {
    return getal === null ? '-' : getal.toLocaleString('nl-NL', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

/** Eén video: een bestand uploaden of een link (TikTok, Instagram, Drive) plakken. */
export function VideoOfLink({
    id,
    label,
    link,
    bestand,
    onLink,
    onBestand,
    fout,
}: {
    id: string;
    label: string;
    link: string;
    bestand: File | null;
    onLink: (v: string) => void;
    onBestand: (f: File | null) => void;
    fout?: string;
}) {
    return (
        <fieldset className="space-y-2 rounded-xl border p-3">
            <legend className="px-1 text-sm font-semibold">{label}</legend>
            <label htmlFor={`${id}-video`} className="block text-sm">Video uploaden (staand, 9:16)</label>
            <input
                id={`${id}-video`}
                type="file"
                accept="video/mp4,video/quicktime,video/webm"
                onChange={(e) => onBestand(e.target.files?.[0] ?? null)}
                className="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-violet/10 file:px-3 file:py-2 file:font-semibold file:text-violet"
            />
            {bestand && <p className="text-xs break-all text-muted-foreground">{bestand.name}</p>}
            <label htmlFor={`${id}-link`} className="block text-sm">of een link</label>
            <input id={`${id}-link`} type="url" inputMode="url" placeholder="https://" value={link} onChange={(e) => onLink(e.target.value)} className="w-full rounded-xl border-2 border-input bg-background px-3 py-2.5 text-base outline-none focus:border-violet" />
            {fout && <p className="text-sm text-rood">{fout}</p>}
        </fieldset>
    );
}

/** Ingediende video of link tonen. */
export function Ingediend({ label, link, video_url }: { label?: string | null; link: string | null; video_url: string | null }) {
    return (
        <div className="min-w-0 rounded-xl border p-3 text-sm">
            {label && <p className="font-semibold">{label}</p>}
            {video_url && <video src={video_url} controls preload="metadata" className="mt-1 aspect-[9/16] max-h-80 w-full rounded-lg bg-black object-contain" />}
            {link && <a href={link} target="_blank" rel="noreferrer" className="mt-1 block break-all text-violet underline">{link}</a>}
        </div>
    );
}
