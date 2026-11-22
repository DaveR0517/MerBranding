import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Clock, Gift } from 'lucide-react';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { datum, datumTijd, euro } from '@/lib/datum';
import { klus, overzicht } from '@/routes/creator';
import { start as profielStart } from '@/routes/creator/profiel';
import type { SamenwerkingKort } from '@/types/samenwerking';
import { Kaart, Kop } from './_onderdelen/opmaak';
import { volgendeStap } from './_onderdelen/volgende-stap';

type Props = {
    creator: { name: string; level: string; reward: string };
    raamovereenkomst_getekend: boolean;
    invitations: {
        id: number;
        collaboration_id: number;
        company: string;
        location: string;
        title: string;
        expires_at: string;
    }[];
    jobs: SamenwerkingKort[];
    payouts: {
        company: string;
        amount_cents: number;
        status: string;
        run_on: string;
    }[];
};

export default function Overzicht({
    creator,
    invitations,
    jobs,
    payouts,
    raamovereenkomst_getekend,
}: Props) {
    const voornaam = creator.name.split(' ')[0];
    const lopend = jobs.filter(
        (j) =>
            !['afgerond', 'geannuleerd', 'niet_verschenen'].includes(
                j.status.value,
            ),
    );
    const klaar = jobs.filter((j) => !lopend.includes(j));

    return (
        <>
            <Head title="Mijn klussen" />
            <Kop
                boven="Mijn klussen"
                titel={`Hoi ${voornaam}`}
                onder={
                    <span className="mt-1 flex flex-wrap items-center gap-2">
                        <span className="rounded-md bg-white px-2 py-0.5 text-xs font-extrabold tracking-wider text-aubergine-dark uppercase">
                            {creator.level}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <Gift className="size-4 shrink-0" aria-hidden />
                            {creator.reward}
                        </span>
                    </span>
                }
            />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-5 md:px-6">
                {!raamovereenkomst_getekend && (
                    <Link
                        href={profielStart.url()}
                        className="block rounded-2xl border-2 border-violet bg-violet/5 p-4"
                        data-test="raamovereenkomst"
                    >
                        <span className="font-heading font-extrabold">Teken eerst je raamovereenkomst</span>
                        <span className="mt-1 block text-sm text-muted-foreground">
                            Eenmalig, daarna kun je uitnodigingen accepteren zonder gedoe. Vul ook je uitbetaalgegevens in.
                        </span>
                    </Link>
                )}
                {invitations.length > 0 && (
                    <section
                        aria-labelledby="uitnodigingen"
                        className="space-y-3"
                    >
                        <h2
                            id="uitnodigingen"
                            className="font-heading text-lg font-extrabold"
                        >
                            Nieuwe uitnodiging
                            {invitations.length > 1 ? 'en' : ''}
                        </h2>
                        {invitations.map((u) => (
                            <Link
                                key={u.id}
                                href={klus(u.collaboration_id)}
                                className="block rounded-2xl border-2 border-violet bg-violet/5 p-4 transition hover:bg-violet/10"
                                data-test="uitnodiging"
                            >
                                <span className="text-xs font-bold tracking-wider text-rood uppercase">
                                    Reageer voor {datumTijd(u.expires_at)}
                                </span>
                                <span className="mt-1 flex items-center justify-between gap-3">
                                    <span className="min-w-0">
                                        <span className="block font-heading text-lg font-extrabold break-words">
                                            {u.company}
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            {u.title}, {u.location}
                                        </span>
                                    </span>
                                    <ChevronRight
                                        className="size-6 shrink-0 text-violet"
                                        aria-hidden
                                    />
                                </span>
                                <span className="mt-3 flex min-h-11 items-center justify-center rounded-xl bg-violet px-4 text-sm font-bold text-white">
                                    Bekijk en reageer
                                </span>
                            </Link>
                        ))}
                    </section>
                )}

                <section aria-labelledby="klussen" className="space-y-3">
                    <h2
                        id="klussen"
                        className="font-heading text-lg font-extrabold"
                    >
                        Lopende klussen
                    </h2>
                    {lopend.length === 0 && (
                        <Kaart>
                            <p className="text-muted-foreground">
                                Je hebt nu geen lopende klussen. We sturen je
                                een melding zodra er een uitnodiging is.
                            </p>
                        </Kaart>
                    )}
                    <ul className="space-y-3">
                        {lopend.map((j) => (
                            <KlusRegel key={j.id} job={j} />
                        ))}
                    </ul>
                </section>

                {payouts.length > 0 && (
                    <section
                        aria-labelledby="uitbetalingen"
                        className="space-y-3"
                    >
                        <h2
                            id="uitbetalingen"
                            className="font-heading text-lg font-extrabold"
                        >
                            Uitbetalingen
                        </h2>
                        <Kaart>
                            <ul className="divide-y">
                                {payouts.map((p, i) => (
                                    <li
                                        key={i}
                                        className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                    >
                                        <span className="min-w-0">
                                            <span className="block font-semibold break-words">
                                                {p.company}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {p.status === 'uitbetaald'
                                                    ? `Uitbetaald, betaalrun ${datum(p.run_on)}`
                                                    : `Staat klaar, betaalrun ${datum(p.run_on)}`}
                                            </span>
                                        </span>
                                        <span className="shrink-0 font-heading text-lg font-extrabold">
                                            {euro(p.amount_cents)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Kaart>
                    </section>
                )}

                {klaar.length > 0 && (
                    <section aria-labelledby="eerder" className="space-y-3">
                        <h2
                            id="eerder"
                            className="font-heading text-lg font-extrabold"
                        >
                            Eerdere klussen
                        </h2>
                        <ul className="space-y-3">
                            {klaar.map((j) => (
                                <KlusRegel key={j.id} job={j} />
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

function KlusRegel({ job }: { job: SamenwerkingKort }) {
    const stap = volgendeStap(job);

    return (
        <li>
            <Link
                href={klus(job.id)}
                className={`block rounded-2xl border bg-card p-4 shadow-xs transition hover:border-violet ${stap.aanZet ? 'border-l-4 border-l-violet' : ''}`}
                data-test="klus"
            >
                <span className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-heading text-base font-extrabold break-words">
                        {job.company}
                    </span>
                    <StatusBadge status={job.status} />
                </span>
                <span className="block text-sm text-muted-foreground">
                    {job.title}, {job.location}
                </span>
                <span
                    className={`mt-3 flex items-center gap-2 rounded-xl px-3 py-2 text-sm ${stap.aanZet ? 'bg-violet/10 font-semibold text-violet' : 'bg-muted text-muted-foreground'}`}
                >
                    {stap.aanZet ? (
                        <ChevronRight className="size-4 shrink-0" aria-hidden />
                    ) : (
                        <Clock className="size-4 shrink-0" aria-hidden />
                    )}
                    <span>
                        {stap.aanZet && (
                            <span className="sr-only">Jij bent aan zet: </span>
                        )}
                        {stap.tekst}
                    </span>
                </span>
            </Link>
        </li>
    );
}

Overzicht.layout = {
    breadcrumbs: [{ title: 'Mijn klussen', href: overzicht() }],
};
