import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ChevronRight, Gift, Landmark } from 'lucide-react';
import {
    Kaart,
    KaartRaster,
    Kerncijfer,
    Leeg,
    MeerLijst,
    Pagina,
    PaginaKop,
    Rij,
    Sectie,
    TweeKolommen,
} from '@/components/pagina';
import { datum, euro } from '@/lib/datum';
import * as profiel from '@/routes/creator/profiel';
import { klus } from '@/routes/creator';
import { TitelKaart } from '../_onderdelen/opmaak';

type Uitbetaling = {
    id: number;
    company: string;
    title: string;
    amount_cents: number;
    status: string;
    run_on: string;
    live_at: string | null;
};
type Wachtend = {
    id: number;
    company: string;
    title: string;
    amount_cents: number;
    status: string;
};

type Props = {
    level: string;
    paid_level: boolean;
    fee_cents: number;
    next_run: string;
    totals: {
        open_cents: number;
        paid_year_cents: number;
        waiting_cents: number;
    };
    payouts: Uitbetaling[];
    waiting: Wachtend[];
    payout_details: {
        complete: boolean;
        iban_masked: string | null;
        vat_status: string | null;
    };
};

/** Euro met centen, voor bedragen per samenwerking. */
const bedrag = (c: number) =>
    (c / 100).toLocaleString('nl-NL', { style: 'currency', currency: 'EUR' });

const statusTekst: Record<string, string> = {
    opeisbaar: 'Opeisbaar',
    uitbetaald: 'Betaald',
    in_run: 'In betaalrun',
};

