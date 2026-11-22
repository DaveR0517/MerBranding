/** Gedeelde onderdelen van de module eigen kanalen (overzicht, posts en filmpjes, berichten). */
import { Link } from '@inertiajs/react';
import { Instagram, Music2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Etiket } from '@/components/content/labels';
import type { Kleur } from '@/components/content/labels';
import { cn } from '@/lib/utils';
import kanalen from '@/routes/team/kanalen';

export type PlatformSleutel = 'instagram' | 'tiktok';

export const PLATFORMS: Record<PlatformSleutel, { naam: string; icoon: LucideIcon }> = {
    instagram: { naam: 'Instagram', icoon: Instagram },
    tiktok: { naam: 'TikTok', icoon: Music2 },
};

export const KANAAL_STATUS: Record<string, { naam: string; kleur: Kleur }> = {
    niet_gekoppeld: { naam: 'Niet gekoppeld', kleur: 'grijs' },
    gekoppeld: { naam: 'Gekoppeld', kleur: 'groen' },
    verloopt: { naam: 'Verloopt binnenkort', kleur: 'amber' },
    verlopen: { naam: 'Verlopen', kleur: 'rood' },
    fout: { naam: 'Fout, opnieuw koppelen', kleur: 'rood' },
};

export function KanaalStatus({ status }: { status: string }) {
    const s = KANAAL_STATUS[status] ?? KANAAL_STATUS.niet_gekoppeld;

    return <Etiket kleur={s.kleur}>{s.naam}</Etiket>;
}

/** Getal kort en Nederlands: 1.234 of vanaf 100.000 kort (bijvoorbeeld 109K). Een streepje als er (nog) geen waarde is. */
export function getal(n: number | null | undefined): string {
    if (n === null || n === undefined) {
        return '-';
    }

    return n >= 100000 ? new Intl.NumberFormat('nl-NL', { notation: 'compact', maximumFractionDigits: 1 }).format(n) : n.toLocaleString('nl-NL');
}

/** Tabbladen tussen de drie schermen (links, zodat elk scherm een eigen adres heeft). */
export function KanalenTabs({ actief, ongelezen = 0 }: { actief: 'overzicht' | 'posts' | 'berichten'; ongelezen?: number }) {
    const tabs = [
        { key: 'overzicht', label: 'Overzicht', href: kanalen.index.url() },
        { key: 'posts', label: 'Posts en filmpjes', href: kanalen.posts.url() },
        { key: 'berichten', label: 'Berichten', href: kanalen.berichten.url(), aantal: ongelezen },
    ];

    return (
        <nav aria-label="Eigen kanalen" className="-mx-4 flex gap-1 overflow-x-auto border-b px-4 md:mx-0 md:px-0">
            {tabs.map((t) => (
                <Link
                    key={t.key}
                    href={t.href}
                    aria-current={t.key === actief ? 'page' : undefined}
                    className={cn(
                        '-mb-px shrink-0 border-b-2 px-3 py-2 text-sm font-semibold whitespace-nowrap',
                        t.key === actief ? 'border-violet text-violet' : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {t.label}
                    {!!t.aantal && <span className="ml-1.5 rounded-full bg-violet px-1.5 text-xs text-white tabular-nums">{t.aantal}</span>}
                </Link>
            ))}
        </nav>
    );
}

/**
 * Weergaven per dag als staafjes (één reeks, één kleur). Hover of focus op een staaf toont dag en waarde;
 * de tabel eronder is er voor schermlezers.
 */
export function Staafjes({ reeks, label }: { reeks: { datum: string; waarde: number }[]; label: string }) {
    const max = Math.max(1, ...reeks.map((r) => r.waarde));
    const dag = (iso: string) => new Date(iso).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short' });

    if (reeks.length === 0) {
        return <p className="py-6 text-center text-sm text-muted-foreground">Nog geen verloop. Na een paar dagen ophalen staat het hier.</p>;
    }

    return (
        <figure className="space-y-1">
            <div className="flex h-24 items-end gap-0.5 border-b border-border" aria-hidden>
                {reeks.map((r) => (
                    <div key={r.datum} className="group relative flex h-full flex-1 items-end">
                        <div className="w-full rounded-t-[4px] bg-violet/80 group-hover:bg-violet" style={{ height: `${Math.max(2, (r.waarde / max) * 100)}%` }} />
                        <span className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap text-popover-foreground shadow group-hover:block">
                            {dag(r.datum)}: <strong className="tabular-nums">{r.waarde.toLocaleString('nl-NL')}</strong>
                        </span>
                    </div>
                ))}
            </div>
            <figcaption className="flex justify-between text-xs text-muted-foreground">
                <span>{dag(reeks[0].datum)}</span>
                <span>{label}</span>
                <span>{dag(reeks[reeks.length - 1].datum)}</span>
            </figcaption>
            <table className="sr-only">
                <caption>{label}</caption>
                <tbody>
                    {reeks.map((r) => (
                        <tr key={r.datum}>
                            <td>{dag(r.datum)}</td>
                            <td>{r.waarde}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}
