import { Head, Link, router, usePage } from "@inertiajs/react";
import { Check, ChevronRight, Coins, MapPin, Package } from "lucide-react";
import type { ReactNode } from "react";
import { Button } from "@/components/ui/button";
import { datum } from "@/lib/datum";
import { cn } from "@/lib/utils";
import { aanvragen, overzicht } from "@/routes/ondernemer";
import beheer from "@/routes/ondernemer/beheer";
import type { Auth } from "@/types/auth";
import { Blok, Kop } from "../_onderdelen/opmaak";
import type { Abonnement } from "./_onderdelen/types";

type Stap = "vestiging" | "team" | "aanvraag";

type Props = {
    company: { name: string; available_points: number; locations: string[] };
    subscription: Abonnement | null;
    account_manager: string | null;
    steps: Record<Stap, boolean>;
    closed: boolean;
    can: { team: boolean };
};

export default function Welkom({
    company,
    subscription,
    account_manager,
    steps,
    closed,
    can,
}: Props) {
    const { auth } = usePage().props as unknown as { auth: Auth };
    const voornaam = auth.user.name.split(" ")[0];
    const gedaan = Object.values(steps).filter(Boolean).length;
    const vink = (stap: Stap) =>
        router.post(beheer.welkom.stap.url(stap), {}, { preserveScroll: true });

    return (
        <>
            <Head title="Aan de slag" />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
                <Kop
                    onder={`${company.name} staat klaar. Dit is wat je hebt en wat je nu kunt doen.`}
                >
                    Welkom, {voornaam}
                </Kop>

                <dl className="grid gap-2 sm:grid-cols-3">
                    <Kaart
                        icoon={<Package className="size-4" />}
                        label="Abonnement"
                    >
                        {subscription ? (
                            <>
                                {subscription.name}
                                <span className="block text-xs font-normal text-muted-foreground">
                                    sinds {datum(subscription.starts_on)}
                                </span>
                            </>
                        ) : (
                            "Nog niet ingesteld"
                        )}
                    </Kaart>
                    <Kaart icoon={<Coins className="size-4" />} label="Tegoed">
                        {company.available_points} punten
                        <span className="block text-xs font-normal text-muted-foreground">
                            beschikbaar voor aanvragen
                        </span>
                    </Kaart>
                    <Kaart
                        icoon={<MapPin className="size-4" />}
                        label="Vestigingen"
                    >
                        {company.locations.length} klaargezet
                        <span className="block truncate text-xs font-normal text-muted-foreground">
                            {company.locations.join(", ")}
                        </span>
                    </Kaart>
                </dl>

                <Blok className="space-y-4">
                    <div className="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2 className="font-heading text-lg font-bold">
                                Aan de slag
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Drie korte stappen, samen ongeveer 5 minuten.
                            </p>
                        </div>
                        <span
                            className="text-sm font-semibold"
                            aria-live="polite"
                        >
                            {gedaan} van 3 klaar
                        </span>
                    </div>
                    <div
                        className="h-2 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={3}
                        aria-valuenow={gedaan}
                        aria-label="Voortgang"
                    >
                        <div
                            className="h-full rounded-full bg-brand-gradient transition-all"
                            style={{ width: `${(gedaan / 3) * 100}%` }}
                        />
                    </div>

                    {gedaan === 3 && (
                        <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            Alles staat klaar. Je komt na het inloggen voortaan
                            direct op je overzicht.
                        </p>
                    )}

                    <ol className="space-y-3">
                        <StapRij
                            nummer={1}
                            klaar={steps.vestiging}
                            titel="Vestiging en tijden controleren"
                            tekst="Kloppen het adres, de tijden waarop een creator langs mag komen en wat hij of zij mag ervaren?"
                            actie={
                                <Link href={beheer.vestigingen()}>
                                    Vestigingen bekijken
                                </Link>
                            }
                            afvinken={() => vink("vestiging")}
                            afvinkTekst="Klopt"
                        />
                        <StapRij
                            nummer={2}
                            klaar={steps.team}
                            titel="Team uitnodigen"
                            tekst={
                                can.team
                                    ? "Nodig collega's uit die samenwerkingen aanvragen of meekijken, bijvoorbeeld een bedrijfsleider per vestiging."
                                    : "Wil je dat collega's meekijken? Een eigenaar van je bedrijf kan ze uitnodigen."
                            }
                            actie={
                                can.team ? (
                                    <Link href={beheer.gebruikers()}>
                                        Collega uitnodigen
                                    </Link>
                                ) : undefined
                            }
                            afvinken={() => vink("team")}
                            afvinkTekst="Niet nodig"
                        />
                        <StapRij
                            nummer={3}
                            klaar={steps.aanvraag}
                            titel="Eerste samenwerking aanvragen"
                            tekst={`Vertel wat je wilt laten zien. Wij koppelen een passende creator, meestal binnen 2 werkdagen. Je hebt ${company.available_points} punten.`}
                            actie={
                                <Link href={aanvragen()}>Aanvraag maken</Link>
                            }
                        />
                    </ol>
                </Blok>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {account_manager && (
                        <p className="flex items-center gap-3 text-sm">
                            <span
                                className="grid size-10 place-items-center rounded-full bg-aubergine font-bold text-white"
                                aria-hidden
                            >
                                {account_manager[0]}
                            </span>
                            <span>
                                <span className="block text-muted-foreground">
                                    Je vaste contactpersoon
                                </span>
                                <span className="font-semibold">
                                    {account_manager}
                                </span>
                                , accountmanager
                            </span>
                        </p>
                    )}
                    <div className="flex flex-wrap items-center gap-2">
                        {!closed && (
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    router.post(beheer.welkom.sluiten.url())
                                }
                                data-test="niet-meer-tonen"
                            >
                                Niet meer tonen na inloggen
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={overzicht()} data-test="naar-overzicht">
                                Naar je overzicht <ChevronRight />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

function Kaart({
    icoon,
    label,
    children,
}: {
    icoon: ReactNode;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="min-w-0 rounded-xl border bg-card p-4">
            <dt className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                <span aria-hidden>{icoon}</span>
                {label}
            </dt>
            <dd className="mt-1 font-heading text-lg font-bold">{children}</dd>
        </div>
    );
}

function StapRij({
    nummer,
    klaar,
    titel,
    tekst,
    actie,
    afvinken,
    afvinkTekst,
}: {
    nummer: number;
    klaar: boolean;
    titel: string;
    tekst: string;
    actie?: ReactNode;
    afvinken?: () => void;
    afvinkTekst?: string;
}) {
    return (
        <li
            className={cn(
                "flex gap-3 rounded-xl border p-4",
                klaar && "bg-muted/40",
            )}
            data-test={`stap-${nummer}`}
        >
            <span
                className={cn(
                    "grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold",
                    klaar
                        ? "bg-emerald-600 text-white"
                        : "bg-violet/10 text-violet",
                )}
                aria-hidden
            >
                {klaar ? <Check className="size-4" /> : nummer}
            </span>
            <div className="min-w-0 flex-1">
                <p
                    className={cn(
                        "font-semibold",
                        klaar &&
                            "text-muted-foreground line-through decoration-1",
                    )}
                >
                    {titel}
                    <span className="sr-only">
                        {klaar ? " (klaar)" : " (nog te doen)"}
                    </span>
                </p>
                <p className="text-sm text-muted-foreground">{tekst}</p>
                {!klaar && (actie || afvinken) && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        {actie && (
                            <Button asChild size="sm">
                                {actie}
                            </Button>
                        )}
                        {afvinken && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={afvinken}
                            >
                                <Check /> {afvinkTekst}
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </li>
    );
}

Welkom.layout = {
    breadcrumbs: [{ title: "Aan de slag", href: beheer.welkom() }],
};
