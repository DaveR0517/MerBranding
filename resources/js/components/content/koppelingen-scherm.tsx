import { router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    Clock,
    Eye,
    Link2,
    ShieldCheck,
    Unlink,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { datum, datumTijd } from '@/lib/datum';
import { Etiket, koppelingStatus, waarschuwingen } from './labels';
import type { Account, Toestemmingstekst } from './types';

export type KoppelingRoutes = {
    toestemming: (platform: string) => string;
    handmatig: (platform: string) => string;
    verbreken: (platform: string) => string;
};

/**
 * Koppelingenscherm van creator en ondernemer (CreatorKoppelingen, OndernemerKoppelingen).
 * Eerst toestemming (AVG: wat, met wie, hoe lang), dan inloggen bij het platform of, zolang de sleutels er niet zijn, handmatig.
 */
export function KoppelingenLijst({
    accounts,
    toestemming,
    routes,
    magBeheren,
    voor,
}: {
    accounts: Account[];
    toestemming: Toestemmingstekst;
    routes: KoppelingRoutes;
    magBeheren: boolean;
    voor: 'creator' | 'ondernemer';
}) {
    const [open, setOpen] = useState<Account | null>(null);

    return (
        <div className="space-y-4">
            <div className="flex gap-3 rounded-xl border border-violet/20 bg-violet/5 p-4 text-sm">
                <Eye
                    className="mt-0.5 size-5 shrink-0 text-violet"
                    aria-hidden
                />
                <p>
                    <strong>MEBRANDING leest alleen.</strong>{' '}
                    {voor === 'creator'
                        ? "We halen cijfers op over je video's en je publiek. We plaatsen nooit iets namens jou."
                        : 'We halen profielbezoeken en nieuwe volgers op. We plaatsen nooit iets op je account.'}
                </p>
            </div>

            <ul className="grid gap-4 md:grid-cols-2">
                {accounts.map((a) => (
                    <li key={a.platform}>
                        <AccountKaart
                            account={a}
                            routes={routes}
                            magBeheren={magBeheren}
                            onKoppelen={() => setOpen(a)}
                            voor={voor}
                        />
                    </li>
                ))}
            </ul>

            <p className="text-sm text-muted-foreground">
                Je kunt een koppeling op elk moment verbreken, hier of in de
                instellingen van Instagram of TikTok.
                {voor === 'creator' &&
                    ' Een verbroken koppeling geeft geen strafpunt.'}
            </p>

            {open && (
                <ToestemmingDialoog
                    account={open}
                    tekst={toestemming}
                    url={routes.toestemming(open.platform)}
                    onSluit={() => setOpen(null)}
                />
            )}
        </div>
    );
}

function AccountKaart({
    account: a,
    routes,
    magBeheren,
    onKoppelen,
    voor,
}: {
    account: Account;
    routes: KoppelingRoutes;
    magBeheren: boolean;
    onKoppelen: () => void;
    voor: 'creator' | 'ondernemer';
}) {
    const status = koppelingStatus[a.status] ?? koppelingStatus.niet_gekoppeld;
    // Fout en verlopen staan al in de status; alleen "verloopt binnenkort" komt erbij.
    const waarschuwing =
        a.waarschuwing === 'verloopt' ? waarschuwingen.verloopt : null;
    const actief = a.status === 'handmatig' || a.status === 'gekoppeld';

    return (
        <section
            className="flex h-full flex-col gap-3 rounded-xl border bg-card p-4 md:p-5"
            data-test={`koppeling-${a.platform}`}
        >
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <h2 className="font-heading text-lg font-extrabold">
                        {a.naam}
                    </h2>
                    <p className="truncate text-sm text-muted-foreground">
                        {a.handle ??
                            (voor === 'creator'
                                ? 'Creator-account'
                                : 'Bedrijfsaccount of creatoraccount')}
                    </p>
                </div>
                <div className="flex flex-col items-end gap-1">
                    <Etiket kleur={status.kleur}>{status.naam}</Etiket>
                    {waarschuwing && (
                        <Etiket kleur={waarschuwing.kleur}>
                            {waarschuwing.naam}
                        </Etiket>
                    )}
                </div>
            </div>

            <dl className="grid grid-cols-2 gap-2 text-sm">
                <div>
                    <dt className="text-xs font-semibold text-muted-foreground uppercase">
                        Laatste synchronisatie
                    </dt>
                    <dd>
                        {a.laatste_sync
                            ? datumTijd(a.laatste_sync)
                            : 'Nog niet'}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs font-semibold text-muted-foreground uppercase">
                        {a.verloopt_op ? 'Toegang verloopt op' : 'Toestemming'}
                    </dt>
                    <dd>
                        {a.verloopt_op
                            ? datum(a.verloopt_op)
                            : a.toestemming && a.toestemming_op
                              ? `Gegeven ${datum(a.toestemming_op)} (versie ${a.toestemming_versie})`
                              : 'Nog niet gegeven'}
                    </dd>
                </div>
            </dl>

            {a.status === 'handmatig' && (
                <p className="flex gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-900/30 dark:text-amber-100">
                    <Clock className="mt-0.5 size-4 shrink-0" aria-hidden />
                    Handmatige modus: tot de koppeling er is, lever je de
                    cijfers aan met een screenshot. Het team controleert ze.
                </p>
            )}

            {(a.status === 'fout' || a.status === 'verlopen') && (
                <p
                    role="alert"
                    className="flex gap-2 rounded-lg bg-rood/5 p-3 text-sm text-rood"
                >
                    <AlertTriangle
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden
                    />
                    We kunnen je cijfers niet meer ophalen. Koppel opnieuw, of
                    lever ze handmatig aan.
                </p>
            )}

            <div className="mt-auto space-y-3">
                {magBeheren && a.toestemming && !actief && (
                    <HandmatigFormulier
                        account={a}
                        url={routes.handmatig(a.platform)}
                    />
                )}

                {magBeheren && (
                    <div className="flex flex-wrap gap-2">
                        {(!a.toestemming || (a.ingesteld && !actief)) && (
                            <Button
                                type="button"
                                className="min-h-11 flex-1 bg-violet hover:bg-violet/90"
                                onClick={onKoppelen}
                                data-test={`koppelen-${a.platform}`}
                            >
                                <Link2 aria-hidden />
                                Koppelen met {a.naam}
                            </Button>
                        )}
                        {(a.toestemming || actief) && (
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            `Koppeling met ${a.naam} verbreken? Je toestemming wordt ingetrokken.`,
                                        )
                                    ) {
                                        router.post(
                                            routes.verbreken(a.platform),
                                            {},
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            >
                                <Unlink aria-hidden />
                                Koppeling verbreken
                            </Button>
                        )}
                    </div>
                )}
                {!a.ingesteld && !actief && (
                    <p className="text-xs text-muted-foreground">
                        Koppelen met {a.naam} is binnenkort beschikbaar. Tot dan
                        vul je je account handmatig in.
                    </p>
                )}
                {!magBeheren && (
                    <p className="text-xs text-muted-foreground">
                        Alleen de eigenaar van het bedrijf kan koppelingen
                        beheren.
                    </p>
                )}
            </div>
        </section>
    );
}

function HandmatigFormulier({
    account: a,
    url,
}: {
    account: Account;
    url: string;
}) {
    const form = useForm({ handle: a.handle ?? '', professioneel: false });

    function opslaan(e: FormEvent) {
        e.preventDefault();
        form.post(url, { preserveScroll: true });
    }

    return (
        <form
            onSubmit={opslaan}
            className="space-y-3 rounded-xl border border-dashed p-3"
            noValidate
        >
            <p className="text-sm font-semibold">Handmatig invullen</p>
            <div className="grid gap-1.5">
                <label htmlFor={`handle-${a.platform}`} className="text-sm">
                    Gebruikersnaam op {a.naam}
                </label>
                <Input
                    id={`handle-${a.platform}`}
                    value={form.data.handle}
                    onChange={(e) => form.setData('handle', e.target.value)}
                    placeholder="@gebruikersnaam"
                    autoComplete="off"
                    aria-invalid={!!form.errors.handle}
                />
                <InputError message={form.errors.handle} />
            </div>
            <label className="flex items-start gap-2 text-sm">
                <input
                    type="checkbox"
                    className="mt-0.5 size-4 accent-violet"
                    checked={form.data.professioneel}
                    onChange={(e) =>
                        form.setData('professioneel', e.target.checked)
                    }
                />
                <span>
                    Dit is een professioneel of creator-account. Dat is nodig om
                    cijfers per regio te kunnen tonen.
                </span>
            </label>
            <InputError message={form.errors.professioneel} />
            <Button
                type="submit"
                variant="outline"
                className="min-h-11 w-full"
                disabled={form.processing}
            >
                <Check aria-hidden />
                Opslaan
            </Button>
        </form>
    );
}

/** Toestemmingsscherm (AVG): wat, met wie en hoe lang, met versienummer. Toestemming wordt gelogd met datum en versie. */
function ToestemmingDialoog({
    account: a,
    tekst,
    url,
    onSluit,
}: {
    account: Account;
    tekst: Toestemmingstekst;
    url: string;
    onSluit: () => void;
}) {
    const form = useForm({ akkoord: false });

    function geven(e: FormEvent) {
        e.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onSluit });
    }

    return (
        <Dialog open onOpenChange={(o) => !o && onSluit()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                <form onSubmit={geven} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle className="font-heading">
                            Toestemming voor {a.naam}
                        </DialogTitle>
                        <DialogDescription>
                            Lees wat we wel en niet doen. Je kunt je toestemming
                            altijd intrekken.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3 text-sm">
                        <div>
                            <h3 className="font-semibold">
                                Wat MEBRANDING wel doet
                            </h3>
                            <ul className="mt-1 space-y-1">
                                {tekst.wat.map((t) => (
                                    <li key={t} className="flex gap-2">
                                        <Check
                                            className="mt-0.5 size-4 shrink-0 text-emerald-600"
                                            aria-hidden
                                        />
                                        {t}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <div>
                            <h3 className="font-semibold">
                                Wat MEBRANDING niet doet
                            </h3>
                            <ul className="mt-1 space-y-1">
                                {tekst.niet.map((t) => (
                                    <li key={t} className="flex gap-2">
                                        <X
                                            className="mt-0.5 size-4 shrink-0 text-rood"
                                            aria-hidden
                                        />
                                        {t}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <div>
                            <h3 className="font-semibold">Met wie</h3>
                            <p className="mt-1 text-muted-foreground">
                                {tekst.met_wie}
                            </p>
                        </div>
                        <div>
                            <h3 className="font-semibold">Hoe lang</h3>
                            <p className="mt-1 text-muted-foreground">
                                {tekst.hoe_lang}
                            </p>
                        </div>
                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <ShieldCheck className="size-4" aria-hidden />
                            Toestemmingstekst versie {tekst.versie} (concept,
                            juridische tekst volgt)
                        </p>
                    </div>

                    <label className="flex items-start gap-2 rounded-lg border p-3 text-sm">
                        <input
                            type="checkbox"
                            className="mt-0.5 size-4 accent-violet"
                            checked={form.data.akkoord}
                            onChange={(e) =>
                                form.setData('akkoord', e.target.checked)
                            }
                            data-test="akkoord"
                        />
                        <span>
                            Ik geef MEBRANDING toestemming om deze gegevens van
                            mijn {a.naam}-account te lezen.
                        </span>
                    </label>
                    <InputError message={form.errors.akkoord} />

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={onSluit}
                        >
                            Annuleren
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11 bg-violet hover:bg-violet/90"
                            disabled={form.processing}
                            data-test="toestemming-geven"
                        >
                            Toestemming geven
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
