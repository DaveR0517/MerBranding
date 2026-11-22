import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { datumTijd } from '@/lib/datum';
import * as team from '@/routes/team';
import type { SamenwerkingKort, StatusInfo } from '@/types/samenwerking';
import { Kop, Leeg, Pagina } from './_onderdelen/basis';

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
        `inline-flex shrink-0 items-center rounded-full border px-3 py-1.5 text-sm font-semibold transition ${actief ? 'border-aubergine bg-aubergine text-white' : 'bg-card hover:border-violet'}`;
    const huidig = statuses.find((s) => s.value === filter);

    return (
        <>
            <Head title="Samenwerkingen" />
            <Pagina breed>
                <Kop
                    titel="Samenwerkingen"
                    sub={`${collaborations.length} ${collaborations.length === 1 ? 'samenwerking' : 'samenwerkingen'}${huidig ? ` met status ${huidig.label}` : ''}, laatst bijgewerkt eerst`}
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
                    <Leeg>{huidig ? `Geen samenwerkingen met status ${huidig.label}.` : 'Nog geen samenwerkingen.'}</Leeg>
                ) : (
                    <>
                        {/* Telefoon: kaarten */}
                        <ul className="space-y-2 md:hidden">
                            {collaborations.map((c) => (
                                <li key={c.id}>
                                    <Link
                                        href={team.samenwerking.url(c.id)}
                                        className="flex items-start gap-3 rounded-2xl border bg-card p-4 hover:border-violet"
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center justify-between gap-2">
                                                <span className="font-mono text-xs text-muted-foreground">{c.number}</span>
                                                <StatusBadge status={c.status} />
                                            </span>
                                            <span className="mt-1 block font-semibold">{c.title}</span>
                                            <span className="block text-sm text-muted-foreground">
                                                {c.company}, {c.location}
                                            </span>
                                            <span className="mt-1 block text-sm">
                                                {c.creator ?? <span className="text-muted-foreground">Nog geen creator</span>}
                                                {c.creator_level && <span className="text-muted-foreground"> · {c.creator_level}</span>}
                                            </span>
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                Bijgewerkt {datumTijd(c.updated_at)}
                                            </span>
                                        </span>
                                        <ChevronRight className="mt-6 size-5 shrink-0 text-muted-foreground" aria-hidden />
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        {/* Breed: tabel */}
                        <div className="hidden overflow-hidden rounded-2xl border bg-card md:block">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/60 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">Nummer</th>
                                        <th className="px-4 py-3 font-semibold">Bedrijf</th>
                                        <th className="px-4 py-3 font-semibold">Titel</th>
                                        <th className="px-4 py-3 font-semibold">Creator</th>
                                        <th className="px-4 py-3 font-semibold">Status</th>
                                        <th className="px-4 py-3 font-semibold">Bijgewerkt</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {collaborations.map((c) => (
                                        <tr key={c.id} className="group hover:bg-violet/5">
                                            <td className="px-4 py-3 font-mono text-xs">
                                                <Link href={team.samenwerking.url(c.id)} className="font-semibold text-violet hover:underline">
                                                    {c.number}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-semibold">{c.company}</span>
                                                <span className="block text-xs text-muted-foreground">{c.location}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Link href={team.samenwerking.url(c.id)} className="hover:text-violet hover:underline">
                                                    {c.title}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                {c.creator ?? <span className="text-muted-foreground">Nog geen</span>}
                                                {c.creator_level && (
                                                    <span className="block text-xs text-muted-foreground">{c.creator_level}</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={c.status} />
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">{datumTijd(c.updated_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </Pagina>
        </>
    );
}

Samenwerkingen.layout = { breadcrumbs: [{ title: 'Samenwerkingen', href: team.samenwerkingen.url() }] };
