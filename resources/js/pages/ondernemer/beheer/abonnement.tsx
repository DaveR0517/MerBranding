import { Head, useForm } from '@inertiajs/react';
import { Kaart, KaartRaster, Pagina, PaginaKop } from '@/components/pagina';
import { FileText, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { datum, euro } from '@/lib/datum';
import { cn } from '@/lib/utils';
import beheer from '@/routes/ondernemer/beheer';
import { Tekstvak } from '../_onderdelen/opmaak';
import { Tegoed } from '../_onderdelen/tegoed';
import { Grootboek } from './_onderdelen/grootboek';
import type {
    Abonnement as AbonnementT,
    Boeking,
    Verzoek,
} from './_onderdelen/types';
import { Voorstel } from './_onderdelen/voorstel';

type Pakket = {
    id: number;
    name: string;
    price_cents: number;
    points_per_month: number;
    is_proposal: boolean;
};
type Factuur = {
    id: number;
    number: string;
    date: string | null;
    amount_cents: number | null;
    status: string | null;
};

type Props = {
    company: {
        name: string;
        available_points: number;
        reserved_points: number;
    };
    subscription: AbonnementT | null;
    plans: Pakket[];
    ledger: Boeking[];
    invoices: Factuur[] | null;
    requests: Verzoek[];
    account_manager: string | null;
    can: { wijzigen: boolean };
};

const REDENEN = [
    'Te duur voor wat het oplevert',
    'Minder samenwerkingen nodig',
    'Zaak stopt of wordt verkocht',
    'Andere reden',
];

export default function Abonnement({
    company,
    subscription,
    plans,
    ledger,
    invoices,
    requests,
    account_manager,
    can,
}: Props) {
    const [paneel, setPaneel] = useState<'wijzigen' | 'opzeggen' | null>(null);
    const am = account_manager ?? 'je accountmanager';

    return (
        <>
            <Head title="Abonnement" />
            <Pagina>
                <PaginaKop
                    titel="Abonnement"
                    omschrijving={`Pakket, tegoed en facturen van ${company.name}.`}
                />

                {requests.length > 0 && (
                    <section
                        aria-label="Lopende verzoeken"
                        className="space-y-2"
                    >
                        {requests.map((r) => (
                            <div
                                key={r.id}
                                className="flex items-start gap-3 rounded-xl border border-l-4 border-l-violet bg-card p-4"
                                data-test="verzoek"
                            >
                                <Send
                                    className="mt-0.5 size-5 shrink-0 text-violet"
                                    aria-hidden
                                />
                                <div className="min-w-0">
                                    <p className="font-semibold">
                                        Verzoek verstuurd naar {am}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {r.title}, {datum(r.created_at)}.{' '}
                                        {am.charAt(0).toUpperCase() +
                                            am.slice(1)}{' '}
                                        reageert binnen 1 werkdag.
                                    </p>
                                </div>
                            </div>
                        ))}
                    </section>
                )}

                <KaartRaster kolommen={2}>
                    <Kaart>
                        <section aria-labelledby="huidig">
                            <p
                                id="huidig"
                                className="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Huidig abonnement
                            </p>
                            {subscription ? (
                                <>
                                    <div className="mt-1 flex flex-wrap items-center gap-2">
                                        <h2 className="font-heading text-2xl font-extrabold">
                                            {subscription.name}
                                        </h2>
                                        {subscription.is_proposal && (
                                            <Voorstel />
                                        )}
                                    </div>
                                    <p className="mt-2">
                                        <span className="font-heading text-xl font-bold tabular-nums">
                                            {euro(subscription.price_cents)}
                                        </span>{' '}
                                        <span className="text-sm text-muted-foreground">
                                            per maand, excl. btw
                                        </span>
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {subscription.points_per_month} punten
                                        per maand · sinds{' '}
                                        {datum(subscription.starts_on)}
                                    </p>
                                    {subscription.cancels_on && (
                                        <p className="mt-2 text-sm font-semibold text-rood">
                                            Stopt op{' '}
                                            {datum(subscription.cancels_on)}
                                        </p>
                                    )}
                                    {can.wijzigen ? (
                                        <div className="mt-4 flex flex-wrap gap-2">
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    setPaneel('wijzigen')
                                                }
                                                data-test="wijzigen"
                                            >
                                                Abonnement wijzigen
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                className="text-rood hover:text-rood"
                                                onClick={() =>
                                                    setPaneel('opzeggen')
                                                }
                                            >
                                                Opzeggen
                                            </Button>
                                        </div>
                                    ) : (
                                        <p className="mt-4 text-sm text-muted-foreground">
                                            Alleen een eigenaar kan het
                                            abonnement wijzigen of opzeggen.
                                        </p>
                                    )}
                                </>
                            ) : (
                                <p className="mt-2">
                                    Er staat nog geen abonnement klaar. Neem
                                    contact op met {am}.
                                </p>
                            )}
                        </section>
                    </Kaart>

                    <Tegoed
                        beschikbaar={company.available_points}
                        gereserveerd={company.reserved_points}
                    >
                        <p className="mt-2 text-xs text-white/65">
                            Annuleren of een creator die niet komt: je tegoed
                            komt terug. Tegoedregels zijn een voorstel.
                        </p>
                    </Tegoed>
                </KaartRaster>

                {can.wijzigen && paneel === 'wijzigen' && (
                    <Wijzigen
                        plans={plans}
                        huidig={subscription?.plan_id ?? null}
                        sluit={() => setPaneel(null)}
                        am={am}
                    />
                )}
                {can.wijzigen && paneel === 'opzeggen' && (
                    <Opzeggen sluit={() => setPaneel(null)} am={am} />
                )}

                <Kaart>
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <h2 className="font-heading text-lg font-bold">
                            Tegoed per boeking
                        </h2>
                        <span className="text-sm text-muted-foreground">
                            Saldo {company.available_points} punten
                        </span>
                    </div>
                    <p className="-mt-1 mb-3 text-sm text-muted-foreground">
                        Elke wijziging in je tegoed staat hier: toegekend per
                        maand, gereserveerd bij een aanvraag, verbruikt na het
                        bezoek, teruggegeven bij annuleren.
                    </p>
                    <Grootboek ledger={ledger} />
                </Kaart>

                <Kaart>
                    <h2 className="mb-3 font-heading text-lg font-bold">
                        Facturen
                    </h2>
                    {invoices === null ? (
                        <p className="flex items-start gap-2 text-sm text-muted-foreground">
                            <FileText
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden
                            />
                            Facturen volgen. Zodra de betaalpartij gekoppeld is,
                            vind je hier elke maandfactuur.
                        </p>
                    ) : invoices.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nog geen facturen.
                        </p>
                    ) : (
                        <ul className="divide-y" data-test="facturen">
                            {invoices.map((f) => (
                                <li
                                    key={f.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                                >
                                    <span className="font-semibold">
                                        {f.number}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {f.date ? datum(f.date) : ''}
                                    </span>
                                    <span>
                                        {f.amount_cents !== null
                                            ? euro(f.amount_cents)
                                            : ''}
                                    </span>
                                    {f.status && (
                                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs">
                                            {f.status}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Kaart>
            </Pagina>
        </>
    );
}

function Wijzigen({
    plans,
    huidig,
    sluit,
    am,
}: {
    plans: Pakket[];
    huidig: number | null;
    sluit: () => void;
    am: string;
}) {
    const form = useForm({
        soort: 'wijzigen',
        plan_id: '' as string | number,
        toelichting: '',
    });
    const { data, setData, errors, processing } = form;

    function versturen(e: FormEvent) {
        e.preventDefault();
        form.post(beheer.abonnement.verzoek.url(), {
            preserveScroll: true,
            onSuccess: () => sluit(),
        });
    }

    return (
        <Kaart className="border-violet/40">
            <form
                onSubmit={versturen}
                className="space-y-4"
                noValidate
                data-test="wijzigen-formulier"
            >
                <h2 className="mb-3 font-heading text-lg font-bold">
                    Abonnementen vergelijken
                </h2>
                <p className="-mt-2 text-sm text-muted-foreground">
                    Kies het pakket dat beter past. Je stuurt een verzoek: {am}{' '}
                    bevestigt de overstap en de ingangsdatum. Er wordt nu niets
                    gewijzigd of betaald.
                </p>
                <div
                    className="grid gap-3 md:grid-cols-3"
                    role="radiogroup"
                    aria-label="Pakket"
                >
                    {plans.map((p) => {
                        const isHuidig = p.id === huidig;
                        const gekozen = String(data.plan_id) === String(p.id);

                        return (
                            <button
                                key={p.id}
                                type="button"
                                role="radio"
                                aria-checked={gekozen}
                                disabled={isHuidig}
                                onClick={() => setData('plan_id', p.id)}
                                className={cn(
                                    'rounded-xl border p-4 text-left transition',
                                    isHuidig
                                        ? 'cursor-default bg-muted/50'
                                        : gekozen
                                          ? 'border-violet bg-violet/5 ring-1 ring-violet'
                                          : 'hover:border-violet/60',
                                )}
                            >
                                <span className="flex flex-wrap items-center gap-2">
                                    <span className="font-heading text-lg font-bold">
                                        {p.name}
                                    </span>
                                    {isHuidig ? (
                                        <span className="rounded-full bg-aubergine px-2 py-0.5 text-xs font-semibold text-white">
                                            Huidig
                                        </span>
                                    ) : (
                                        p.is_proposal && <Voorstel />
                                    )}
                                </span>
                                <span className="mt-1 block font-heading text-xl font-extrabold">
                                    {euro(p.price_cents)}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    per maand, excl. btw
                                </span>
                                <span className="mt-2 block text-sm">
                                    {p.points_per_month} punten per maand
                                </span>
                            </button>
                        );
                    })}
                </div>
                <InputError message={errors.plan_id} />
                <div className="space-y-1.5">
                    <Label htmlFor="w-toelichting">
                        Toelichting (niet verplicht)
                    </Label>
                    <Tekstvak
                        id="w-toelichting"
                        value={data.toelichting}
                        onChange={(e) => setData('toelichting', e.target.value)}
                        className="min-h-20"
                    />
                </div>
                <p className="text-xs text-muted-foreground">
                    Lopende samenwerkingen lopen altijd door. Wanneer een
                    overstap ingaat en de prijzen van de pakketten liggen nog
                    niet vast (VOORSTEL).
                </p>
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" onClick={sluit}>
                        Annuleren
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing || !data.plan_id}
                    >
                        {processing && <Spinner />} Verzoek versturen
                    </Button>
                </div>
            </form>
        </Kaart>
    );
}

function Opzeggen({ sluit, am }: { sluit: () => void; am: string }) {
    const form = useForm({
        soort: 'opzeggen',
        reden: '',
        toelichting: '',
        akkoord: false,
    });
    const { data, setData, errors, processing } = form;

    function versturen(e: FormEvent) {
        e.preventDefault();
        form.post(beheer.abonnement.verzoek.url(), {
            preserveScroll: true,
            onSuccess: () => sluit(),
        });
    }

    return (
        <Kaart className="border-rood/40">
            <form
                onSubmit={versturen}
                className="space-y-4"
                noValidate
                data-test="opzeggen-formulier"
            >
                <h2 className="mb-3 font-heading text-lg font-bold">
                    Abonnement opzeggen
                </h2>
                <p className="-mt-2 text-sm text-muted-foreground">
                    Jammer dat je wilt stoppen. Vertel ons waarom, dan kijkt{' '}
                    {am} of we iets kunnen oplossen.
                </p>
                <div className="space-y-1.5">
                    <Label htmlFor="o-reden">Reden</Label>
                    <select
                        id="o-reden"
                        value={data.reden}
                        onChange={(e) => setData('reden', e.target.value)}
                        className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Kies een reden</option>
                        {REDENEN.map((r) => (
                            <option key={r}>{r}</option>
                        ))}
                    </select>
                    <InputError message={errors.reden} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="o-toelichting">
                        Toelichting (niet verplicht)
                    </Label>
                    <Tekstvak
                        id="o-toelichting"
                        value={data.toelichting}
                        onChange={(e) => setData('toelichting', e.target.value)}
                        className="min-h-20"
                    />
                </div>
                <dl className="grid gap-3 rounded-lg bg-muted/50 p-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt className="font-semibold">Einddatum</dt>
                        <dd className="text-muted-foreground">
                            Einde van je periode. Opzegtermijn en minimale
                            looptijd: nog te bepalen.
                        </dd>
                    </div>
                    <div>
                        <dt className="font-semibold">
                            Lopende samenwerkingen
                        </dt>
                        <dd className="text-muted-foreground">
                            Lopen gewoon door, tot de video live staat en de
                            meting klaar is.
                        </dd>
                    </div>
                    <div>
                        <dt className="font-semibold">Content en resultaten</dt>
                        <dd className="text-muted-foreground">
                            Blijven zichtbaar. Hoe lang: nog te bepalen.
                        </dd>
                    </div>
                </dl>
                <label className="flex cursor-pointer items-start gap-3 text-sm">
                    <Checkbox
                        checked={data.akkoord}
                        onCheckedChange={(v) => setData('akkoord', v === true)}
                        className="mt-0.5"
                    />
                    <span>
                        Ik begrijp dat mijn abonnement stopt aan het einde van
                        de periode. Lopende samenwerkingen worden afgemaakt.
                    </span>
                </label>
                <InputError message={errors.akkoord} />
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" onClick={sluit}>
                        Toch niet
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={processing}
                    >
                        {processing && <Spinner />} Opzegverzoek versturen
                    </Button>
                </div>
            </form>
        </Kaart>
    );
}

Abonnement.layout = {
    breadcrumbs: [{ title: 'Abonnement', href: beheer.abonnement() }],
};
