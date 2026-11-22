import { router, useForm, usePage } from '@inertiajs/react';
import { Kaart, TweeKolommen } from '@/components/pagina';
import { Mail, ShieldCheck, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

import type { Actie, Gebruiker } from './types';

const MAG = {
    eigenaar: 'Alles, ook facturen, abonnement en gebruikers',
    lid: 'Aanvragen, meekijken en vestigingen bijwerken',
};

function initialen(naam: string): string {
    return naam
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join('');
}

function verloopt(iso: string): string {
    return new Date(iso).toLocaleDateString('nl-NL', {
        timeZone: 'Europe/Amsterdam',
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

/** Wie van het bedrijf in het portaal mag. Gedeeld door ondernemer (alleen eigenaar beheert) en team. */
export function GebruikersBeheer({
    users,
    me,
    kanBeheren,
    uitnodigen,
    rol,
    verwijderen,
    opnieuw,
}: {
    users: Gebruiker[];
    me?: number;
    kanBeheren: boolean;
    uitnodigen: Actie;
    rol: (id: number) => Actie;
    verwijderen: (id: number) => Actie;
    opnieuw: (id: number) => Actie;
}) {
    const { errors } = usePage().props as unknown as {
        errors: Record<string, string>;
    };
    const eigenaren = users.filter((u) => u.role === 'eigenaar').length;

    return (
        <TweeKolommen
            hoofd={
                <Kaart className="p-0 md:p-0">
                    <div className="px-4 pt-4 md:px-5 md:pt-5">
                        <div className="mb-3 flex items-center justify-between gap-2">
                            <h2 className="font-heading text-lg font-bold">
                                Collega's met toegang
                            </h2>
                            <span className="text-sm text-muted-foreground">
                                {users.length}
                            </span>
                        </div>
                        {errors.rol && (
                            <p
                                role="alert"
                                className="mb-3 rounded-lg bg-rood/10 px-3 py-2 text-sm text-rood"
                            >
                                {errors.rol}
                            </p>
                        )}
                    </div>
                    <ul className="divide-y" data-test="gebruikers">
                        {users.map((u) => (
                            <Rij
                                key={u.id}
                                u={u}
                                jij={u.id === me}
                                kanBeheren={kanBeheren}
                                laatsteEigenaar={
                                    u.role === 'eigenaar' && eigenaren <= 1
                                }
                                rol={rol(u.id)}
                                verwijderen={verwijderen(u.id)}
                                opnieuw={opnieuw(u.id)}
                            />
                        ))}
                    </ul>
                    <p className="border-t px-4 py-3 text-sm text-muted-foreground md:px-5">
                        Er is altijd minstens één eigenaar. Eigenaren zien
                        facturen, wijzigen het abonnement en beheren gebruikers.
                    </p>
                </Kaart>
            }
            zij={
                kanBeheren ? (
                    <Uitnodigen actie={uitnodigen} />
                ) : (
                    <Kaart>
                        <h2 className="mb-3 font-heading text-lg font-bold">
                            Collega uitnodigen
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Alleen een eigenaar kan collega's uitnodigen of
                            rechten wijzigen. Vraag het aan een eigenaar van je
                            bedrijf.
                        </p>
                    </Kaart>
                )
            }
        />
    );
}

function Rij({
    u,
    jij,
    kanBeheren,
    laatsteEigenaar,
    rol,
    verwijderen,
    opnieuw,
}: {
    u: Gebruiker;
    jij: boolean;
    kanBeheren: boolean;
    laatsteEigenaar: boolean;
    rol: Actie;
    verwijderen: Actie;
    opnieuw: Actie;
}) {
    const [zeker, setZeker] = useState(false);
    const actie = (a: Actie, data: Record<string, string> = {}) =>
        router.visit(a.url, { method: a.method, data, preserveScroll: true });

    return (
        <li
            className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center md:px-5"
            data-test="gebruiker"
        >
            <span
                className="grid size-10 shrink-0 place-items-center rounded-full bg-aubergine text-sm font-bold text-white"
                aria-hidden
            >
                {initialen(u.name)}
            </span>
            <div className="min-w-0 flex-1">
                <p className="font-semibold">
                    {u.name}{' '}
                    {jij && (
                        <span className="ml-1 rounded-full bg-violet/10 px-2 py-0.5 text-xs text-violet">
                            Jij
                        </span>
                    )}
                </p>
                <p className="truncate text-sm text-muted-foreground">
                    {u.email}
                </p>
                <p className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    <span>{MAG[u.role]}</span>
                    {u.two_factor && (
                        <span className="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400">
                            <ShieldCheck className="size-3.5" aria-hidden />{' '}
                            Tweestaps aan
                        </span>
                    )}
                    {u.invited_until && (
                        <span className="inline-flex items-center gap-1 text-amber-700 dark:text-amber-400">
                            <Mail className="size-3.5" aria-hidden />{' '}
                            Uitnodiging open tot {verloopt(u.invited_until)}
                        </span>
                    )}
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {kanBeheren ? (
                    <>
                        <label className="sr-only" htmlFor={`rol-${u.id}`}>
                            Rol van {u.name}
                        </label>
                        <select
                            id={`rol-${u.id}`}
                            value={u.role}
                            disabled={laatsteEigenaar}
                            title={
                                laatsteEigenaar
                                    ? 'De laatste eigenaar blijft eigenaar'
                                    : undefined
                            }
                            onChange={(e) =>
                                actie(rol, { role: e.target.value })
                            }
                            className="h-9 rounded-md border border-input bg-background px-2 text-sm disabled:opacity-60"
                        >
                            <option value="eigenaar">Eigenaar</option>
                            <option value="lid">Lid</option>
                        </select>
                        {u.invited_until && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => actie(opnieuw)}
                            >
                                Opnieuw sturen
                            </Button>
                        )}
                        {!laatsteEigenaar &&
                            (zeker ? (
                                <span className="flex gap-1">
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => actie(verwijderen)}
                                        data-test="verwijderen-bevestigen"
                                    >
                                        {jij
                                            ? 'Mezelf verwijderen'
                                            : 'Toegang intrekken'}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setZeker(false)}
                                    >
                                        Toch niet
                                    </Button>
                                </span>
                            ) : (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="text-rood"
                                    onClick={() => setZeker(true)}
                                >
                                    Verwijderen
                                </Button>
                            ))}
                    </>
                ) : (
                    <span
                        className={cn(
                            'rounded-full px-2.5 py-1 text-xs font-semibold',
                            u.role === 'eigenaar'
                                ? 'bg-aubergine text-white'
                                : 'bg-muted',
                        )}
                    >
                        {u.role === 'eigenaar' ? 'Eigenaar' : 'Lid'}
                    </span>
                )}
            </div>
        </li>
    );
}

