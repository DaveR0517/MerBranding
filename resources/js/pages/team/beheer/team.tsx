import { Head, Link, router, useForm } from "@inertiajs/react";
import { Check, Minus, ShieldCheck, ShieldOff } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import * as team from "@/routes/team";
import * as beheer from "@/routes/team/beheer";
import {
    deactiveren,
    rollen as rollenRoute,
    uitnodigen,
} from "@/routes/team/beheer/medewerkers";
import {
    KaartRaster,
    Kerncijfer,
    Leeg,
    Pagina,
    PaginaKop,
    TweeKolommen,
    Uitklap,
} from "@/components/pagina";
import {
    Fouten,
    Label,
    Paneel,
    Veld,
    initialen,
    invoer,
} from "../_onderdelen/basis";
import { Aandacht, Keuzes } from "./_onderdelen/beheer";

type Medewerker = {
    id: number;
    name: string;
    email: string;
    rollen: string[];
    tweestaps: boolean;
    email_bevestigd: boolean;
    is_super_admin: boolean;
    is_ik: boolean;
};
type Sjabloon = { name: string; rechten: string[]; aantal: number };
type Recht = { name: string; label: string };
type Props = {
    medewerkers: Medewerker[];
    gedeactiveerd: { id: number; name: string; email: string }[];
    sjablonen: Sjabloon[];
    rechten: Recht[];
    kan_super_admin: boolean;
    tweestaps_verplicht: boolean;
};

const SUPER_ADMIN = "Super Admin";

