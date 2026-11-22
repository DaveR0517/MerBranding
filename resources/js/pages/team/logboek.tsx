import { Head, Link } from '@inertiajs/react';
import { datumTijd } from '@/lib/datum';
import * as team from '@/routes/team';
import { Kop, Leeg, Pagina, gebeurtenis } from './_onderdelen/basis';

type Regel = {
    at: string;
    user: string;
    event: string;
    subject: string | null;
    collaboration_id: number | null;
    from: string | null;
    to: string | null;
    reason: string | null;
};

function Wat({ r }: { r: Regel }) {
    return (
        <>
            <span className="font-semibold">{gebeurtenis(r.event)}</span>
            {(r.from || r.to) && (
                <span className="block">
                    {r.from ?? 'Start'} <span className="text-muted-foreground">naar</span> <span className="font-semibold">{r.to}</span>
                </span>
            )}
            {r.reason && <span className="block text-muted-foreground">{r.reason}</span>}
        </>
    );
}

function Onderwerp({ r }: { r: Regel }) {
    return r.collaboration_id ? (
        <Link href={team.samenwerking.url(r.collaboration_id)} className="font-mono text-xs font-semibold text-violet hover:underline">
            {r.subject ?? `#${r.collaboration_id}`}
        </Link>
    ) : (
        <span className="text-muted-foreground">-</span>
    );
}

export default function Logboek({ entries }: { entries: Regel[] }) {
    return (
        <>
            <Head title="Logboek" />
            <Pagina breed>
                <Kop titel="Logboek" sub="Wie deed wat en wanneer. De laatste 300 gebeurtenissen, nieuwste eerst." />

                {entries.length === 0 ? (
                    <Leeg>Nog niets vastgelegd.</Leeg>
                ) : (
                    <>
                        <ol className="space-y-2 md:hidden" data-test="logboek">
                            {entries.map((r, i) => (
                                <li key={i} className="rounded-2xl border bg-card p-3 text-sm">
                                    <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                        <span>{datumTijd(r.at)}</span>
                                        <Onderwerp r={r} />
                                    </div>
                                    <p className="mt-1">
                                        <Wat r={r} />
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">Door {r.user}</p>
                                </li>
                            ))}
                        </ol>
                        <div className="hidden overflow-hidden rounded-2xl border bg-card md:block">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/60 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">Tijd</th>
                                        <th className="px-4 py-3 font-semibold">Wie</th>
                                        <th className="px-4 py-3 font-semibold">Wat</th>
                                        <th className="px-4 py-3 font-semibold">Samenwerking</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {entries.map((r, i) => (
                                        <tr key={i} className="align-top">
                                            <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">{datumTijd(r.at)}</td>
                                            <td className="px-4 py-3 whitespace-nowrap">{r.user}</td>
                                            <td className="px-4 py-3">
                                                <Wat r={r} />
                                            </td>
                                            <td className="px-4 py-3">
                                                <Onderwerp r={r} />
                                            </td>
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

Logboek.layout = { breadcrumbs: [{ title: 'Logboek', href: team.logboek.url() }] };
