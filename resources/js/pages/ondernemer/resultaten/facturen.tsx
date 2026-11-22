import { Head } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { bedrag } from '@/components/cijfers/cijfers';
import { Leeg, MeerLijst, Pagina, PaginaKop, Rij } from '@/components/pagina';
import { datum } from '@/lib/datum';
import { facturen } from '@/routes/ondernemer';

type Factuur = {
    id: number;
    number: string;
    description: string;
    issued_on: string;
    due_on: string;
    total_cents: number;
    status: 'concept' | 'openstaand' | 'te_laat' | 'betaald';
    paid_at: string | null;
    url: string;
};

const label: Record<Factuur['status'], { tekst: string; klasse: string }> = {
    betaald: { tekst: 'Betaald', klasse: 'bg-emerald-100 text-emerald-800' },
    te_laat: { tekst: 'Te laat', klasse: 'bg-rood/10 text-rood' },
    openstaand: { tekst: 'Openstaand', klasse: 'bg-amber-100 text-amber-800' },
    concept: { tekst: 'Concept', klasse: 'bg-muted text-muted-foreground' },
};

export default function Facturen({
    company,
    invoices,
}: {
    company: string;
    invoices: Factuur[];
}) {
    return (
        <>
            <Head title="Facturen" />
            <Pagina smal>
                <PaginaKop
                    titel="Facturen"
                    omschrijving={`${company}. Je abonnement wordt op de 1e van de maand gefactureerd.`}
                />
                {invoices.length === 0 ? (
                    <Leeg titel="Nog geen facturen">
                        Je eerste factuur komt op de 1e van de volgende maand.
                    </Leeg>
                ) : (
                    <MeerLijst eerst={12}>
                        {invoices.map((f) => (
                            <li key={f.id}>
                                <a
                                    href={f.url}
                                    className="block rounded-xl transition hover:ring-2 hover:ring-violet/30"
                                    data-test="factuur"
                                >
                                    <Rij
                                        titel={f.description}
                                        onder={`${f.number} · ${
                                            f.status === 'betaald' && f.paid_at
                                                ? `betaald ${datum(f.paid_at)}`
                                                : `vervalt ${datum(f.due_on)}`
                                        }`}
                                        rechts={
                                            <>
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ${label[f.status].klasse}`}
                                                >
                                                    {label[f.status].tekst}
                                                </span>
                                                <span className="font-heading font-bold tabular-nums">
                                                    {bedrag(f.total_cents)}
                                                </span>
                                                <ChevronRight
                                                    className="size-4 text-muted-foreground"
                                                    aria-hidden
                                                />
                                            </>
                                        }
                                    />
                                </a>
                            </li>
                        ))}
                    </MeerLijst>
                )}
            </Pagina>
        </>
    );
}

Facturen.layout = { breadcrumbs: [{ title: 'Facturen', href: facturen() }] };
