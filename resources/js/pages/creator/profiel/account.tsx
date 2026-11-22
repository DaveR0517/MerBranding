import { Head, Link, useForm } from '@inertiajs/react';
import { Check, FileSignature, Landmark, Lock, Settings } from 'lucide-react';
import { Pagina, PaginaKop, TweeKolommen } from '@/components/pagina';
import * as profiel from '@/routes/creator/profiel';
import { edit as instellingen } from '@/routes/profile';
import {
    Fout,
    TitelKaart,
    Uitkomst,
    groteKnop,
    veld,
} from '../_onderdelen/opmaak';

type Props = {
    email: string;
    payout: {
        complete: boolean;
        iban_masked: string | null;
        name: string | null;
        vat_status: string | null;
        vat_number: string | null;
    };
    vat_options: { value: string; name: string }[];
    agreement: { version: string; signed_at: string | null };
};

const btwUitleg: Record<string, [string, string]> = {
    btw: ['+21%', 'Je hebt een btw-nummer en doet zelf btw-aangifte.'],
    kor: ['0% btw', 'Je gebruikt de kleineondernemersregeling.'],
    geen: ['geen btw', 'Je hebt geen onderneming en geen btw-nummer.'],
};

/** Account: uitbetaalgegevens, eigen btw-status en de raamovereenkomst (ontwerp CreatorAccount en uitbetalingen). */
export default function Account({
    email,
    payout,
    vat_options,
    agreement,
}: Props) {
    const form = useForm({
        iban: '',
        name: payout.name ?? '',
        vat_status: payout.vat_status ?? '',
        vat_number: payout.vat_number ?? '',
    });

    return (
        <>
            <Head title="Account en uitbetaling" />

            <Pagina>
                <PaginaKop
                    titel="Account en uitbetaling"
                    omschrijving={email}
                />
                <TweeKolommen
                    hoofd={
                        <>
                            {!payout.complete && (
                                <Uitkomst goed={false}>
                                    Nodig voor je eerste uitbetaling: je
                                    rekening en je btw-status.
                                </Uitkomst>
                            )}

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.post(profiel.uitbetaling.url(), {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            form.setData('iban', ''),
                                    });
                                }}
                                className="space-y-5"
                                data-test="uitbetaling-form"
                            >
                                <TitelKaart
                                    titel={
                                        <span className="flex items-center gap-2">
                                            <Landmark
                                                className="size-5 text-violet"
                                                aria-hidden
                                            />
                                            Je rekening
                                        </span>
                                    }
                                >
                                    <div className="space-y-4">
                                        <div>
                                            <label
                                                htmlFor="name"
                                                className="mb-1 block text-sm font-semibold"
                                            >
                                                Naam rekeninghouder
                                            </label>
                                            <input
                                                id="name"
                                                required
                                                autoComplete="name"
                                                value={form.data.name}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'name',
                                                        e.target.value,
                                                    )
                                                }
                                                className={veld}
                                            />
                                            <Fout tekst={form.errors.name} />
                                        </div>
                                        <div>
                                            <label
                                                htmlFor="iban"
                                                className="mb-1 block text-sm font-semibold"
                                            >
                                                IBAN
                                            </label>
                                            <input
                                                id="iban"
                                                required={!payout.iban_masked}
                                                autoComplete="off"
                                                autoCapitalize="characters"
                                                spellCheck={false}
                                                value={form.data.iban}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'iban',
                                                        e.target.value,
                                                    )
                                                }
                                                className={`${veld} font-mono tracking-wide`}
                                                placeholder={
                                                    payout.iban_masked ??
                                                    'NL00 BANK 0123 4567 89'
                                                }
                                                aria-describedby="iban-uitleg"
                                            />
                                            <p
                                                id="iban-uitleg"
                                                className="mt-1 flex items-start gap-1.5 text-xs text-muted-foreground"
                                            >
                                                <Lock
                                                    className="mt-0.5 size-3.5 shrink-0"
                                                    aria-hidden
                                                />
                                                {payout.iban_masked
                                                    ? `Bewaard: ${payout.iban_masked}. Laat leeg om dit nummer te houden. We bewaren je IBAN versleuteld en tonen het maar half.`
                                                    : 'We bewaren je IBAN versleuteld en tonen het maar half.'}
                                            </p>
                                            <Fout tekst={form.errors.iban} />
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            Uitbetalen gaat via een
                                            betaalpartij, die nog gekozen wordt.
                                        </p>
                                    </div>
                                </TitelKaart>

                                <TitelKaart titel="Je btw-status, kies zelf">
                                    <fieldset>
                                        <legend className="sr-only">
                                            Btw-status
                                        </legend>
                                        <div className="grid gap-2">
                                            {vat_options.map((o) => (
                                                <label
                                                    key={o.value}
                                                    className="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-border p-3 has-[:checked]:border-violet has-[:checked]:bg-violet/5"
                                                >
                                                    <input
                                                        type="radio"
                                                        name="vat_status"
                                                        value={o.value}
                                                        checked={
                                                            form.data
                                                                .vat_status ===
                                                            o.value
                                                        }
                                                        onChange={() =>
                                                            form.setData(
                                                                'vat_status',
                                                                o.value,
                                                            )
                                                        }
                                                        className="mt-1 size-4 shrink-0 accent-violet"
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="flex flex-wrap items-baseline justify-between gap-2">
                                                            <span className="font-semibold">
                                                                {o.name}
                                                            </span>
                                                            <span className="text-xs font-semibold text-muted-foreground">
                                                                {
                                                                    btwUitleg[
                                                                        o.value
                                                                    ]?.[0]
                                                                }
                                                            </span>
                                                        </span>
                                                        <span className="block text-sm text-muted-foreground">
                                                            {
                                                                btwUitleg[
                                                                    o.value
                                                                ]?.[1]
                                                            }
                                                        </span>
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </fieldset>
                                    <Fout tekst={form.errors.vat_status} />

                                    {form.data.vat_status === 'btw' && (
                                        <div className="mt-4">
                                            <label
                                                htmlFor="vat_number"
                                                className="mb-1 block text-sm font-semibold"
                                            >
                                                Btw-nummer
                                            </label>
                                            <input
                                                id="vat_number"
                                                required
                                                autoCapitalize="characters"
                                                spellCheck={false}
                                                value={form.data.vat_number}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'vat_number',
                                                        e.target.value,
                                                    )
                                                }
                                                className={`${veld} font-mono`}
                                                placeholder="NL123456789B01"
                                            />
                                            <Fout
                                                tekst={form.errors.vat_number}
                                            />
                                        </div>
                                    )}

                                    <p className="mt-4 rounded-xl bg-muted px-3 py-2 text-sm">
                                        Btw en KOR zijn jouw eigen
                                        verantwoordelijkheid. MEBRANDING rekent
                                        niet voor je en neemt je opgave over op
                                        de specificatie. Tot welk bedrag
                                        particulier kan en welke gegevens
                                        daarbij nodig zijn, wordt nog bepaald.
                                    </p>
                                </TitelKaart>

                                <button
                                    type="submit"
                                    className={groteKnop}
                                    disabled={form.processing}
                                >
                                    <Check className="size-5" aria-hidden />
                                    Uitbetaalgegevens opslaan
                                </button>
                            </form>
                        </>
                    }
                    zij={
                        <>
                            <TitelKaart
                                titel={
                                    <span className="flex items-center gap-2">
                                        <FileSignature
                                            className="size-5 text-violet"
                                            aria-hidden
                                        />
                                        Raamovereenkomst
                                    </span>
                                }
                            >
                                {agreement.signed_at ? (
                                    <p className="text-sm">
                                        Getekend op{' '}
                                        {new Date(
                                            agreement.signed_at,
                                        ).toLocaleDateString('nl-NL', {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        })}
                                        , versie {agreement.version}.
                                    </p>
                                ) : (
                                    <Link
                                        href={`${profiel.start.url()}#raamovereenkomst`}
                                        className="font-semibold text-violet hover:underline"
                                    >
                                        Nog niet getekend. Nu tekenen
                                    </Link>
                                )}
                            </TitelKaart>

                            <TitelKaart titel="Inloggen en beveiliging">
                                <Link
                                    href={instellingen.url()}
                                    className="inline-flex items-center gap-2 font-semibold text-violet hover:underline"
                                >
                                    <Settings className="size-4" aria-hidden />
                                    E-mailadres, wachtwoord en
                                    tweestapsverificatie
                                </Link>
                            </TitelKaart>
                        </>
                    }
                />
            </Pagina>
        </>
    );
}

Account.layout = {
    breadcrumbs: [
        { title: 'Account en uitbetaling', href: profiel.account.url() },
    ],
};
