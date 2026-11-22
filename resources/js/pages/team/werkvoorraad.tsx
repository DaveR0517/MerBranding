import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ChevronRight, Clock } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { datumTijd } from '@/lib/datum';
import * as team from '@/routes/team';
import type { StatusInfo } from '@/types/samenwerking';
import { Leeg, MeerLijst, Pagina, PaginaKop, Rij } from '@/components/pagina';
import { cn } from '@/lib/utils';
import { Label } from './_onderdelen/basis';

type Taak = {
    id: number;
    type: string;
    title: string;
    due_at: string | null;
    overdue: boolean;
    collaboration_id: number | null;
    url: string | null;
    status: StatusInfo | null;
};

const soorten: Record<string, { naam: string; kleur: 'violet' | 'rood' | 'amber' }> = {
    koppelen: { naam: 'Koppelen', kleur: 'violet' },
    screening: { naam: 'Screening', kleur: 'amber' },
    abonnement: { naam: 'Abonnement', kleur: 'violet' },
    rechten_verlengen: { naam: 'Gebruiksrechten', kleur: 'violet' },
    radar_interesse: { naam: 'Radar', kleur: 'violet' },
    support: { naam: 'Support', kleur: 'amber' },
    'cijfers-controleren': { naam: 'Cijfers', kleur: 'violet' },
    geweigerd: { naam: 'Geweigerd', kleur: 'rood' },
    contentcontrole: { naam: 'Contentcontrole', kleur: 'amber' },
};

/** Dringendste eerst: te laat, dan op termijn, taken zonder termijn onderaan. */
function volgorde(a: Taak, b: Taak): number {
    if (a.overdue !== b.overdue) {
        return a.overdue ? -1 : 1;
    }
    if (!a.due_at || !b.due_at) {
        return a.due_at ? -1 : b.due_at ? 1 : 0;
    }

    return a.due_at.localeCompare(b.due_at);
}

export default function Werkvoorraad({ tasks }: { tasks: Taak[] }) {
    const [filter, setFilter] = useState<string>('alles');
    const telling = (t: string) => tasks.filter((x) => (t === 'te_laat' ? x.overdue : x.type === t)).length;
    const zichtbaar = tasks
        .filter((t) => filter === 'alles' || (filter === 'te_laat' ? t.overdue : t.type === filter))
        .sort(volgorde);

    const chips = [
        { key: 'alles', naam: 'Alles', aantal: tasks.length },
        { key: 'te_laat', naam: 'Over de termijn', aantal: telling('te_laat') },
        ...Object.entries(soorten).map(([key, s]) => ({ key, naam: s.naam, aantal: telling(key) })),
    ];

    return (
        <>
            <Head title="Werkvoorraad" />
            <Pagina>
                <PaginaKop
                    titel="Werkvoorraad"
                    omschrijving={
                        tasks.length === 0
                            ? 'Alles is bij.'
                            : `${tasks.length} ${tasks.length === 1 ? 'taak' : 'taken'}, dringendste eerst`
                    }
                />

                {tasks.length > 0 && (
                    <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:px-0" role="group" aria-label="Filter op soort">
                        {chips.map((c) => (
                            <button
                                key={c.key}
                                type="button"
                                aria-pressed={filter === c.key}
                                onClick={() => setFilter(c.key)}
                                className={`inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-full border px-3 text-sm md:min-h-9 font-semibold transition ${filter === c.key ? 'border-aubergine bg-aubergine text-white' : 'bg-card hover:border-violet'}`}
                            >
                                {c.naam}
                                <span
                                    className={`rounded-full px-1.5 text-xs ${c.key === 'te_laat' && c.aantal > 0 ? 'bg-rood text-white' : filter === c.key ? 'bg-white/20' : 'bg-muted'}`}
                                >
                                    {c.aantal}
                                </span>
                            </button>
                        ))}
                    </div>
                )}

                {tasks.length === 0 ? (
                    <Leeg titel="Geen open taken. Nieuwe aanvragen, weigeringen en video's om te controleren verschijnen hier vanzelf." />
                ) : zichtbaar.length === 0 ? (
                    <Leeg titel="Geen taken in dit filter." />
                ) : (
                    <MeerLijst key={filter}>
                        {zichtbaar.map((t) => {
                            const soort = soorten[t.type] ?? { naam: t.type, kleur: 'violet' as const };
                            const rij = (
                                <Rij
                                    className={cn(t.overdue && 'border-rood/40', t.url && 'group-hover:border-violet')}
                                    links={
                                        <span
                                            className={`grid size-9 place-items-center rounded-full ${t.overdue ? 'bg-rood/10 text-rood' : 'bg-violet/10 text-violet'}`}
                                            aria-hidden
                                        >
                                            {t.overdue ? <AlertTriangle className="size-4" /> : <Clock className="size-4" />}
                                        </span>
                                    }
                                    titel={<span className="line-clamp-2 whitespace-normal">{t.title}</span>}
                                    onder={
                                        <span className="flex items-center gap-2">
                                            <Label kleur={soort.kleur}>{soort.naam}</Label>
                                            <span className={cn('truncate', t.overdue && 'font-semibold text-rood')}>
                                                {t.due_at ? `${t.overdue ? 'Te laat, termijn was' : 'Termijn'} ${datumTijd(t.due_at)}` : 'Geen termijn'}
                                            </span>
                                        </span>
                                    }
                                    rechts={
                                        <>
                                            {t.status && (
                                                <span className="hidden md:inline-flex">
                                                    <StatusBadge status={t.status} />
                                                </span>
                                            )}
                                            {t.url && <ChevronRight className="size-5 text-muted-foreground" aria-hidden />}
                                        </>
                                    }
                                />
                            );

                            return (
                                <li key={t.id} data-test="taak">
                                    {t.url ? (
                                        <Link href={t.url} className="group block rounded-xl">
                                            {rij}
                                        </Link>
                                    ) : (
                                        rij
                                    )}
                                </li>
                            );
                        })}
                    </MeerLijst>
                )}
            </Pagina>
        </>
    );
}

Werkvoorraad.layout = { breadcrumbs: [{ title: 'Werkvoorraad', href: team.werkvoorraad.url() }] };
