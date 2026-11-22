import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Kaart,
    Leeg,
    MeerLijst,
    Pagina,
    PaginaKop,
    Rij,
    Sectie,
    TweeKolommen,
    Uitklap,
} from '@/components/pagina';
import { StatusBadge } from '@/components/samenwerking/status-badge';
import { datum, datumTijd, euro } from '@/lib/datum';
import { cn } from '@/lib/utils';
import { klus, overzicht } from '@/routes/creator';
import { start as profielStart } from '@/routes/creator/profiel';
import type { SamenwerkingKort } from '@/types/samenwerking';
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

const KLAAR = ['afgerond', 'geannuleerd', 'niet_verschenen'];

export default function Overzicht({
    creator,
    invitations,
    jobs,
    payouts,
    raamovereenkomst_getekend,
}: Props) {
    const voornaam = creator.name.split(' ')[0];
    const lopend = jobs.filter((j) => !KLAAR.includes(j.status.value));
    const klaar = jobs.filter((j) => KLAAR.includes(j.status.value));

    const hoofd = (
        <>
            {!raamovereenkomst_getekend && (
                <Link
                    href={profielStart.url()}
                    className="block"
                    data-test="raamovereenkomst"
                >
                    <Kaart compact className="border-violet bg-violet/5">
                        <p className="font-semibold">
                            Teken eerst je raamovereenkomst
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Eenmalig, daarna kun je uitnodigingen accepteren
                            zonder gedoe. Vul ook je uitbetaalgegevens in.
                        </p>
                    </Kaart>
                </Link>
            )}

            {invitations.length > 0 && (
                <Sectie
                    titel={
                        invitations.length > 1
                            ? 'Nieuwe uitnodigingen'
                            : 'Nieuwe uitnodiging'
                    }
                >
                    <ul className="space-y-2">
                        {invitations.map((u) => (
                            <li key={u.id}>
                                <Link
                                    href={klus(u.collaboration_id)}
                                    className="block"
                                    data-test="uitnodiging"
                                >
                                    <Rij
                                        className="border-violet bg-violet/5 transition hover:bg-violet/10"
                                        titel={u.company}
                                        onder={
                                            <>
                                                <span className="font-semibold text-rood">
                                                    Reageer voor{' '}
                                                    {datumTijd(u.expires_at)}
                                                </span>
                                                <span>, {u.title}</span>
                                            </>
                                        }
                                        rechts={
                                            <ChevronRight
                                                className="size-5 text-violet"
                                                aria-hidden
                                            />
                                        }
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Sectie>
            )}

            <Sectie titel="Lopende klussen">
                {lopend.length === 0 ? (
                    <Leeg titel="Geen lopende klussen">
                        We sturen je een melding zodra er een uitnodiging is.
                    </Leeg>
                ) : (
                    <MeerLijst>
                        {lopend.map((j) => (
                            <KlusRij key={j.id} job={j} />
                        ))}
                    </MeerLijst>
                )}
            </Sectie>

            {klaar.length > 0 && (
                <Uitklap
                    titel="Afgeronde klussen"
                    rechts={
                        <span className="text-sm text-muted-foreground tabular-nums">
                            {klaar.length}
                        </span>
                    }
                >
                    <MeerLijst>
                        {klaar.map((j) => (
                            <KlusRij key={j.id} job={j} />
                        ))}
                    </MeerLijst>
                </Uitklap>
            )}
        </>
    );

    const zij =
        payouts.length > 0 ? (
            <Sectie titel="Uitbetalingen">
                <MeerLijst eerst={5}>
                    {payouts.map((p, i) => (
                        <li key={i}>
                            <Rij
                                titel={p.company}
                                onder={`${p.status === 'uitbetaald' ? 'Uitbetaald' : 'Staat klaar'}, betaalrun ${datum(p.run_on)}`}
                                rechts={
                                    <span className="font-heading text-lg font-extrabold tabular-nums">
                                        {euro(p.amount_cents)}
                                    </span>
                                }
                            />
                        </li>
                    ))}
                </MeerLijst>
            </Sectie>
        ) : null;

    return (
        <>
            <Head title="Mijn klussen" />
            <Pagina>
                <PaginaKop
                    titel="Mijn klussen"
                    omschrijving={
                        <>
                            Hoi {voornaam}. Als{' '}
                            <span className="font-semibold text-foreground">
                                {creator.level}
                            </span>{' '}
                            krijg je: {creator.reward}.
                        </>
                    }
                />
                {zij ? <TweeKolommen hoofd={hoofd} zij={zij} /> : hoofd}
            </Pagina>
        </>
    );
}

/** Eén klus op één rij: bedrijf en klus, daaronder wat er nu moet gebeuren. */
function KlusRij({ job }: { job: SamenwerkingKort }) {
    const stap = volgendeStap(job);

    return (
        <li>
            <Link href={klus(job.id)} className="block" data-test="klus">
                <Rij
                    className={cn(
                        'transition hover:border-violet',
                        stap.aanZet && 'border-violet',
                    )}
                    titel={
                        <>
                            {job.company}
                            <span className="font-normal text-muted-foreground">
                                , {job.title}, {job.location}
                            </span>
                        </>
                    }
                    onder={
                        <span
                            className={cn(
                                stap.aanZet && 'font-semibold text-violet',
                            )}
                        >
                            {stap.aanZet && (
                                <span className="sr-only">
                                    Jij bent aan zet:{' '}
                                </span>
                            )}
                            {stap.tekst}
                        </span>
                    }
                    rechts={
                        <>
                            <span className="hidden sm:inline-flex">
                                <StatusBadge status={job.status} />
                            </span>
                            <ChevronRight
                                className="size-4 text-muted-foreground"
                                aria-hidden
                            />
                        </>
                    }
                />
            </Link>
        </li>
    );
}

Overzicht.layout = {
    breadcrumbs: [{ title: 'Mijn klussen', href: overzicht() }],
};