/** Vergoedingen per samenwerking en de betaalrun op vrijdag (ontwerp CreatorVergoedingen en uitbetalingen). */
export default function Vergoedingen({
    level,
    paid_level,
    fee_cents,
    next_run,
    totals,
    payouts,
    waiting,
    payout_details,
}: Props) {
    const leeg = payouts.length === 0 && waiting.length === 0;

    const hoofd = (
        <>
            {!payout_details.complete &&
                (paid_level ||
                    totals.open_cents > 0 ||
                    totals.waiting_cents > 0) && (
                    <Link
                        href={profiel.account.url()}
                        className="flex min-h-11 items-center gap-3 rounded-xl border border-rood/40 bg-rood/5 p-3 text-sm font-semibold text-rood md:p-4"
                        data-test="gegevens-ontbreken"
                    >
                        <AlertTriangle
                            className="size-5 shrink-0"
                            aria-hidden
                        />
                        <span className="flex-1">
                            Vul je uitbetaalgegevens en btw-status in, dan gaat
                            je vergoeding mee in de betaalrun.
                        </span>
                        <ChevronRight className="size-5 shrink-0" aria-hidden />
                    </Link>
                )}

            <Sectie
                titel="Per samenwerking"
                actie={
                    <span className="text-xs font-semibold text-muted-foreground">
                        excl. btw
                    </span>
                }
            >
                {leeg ? (
                    <Leeg titel="Nog geen vergoedingen">
                        {paid_level
                            ? 'Zodra je video Live staat, zie je hem hier.'
                            : 'Bij je niveau krijg je geen geld maar een gratis bezoek. Vergoedingen verschijnen hier vanaf Platinum.'}
                    </Leeg>
                ) : (
                    <div data-test="vergoedingen">
                        <MeerLijst>
                            {[
                                ...waiting.map((w) => (
                                    <li key={`w${w.id}`}>
                                        <Link
                                            href={klus(w.id)}
                                            className="block"
                                        >
                                            <Rij
                                                className="transition hover:border-violet"
                                                titel={w.company}
                                                onder={`Wacht op Live, ${w.status}`}
                                                rechts={
                                                    <Bedrag
                                                        cents={w.amount_cents}
                                                    />
                                                }
                                            />
                                        </Link>
                                    </li>
                                )),
                                ...payouts.map((p) => (
                                    <li key={`p${p.id}`}>
                                        <Rij
                                            titel={p.company}
                                            onder={
                                                <span
                                                    className={
                                                        p.status ===
                                                        'uitbetaald'
                                                            ? 'text-emerald-700'
                                                            : 'text-violet'
                                                    }
                                                >
                                                    {p.status === 'uitbetaald'
                                                        ? 'Betaald'
                                                        : (statusTekst[
                                                              p.status
                                                          ] ?? p.status)}
                                                    , betaalrun{' '}
                                                    {datum(p.run_on)}
                                                </span>
                                            }
                                            rechts={
                                                <Bedrag
                                                    cents={p.amount_cents}
                                                />
                                            }
                                        />
                                    </li>
                                )),
                            ]}
                        </MeerLijst>
                    </div>
                )}
            </Sectie>
        </>
    );

    const zij = (
        <>
            <Kaart className="border-violet">
                <p className="flex items-start gap-3 text-sm">
                    <Gift
                        className="mt-0.5 size-5 shrink-0 text-violet"
                        aria-hidden
                    />
                    <span>
                        {paid_level ? (
                            <>
                                Als {level} krijg je een gratis bezoek en{' '}
                                {euro(fee_cents)} per samenwerking.
                            </>
                        ) : (
                            <>
                                <strong>Gratis bezoek, geen geld.</strong> Als{' '}
                                {level} krijg je een gratis bezoek (eten,
                                overnachting of entree), betaald door de
                                ondernemer. Een vergoeding in geld krijg je
                                vanaf Platinum.
                            </>
                        )}{' '}
                        Je vergoeding volgt je niveau bij acceptatie. Een
                        niveauwijziging raakt lopende samenwerkingen niet.
                    </span>
                </p>
            </Kaart>

            <TitelKaart
                titel={
                    <span className="flex items-center gap-2">
                        <Landmark className="size-5 text-violet" aria-hidden />
                        Uitbetaalgegevens
                    </span>
                }
            >
                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt className="text-muted-foreground">IBAN</dt>
                    <dd className="font-mono">
                        {payout_details.iban_masked ?? 'Nog niet ingevuld'}
                    </dd>
                    <dt className="text-muted-foreground">Btw-status</dt>
                    <dd>{payout_details.vat_status ?? 'Nog niet gekozen'}</dd>
                </dl>
                <p className="text-sm text-muted-foreground">
                    Je bent zelf verantwoordelijk voor je btw-aangifte.{' '}
                    <Link
                        href={profiel.account.url()}
                        className="font-semibold text-violet hover:underline"
                    >
                        Wijzigen
                    </Link>
                </p>
            </TitelKaart>
        </>
    );

    return (
        <>
            <Head title="Vergoedingen" />
            <Pagina>
                <PaginaKop
                    titel="Vergoedingen"
                    omschrijving="Je vergoeding staat klaar zodra je video Live staat. Elke vrijdag is er een betaalrun."
                />

                {(paid_level || totals.open_cents > 0) && (
                    <KaartRaster>
                        <Kerncijfer
                            nadruk
                            label="Volgende uitbetaling"
                            waarde={bedrag(totals.open_cents)}
                            toelichting={`Betaalrun ${datum(next_run)}, btw volgens jouw opgave`}
                        />
                        <Kerncijfer
                            label="Wacht op Live"
                            waarde={bedrag(totals.waiting_cents)}
                        />
                        <Kerncijfer
                            label="Uitbetaald dit jaar"
                            waarde={bedrag(totals.paid_year_cents)}
                        />
                    </KaartRaster>
                )}

                <TweeKolommen hoofd={hoofd} zij={zij} />
            </Pagina>
        </>
    );
}

function Bedrag({ cents }: { cents: number }) {
    return (
        <span className="font-heading text-lg font-extrabold tabular-nums">
            {bedrag(cents)}
        </span>
    );
}

Vergoedingen.layout = {
    breadcrumbs: [{ title: 'Vergoedingen', href: profiel.vergoedingen.url() }],
};
