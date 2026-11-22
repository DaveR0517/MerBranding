import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { datumTijd } from '@/lib/datum';
import * as team from '@/routes/team';
import type { SamenwerkingKort, StatusInfo } from '@/types/samenwerking';
import { Leeg, MeerLijst, Pagina, PaginaKop, Rij } from '@/components/pagina';

export default function Samenwerkingen({
    filter,
    statuses,
    collaborations,
}: {
    filter: string | null;
    statuses: StatusInfo[];
    collaborations: SamenwerkingKort[];
}) {
    const chip = (actief: boolean) =>
        `inline-flex min-h-10 shrink-0 items-center rounded-full border px-3 text-sm font-semibold transition md:min-h-9 ${actief ? 'border-aubergine bg-aubergine text-white' : 'bg-card hover:border-violet'}`;
    const huidig = statuses.find((s) => s.value === filter);

    return (
        <>
            <Head title="Samenwerkingen" />
            <Pagina>
                <PaginaKop
                    titel="Samenwerkingen"
                    omschrijving={`${collaborations.length} ${collaborations.length === 1 ? 'samenwerking' : 'samenwerkingen'}${huidig ? ` met status ${huidig.label}` : ''}, laatst bijgewerkt eerst`}
                />

                <nav aria-label="Filter op status" className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:px-0">
                    <Link href={team.samenwerkingen.url()} className={chip(!filter)} aria-current={!filter ? 'true' : undefined} preserveScroll>
                        Alle
                    </Link>
                    {statuses.map((s) => (
                        <Link
                            key={s.value}
                            href={team.samenwerkingen.url({ query: { status: s.value } })}
                            className={chip(filter === s.value)}
                            aria-current={filter === s.value ? 'true' : undefined}
                            preserveScroll
                        >
                            {s.number ? `${s.number}. ` : ''}
                            {s.label}
                        </Link>
                    ))}
                </nav>

                {collaborations.length === 0 ? (
                    <Leeg titel={huidig ? `Geen samenwerkingen met status ${huidig.label}.` : 'Nog geen samenwerkingen.'} />
                ) : (
                    <MeerLijst eerst={12} key={filter ?? 'alle'}>
                        {collaborations.map((c) => (
                            <li key={c.id}>
                                <Link href={team.samenwerking.url(c.id)} className="group block rounded-xl">
                                    <Rij
                                        className="group-hover:border-violet"
                                        titel={c.title}
                                        onder={
                                            <>
                                                <span className="font-mono text-xs">{c.number}</span> · {c.company}, {c.location} ·{' '}
                                                {c.creator ?? 'nog geen creator'}
                                                {c.creator_level && ` (${c.creator_level})`}
                                            </>
                                        }
                                        rechts={
                                            <>
                                                <span className="hidden text-sm whitespace-nowrap text-muted-foreground tabular-nums lg:inline">
                                                    {datumTijd(c.updated_at)}
                                                </span>
                                                <StatusBadge status={c.status} />
                                                <ChevronRight className="size-4 text-muted-foreground" aria-hidden />
                                            </>
                                        }
                                    />
                                </Link>
                            </li>
                        ))}
                    </MeerLijst>
                )}
            </Pagina>
        </>
    );
}

Samenwerkingen.layout = { breadcrumbs: [{ title: 'Samenwerkingen', href: team.samenwerkingen.url() }] };