export default function Team({
    medewerkers,
    gedeactiveerd,
    sjablonen,
    rechten,
    kan_super_admin,
    tweestaps_verplicht,
}: Props) {
    const [filter, setFilter] = useState<string | null>(null);
    const [bewerk, setBewerk] = useState<{
        id: number;
        name: string;
        rollen: string[];
        is_super_admin: boolean;
    } | null>(null);

    const keuzes = [...sjablonen.map((s) => s.name), SUPER_ADMIN];
    const zichtbaar = filter
        ? medewerkers.filter((m) => m.rollen.includes(filter))
        : medewerkers;
    const metTweestaps = medewerkers.filter((m) => m.tweestaps).length;
    const beheerders = medewerkers.filter((m) => m.is_super_admin).length;

    const form = useForm({ name: "", email: "", rollen: [] as string[] });
    const verstuur = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(uitnodigen.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Team en rechten" />
            <Pagina>
                <PaginaKop
                    titel="Team"
                    omschrijving="Medewerkers van MEBRANDING, hun rollen en wat ze mogen. Elke wijziging komt met naam en tijd in het logboek."
                    acties={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={team.logboek.url()}>Logboek</Link>
                            </Button>
                            <Button
                                className="bg-violet text-white hover:bg-violet/90"
                                onClick={() =>
                                    document
                                        .getElementById("uitnodigen")
                                        ?.scrollIntoView({ behavior: "smooth" })
                                }
                            >
                                + Medewerker uitnodigen
                            </Button>
                        </>
                    }
                />

                <Fouten negeer={["name", "email", "rollen"]} />

                <KaartRaster kolommen={4} className="grid-cols-2">
                    <Kerncijfer
                        label="Medewerkers"
                        waarde={medewerkers.length}
                        toelichting={
                            gedeactiveerd.length
                                ? `${gedeactiveerd.length} gedeactiveerd`
                                : "Allemaal actief"
                        }
                    />
                    <Kerncijfer
                        label="Rollen"
                        waarde={sjablonen.length}
                        toelichting="Een medewerker kan er meer hebben"
                    />
                    <Kerncijfer
                        label="Tweestaps"
                        waarde={`${metTweestaps} van ${medewerkers.length}`}
                        toelichting={
                            <Aandacht
                                aan={
                                    metTweestaps < medewerkers.length &&
                                    tweestaps_verplicht
                                }
                            >
                                {tweestaps_verplicht
                                    ? "Verplicht voor het team"
                                    : "Nu niet verplicht (instelling)"}
                            </Aandacht>
                        }
                    />
                    <Kerncijfer
                        label="Super Admins"
                        waarde={beheerders}
                        toelichting={
                            <Aandacht aan={beheerders < 2}>
                                Advies: minstens 2
                            </Aandacht>
                        }
                    />
                </KaartRaster>

                <TweeKolommen
                    hoofd={
                        <>
                            <Paneel titel="Medewerkers">
                                <div
                                    className="mb-3 flex flex-wrap gap-2"
                                    role="group"
                                    aria-label="Filter op rol"
                                >
                                    <FilterKnop
                                        actief={filter === null}
                                        onClick={() => setFilter(null)}
                                    >
                                        Alle{" "}
                                        <span className="opacity-70">
                                            {medewerkers.length}
                                        </span>
                                    </FilterKnop>
                                    {sjablonen
                                        .filter((s) => s.aantal > 0)
                                        .map((s) => (
                                            <FilterKnop
                                                key={s.name}
                                                actief={filter === s.name}
                                                onClick={() =>
                                                    setFilter(s.name)
                                                }
                                            >
                                                {s.name}{" "}
                                                <span className="opacity-70">
                                                    {s.aantal}
                                                </span>
                                            </FilterKnop>
                                        ))}
                                </div>

                                {zichtbaar.length === 0 ? (
                                    <Leeg titel="Niemand met deze rol." />
                                ) : (
                                    <ul
                                        className="divide-y"
                                        data-test="medewerkers"
                                    >
                                        {zichtbaar.map((m) => (
                                            <li
                                                key={m.id}
                                                className="flex flex-wrap items-center gap-3 py-2.5 first:pt-0 last:pb-0 md:flex-nowrap"
                                            >
                                                <span
                                                    className="grid size-10 shrink-0 place-items-center rounded-full bg-brand-gradient font-heading text-sm font-bold text-white"
                                                    aria-hidden
                                                >
                                                    {initialen(m.name)}
                                                </span>
                                                <div className="min-w-0 flex-1 text-sm">
                                                    <p className="font-semibold">
                                                        {m.name}{" "}
                                                        {m.is_ik && (
                                                            <span className="font-normal text-muted-foreground">
                                                                (jij)
                                                            </span>
                                                        )}
                                                    </p>
                                                    <p className="truncate text-muted-foreground">
                                                        {m.email}
                                                    </p>
                                                    <p className="mt-1 flex flex-wrap gap-1">
                                                        {m.rollen.map((r) => (
                                                            <Label
                                                                key={r}
                                                                kleur={
                                                                    r ===
                                                                    SUPER_ADMIN
                                                                        ? "rood"
                                                                        : "violet"
                                                                }
                                                            >
                                                                {r}
                                                            </Label>
                                                        ))}
                                                        {!m.email_bevestigd && (
                                                            <Label kleur="amber">
                                                                E-mail nog niet
                                                                bevestigd
                                                            </Label>
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="flex w-full items-center justify-between gap-3 pl-13 md:w-auto md:pl-0">
                                                    <span
                                                        className={`inline-flex items-center gap-1 text-xs font-semibold ${m.tweestaps ? "text-emerald-700" : "text-rood"}`}
                                                    >
                                                        {m.tweestaps ? (
                                                            <ShieldCheck
                                                                className="size-4"
                                                                aria-hidden
                                                            />
                                                        ) : (
                                                            <ShieldOff
                                                                className="size-4"
                                                                aria-hidden
                                                            />
                                                        )}
                                                        Tweestaps{" "}
                                                        {m.tweestaps
                                                            ? "aan"
                                                            : "uit"}
                                                    </span>
                                                    {!m.is_ik &&
                                                        (kan_super_admin ||
                                                            !m.is_super_admin) && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setBewerk(m)
                                                                }
                                                                aria-label={`${m.name} beheren`}
                                                            >
                                                                Beheren
                                                            </Button>
                                                        )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Paneel>

                            <Uitklap titel="Rechten per rol">
                                <p className="mb-3 text-sm text-muted-foreground">
                                    Een medewerker krijgt de optelsom van zijn
                                    rollen. Een Super Admin mag alles.
                                </p>
                                <RechtenMatrix
                                    sjablonen={sjablonen}
                                    rechten={rechten}
                                />
                            </Uitklap>
                        </>
                    }
                    zij={
                        <>
                            <Paneel
                                id="uitnodigen"
                                titel="Medewerker uitnodigen"
                                sub="De medewerker krijgt een e-mail om zelf een wachtwoord te kiezen en zet daarna tweestapsverificatie aan."
                            >
                                <form
                                    onSubmit={verstuur}
                                    className="space-y-3"
                                    data-test="uitnodigen"
                                >
                                    <Veld
                                        label="Naam"
                                        htmlFor="m-naam"
                                        fout={form.errors.name}
                                    >
                                        <input
                                            id="m-naam"
                                            required
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    "name",
                                                    e.target.value,
                                                )
                                            }
                                            className={invoer}
                                        />
                                    </Veld>
                                    <Veld
                                        label="E-mailadres"
                                        htmlFor="m-email"
                                        fout={form.errors.email}
                                    >
                                        <input
                                            id="m-email"
                                            type="email"
                                            required
                                            value={form.data.email}
                                            onChange={(e) =>
                                                form.setData(
                                                    "email",
                                                    e.target.value,
                                                )
                                            }
                                            className={invoer}
                                        />
                                    </Veld>
                                    <fieldset className="space-y-1.5">
                                        <legend className="text-sm font-semibold">
                                            Rollen
                                        </legend>
                                        <Keuzes
                                            naam="m-rol"
                                            opties={keuzes}
                                            gekozen={form.data.rollen}
                                            onChange={(r) =>
                                                form.setData("rollen", r)
                                            }
                                            uit={
                                                kan_super_admin
                                                    ? []
                                                    : [SUPER_ADMIN]
                                            }
                                            uitleg={{
                                                [SUPER_ADMIN]: kan_super_admin
                                                    ? "Mag alles, ook rechten en instellingen"
                                                    : "Alleen een Super Admin geeft deze rol",
                                            }}
                                        />
                                        {form.errors.rollen && (
                                            <p className="text-sm text-rood">
                                                {form.errors.rollen}
                                            </p>
                                        )}
                                    </fieldset>
                                    <Button
                                        type="submit"
                                        className="w-full bg-violet text-white hover:bg-violet/90"
                                        disabled={form.processing}
                                    >
                                        {form.processing
                                            ? "Bezig..."
                                            : "Uitnodiging sturen"}
                                    </Button>
                                </form>
                            </Paneel>

                            {gedeactiveerd.length > 0 && (
                                <Paneel
                                    titel="Gedeactiveerd"
                                    sub="Geen toegang meer tot het teamportaal. Account en gegevens blijven bewaard."
                                >
                                    <ul className="divide-y">
                                        {gedeactiveerd.map((g) => (
                                            <li
                                                key={g.id}
                                                className="flex items-center justify-between gap-3 py-2.5 text-sm"
                                            >
                                                <span className="min-w-0">
                                                    <span className="block font-semibold">
                                                        {g.name}
                                                    </span>
                                                    <span className="block truncate text-muted-foreground">
                                                        {g.email}
                                                    </span>
                                                </span>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setBewerk({
                                                            ...g,
                                                            rollen: [],
                                                            is_super_admin: false,
                                                        })
                                                    }
                                                >
                                                    Toegang geven
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                </Paneel>
                            )}
                        </>
                    }
                />
            </Pagina>

            {bewerk && (
                <Bewerken
                    key={bewerk.id}
                    medewerker={bewerk}
                    keuzes={keuzes}
                    kanSuperAdmin={kan_super_admin}
                    actief={medewerkers.some((m) => m.id === bewerk.id)}
                    sluit={() => setBewerk(null)}
                />
            )}
        </>
    );
}

function FilterKnop({
    actief,
    onClick,
    children,
}: {
    actief: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={actief}
            onClick={onClick}
            className={`rounded-full border px-3 py-1 text-sm font-semibold ${actief ? "border-violet bg-violet text-white" : "bg-background hover:border-violet"}`}
        >
            {children}
        </button>
    );
}

function Bewerken({
    medewerker,
    keuzes,
    kanSuperAdmin,
    actief,
    sluit,
}: {
    medewerker: { id: number; name: string; rollen: string[] };
    keuzes: string[];
    kanSuperAdmin: boolean;
    actief: boolean;
    sluit: () => void;
}) {
    const form = useForm({ rollen: medewerker.rollen });
    const [zeker, setZeker] = useState(false);

    const opslaan = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(rollenRoute.url(medewerker.id), {
            preserveScroll: true,
            onSuccess: sluit,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && sluit()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-heading">
                        {medewerker.name}
                    </DialogTitle>
                    <DialogDescription>
                        Kies de rollen. De medewerker krijgt een e-mail met de
                        nieuwe rollen en de wijziging staat in het logboek.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={opslaan}
                    className="space-y-4"
                    data-test="rollen-wijzigen"
                >
                    <fieldset>
                        <legend className="sr-only">Rollen</legend>
                        <Keuzes
                            naam={`r-${medewerker.id}`}
                            opties={keuzes}
                            gekozen={form.data.rollen}
                            onChange={(r) => form.setData("rollen", r)}
                            uit={kanSuperAdmin ? [] : [SUPER_ADMIN]}
                        />
                    </fieldset>
                    {form.errors.rollen && (
                        <p className="text-sm text-rood">
                            {form.errors.rollen}
                        </p>
                    )}
                    <DialogFooter className="gap-2 sm:justify-between">
                        {actief &&
                            (zeker ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="border-rood text-rood"
                                    onClick={() =>
                                        router.post(
                                            deactiveren.url(medewerker.id),
                                            {},
                                            {
                                                preserveScroll: true,
                                                onSuccess: sluit,
                                            },
                                        )
                                    }
                                >
                                    Ja, deactiveren
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setZeker(true)}
                                >
                                    Deactiveren
                                </Button>
                            ))}
                        <Button
                            type="submit"
                            className="bg-violet text-white hover:bg-violet/90"
                            disabled={
                                form.processing || form.data.rollen.length === 0
                            }
                        >
                            Rollen opslaan
                        </Button>
                    </DialogFooter>
                    {zeker && (
                        <p className="text-sm text-muted-foreground">
                            Deactiveren haalt alle teamrollen weg. Het account
                            en alles wat {medewerker.name} deed, blijft bewaard.
                        </p>
                    )}
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Groot scherm: tabel rechten tegen rollen. Telefoon: per rol een uitklaplijst. */
function RechtenMatrix({
    sjablonen,
    rechten,
}: {
    sjablonen: Sjabloon[];
    rechten: Recht[];
}) {
    const label = (naam: string) =>
        rechten.find((r) => r.name === naam)?.label ?? naam;

    return (
        <>
            <div className="space-y-2 lg:hidden">
                {sjablonen.map((s) => (
                    <details key={s.name} className="rounded-xl border p-3">
                        <summary className="cursor-pointer text-sm font-semibold">
                            {s.name}{" "}
                            <span className="font-normal text-muted-foreground">
                                ({s.rechten.length} rechten)
                            </span>
                        </summary>
                        <ul className="mt-2 space-y-1 text-sm">
                            {s.rechten.map((r) => (
                                <li key={r} className="flex items-center gap-2">
                                    <Check
                                        className="size-4 text-violet"
                                        aria-hidden
                                    />{" "}
                                    {label(r)}
                                </li>
                            ))}
                        </ul>
                    </details>
                ))}
            </div>
            <table
                className="hidden w-full text-sm lg:table"
                data-test="rechtenmatrix"
            >
                <thead>
                    <tr className="text-left text-xs text-muted-foreground">
                        <th className="py-2 pr-2 font-semibold">Recht</th>
                        {sjablonen.map((s) => (
                            <th
                                key={s.name}
                                className="px-1 py-2 text-center font-semibold"
                            >
                                <span
                                    className="block max-w-20 truncate"
                                    title={s.name}
                                >
                                    {s.name}
                                </span>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rechten.map((r) => (
                        <tr key={r.name}>
                            <td className="py-1.5 pr-2">{r.label}</td>
                            {sjablonen.map((s) => (
                                <td
                                    key={s.name}
                                    className="px-1 py-1.5 text-center"
                                >
                                    {s.rechten.includes(r.name) ? (
                                        <Check
                                            className="mx-auto size-4 text-violet"
                                            aria-label="Ja"
                                        />
                                    ) : (
                                        <Minus
                                            className="mx-auto size-4 text-muted-foreground/40"
                                            aria-label="Nee"
                                        />
                                    )}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </>
    );
}

Team.layout = {
    breadcrumbs: [{ title: "Team en rechten", href: beheer.medewerkers.url() }],
};