function Uitnodigen({ actie }: { actie: Actie }) {
    const form = useForm({
        name: '',
        email: '',
        role: 'lid' as 'eigenaar' | 'lid',
    });
    const { data, setData, errors, processing } = form;

    function versturen(e: FormEvent) {
        e.preventDefault();
        form.submit(actie.method, actie.url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Kaart>
            <form
                onSubmit={versturen}
                className="space-y-4"
                noValidate
                data-test="uitnodigen"
            >
                <h2 className="mb-3 font-heading text-lg font-bold">
                    Collega uitnodigen
                </h2>
                <div className="space-y-1.5">
                    <Label htmlFor="inv-email">E-mailadres</Label>
                    <Input
                        id="inv-email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="off"
                    />
                    <InputError message={errors.email} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="inv-name">Naam (optioneel)</Label>
                    <Input
                        id="inv-name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        autoComplete="off"
                    />
                    <InputError message={errors.name} />
                </div>
                <fieldset className="space-y-1.5">
                    <legend className="text-sm font-medium">Rol</legend>
                    <div className="grid grid-cols-2 gap-2">
                        {(['lid', 'eigenaar'] as const).map((r) => (
                            <label
                                key={r}
                                className={cn(
                                    'cursor-pointer rounded-lg border p-3 text-sm',
                                    data.role === r
                                        ? 'border-violet bg-violet/5 ring-1 ring-violet'
                                        : 'hover:border-violet/60',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="role"
                                    value={r}
                                    checked={data.role === r}
                                    onChange={() => setData('role', r)}
                                    className="sr-only"
                                />
                                <span className="block font-semibold">
                                    {r === 'lid' ? 'Lid' : 'Eigenaar'}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {MAG[r]}
                                </span>
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.role} />
                </fieldset>
                <Button type="submit" className="w-full" disabled={processing}>
                    {processing ? <Spinner /> : <UserPlus />} Uitnodiging sturen
                </Button>
                <p className="text-xs text-muted-foreground">
                    De uitnodiging is 7 dagen geldig. Je collega kiest zelf een
                    wachtwoord en kan daarna tweestaps aanzetten.
                </p>
            </form>
        </Kaart>
    );
}
