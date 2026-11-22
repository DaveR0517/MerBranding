import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Check, MessageSquareWarning, X } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { datumTijd } from "@/lib/datum";
import * as screening from "@/routes/team/screening";
import {
    Fouten,
    Kop,
    Label,
    Pagina,
    Paneel,
    Veld,
    gebeurtenis,
    invoer,
} from "../_onderdelen/basis";
import type { Aanmelding } from "./_gedeeld";
import { platformKort, soortNaam } from "./_gedeeld";

type Props = {
    lead: Aanmelding & {
        phone: string | null;
        message: string | null;
        consent_at: string | null;
        consent_version: string | null;
        data: { key: string; value: string }[];
    };
    decision: {
        decision: string;
        reason: string | null;
        level: string | null;
        by: string | null;
        at: string | null;
    } | null;
    history: {
        event: string;
        reason: string | null;
        by: string | null;
        at: string | null;
    }[];
    levels: { value: string; name: string }[];
    open: boolean;
};

type Keuze = "goedkeuren" | "aanvulling" | "afwijzen";

const statusLabel: Record<
    string,
    [string, "violet" | "amber" | "groen" | "rood"]
> = {
    nieuw: ["Nieuw", "violet"],
    aanvulling: ["Aanvulling gevraagd", "amber"],
    creator: ["Goedgekeurd", "groen"],
    afgewezen: ["Afgewezen", "rood"],
};

const besluitNaam: Record<string, string> = {
    screening_goedgekeurd: "Goedgekeurd",
    screening_aanvulling: "Aanvulling gevraagd",
    screening_afgewezen: "Afgewezen",
};

/** Leesbare naam van een formulierveld van de website. */
function veldNaam(sleutel: string): string {
    const namen: Record<string, string> = {
        soort: "Soort",
        straal: "Werkgebied (km)",
        niches: "Niches",
        geboortedatum: "Geboortedatum",
        certificaat: "Academy-certificaat",
    };
    const kanaal = sleutel.match(/^(\w+)-(naam|volgers|zakelijk)$/);
    if (kanaal) {
        const merken: Record<string, string> = {
            tiktok: "TikTok",
            youtube: "YouTube",
            instagram: "Instagram",
            facebook: "Facebook",
            snapchat: "Snapchat",
        };
        const platform = merken[kanaal[1]] ?? kanaal[1];

        return `${platform}: ${{ naam: "gebruikersnaam", volgers: "volgers", zakelijk: "zakelijk account" }[kanaal[2]]}`;
    }

    return namen[sleutel] ?? sleutel.replaceAll("_", " ").replaceAll("-", " ");
}

/** Detail van één aanmelding met alle velden en de drie besluiten (ontwerp AdminCreators, screening). */
export default function Detail({
    lead,
    decision,
    history,
    levels,
    open,
}: Props) {
    const [keuze, setKeuze] = useState<Keuze | null>(null);
    const [status, kleur] = statusLabel[lead.status] ?? [lead.status, "violet"];
    const zakelijk = lead.channels.some((k) => k.business);

    return (
        <>
            <Head title={`Screening ${lead.name}`} />
            <Pagina>
                <Link
                    href={screening.index.url()}
                    className="inline-flex items-center gap-1 text-sm font-semibold text-violet hover:underline"
                >
                    <ArrowLeft className="size-4" aria-hidden />
                    Alle aanmeldingen
                </Link>
                <Kop
                    titel={lead.name}
                    sub={
                        <span className="flex flex-wrap items-center gap-1.5">
                            <Label kleur={kleur}>{status}</Label>
                            {lead.kind && (
                                <Label>
                                    {soortNaam[lead.kind] ?? lead.kind}
                                </Label>
                            )}
                            <span>
                                {[
                                    lead.city,
                                    `aangemeld ${datumTijd(lead.created_at)}`,
                                ]
                                    .filter(Boolean)
                                    .join(", ")}
                            </span>
                        </span>
                    }
                />

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
                    <div className="space-y-5">
                        <Paneel titel="Contact">
                            <dl className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[10rem_1fr]">
                                <dt className="text-muted-foreground">
                                    E-mail
                                </dt>
                                <dd className="break-all">{lead.email}</dd>
                                <dt className="text-muted-foreground">
                                    Telefoon
                                </dt>
                                <dd>{lead.phone ?? "Niet opgegeven"}</dd>
                                <dt className="text-muted-foreground">
                                    Woonplaats
                                </dt>
                                <dd>{lead.city ?? "Niet opgegeven"}</dd>
                                <dt className="text-muted-foreground">
                                    Toestemming
                                </dt>
                                <dd>
                                    {lead.consent_at
                                        ? `${datumTijd(lead.consent_at)}, privacytekst versie ${lead.consent_version ?? "onbekend"}`
                                        : "Niet vastgelegd"}
                                </dd>
                            </dl>
                            {lead.message && (
                                <p className="mt-3 rounded-xl bg-muted p-3 text-sm whitespace-pre-line">
                                    {lead.message}
                                </p>
                            )}
                        </Paneel>

                        <Paneel
                            titel="Kanalen"
                            sub="Echt, actief en een professioneel account? Koppelen via de API volgt; nu bekijk je het profiel zelf."
                        >
                            {lead.channels.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Geen kanalen opgegeven.
                                </p>
                            ) : (
                                <ul className="divide-y text-sm">
                                    {lead.channels.map((k) => (
                                        <li
                                            key={k.platform}
                                            className="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0"
                                        >
                                            <span>
                                                <span className="font-semibold">
                                                    {platformKort[k.platform] ??
                                                        k.platform}
                                                </span>{" "}
                                                {k.handle}
                                                {k.followers ? (
                                                    <span className="text-muted-foreground">
                                                        , {k.followers} volgers
                                                        (opgave)
                                                    </span>
                                                ) : null}
                                            </span>
                                            <Label
                                                kleur={
                                                    k.business
                                                        ? "groen"
                                                        : "amber"
                                                }
                                            >
                                                {k.business
                                                    ? "Zakelijk account"
                                                    : "Niet zakelijk opgegeven"}
                                            </Label>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {!zakelijk && lead.channels.length > 0 && (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Een zakelijk of creatoraccount is nodig om
                                    statistieken op te halen bij influencers.
                                    Voor UGC-makers tellen volgers niet mee,
                                    daar beslist het voorbeeldwerk.
                                </p>
                            )}
                        </Paneel>

                        <Paneel titel="Alle velden uit het formulier">
                            {lead.data.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Geen extra velden.
                                </p>
                            ) : (
                                <dl
                                    className="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-[12rem_1fr]"
                                    data-test="velden"
                                >
                                    {lead.data.map((v) => (
                                        <div key={v.key} className="contents">
                                            <dt className="text-muted-foreground">
                                                {veldNaam(v.key)}
                                            </dt>
                                            <dd className="break-words">
                                                {v.value || "leeg"}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </Paneel>

                        <Paneel titel="Wat we bekijken">
                            <ul className="list-inside list-disc space-y-1 text-sm">
                                <li>
                                    Profiel en leeftijd: compleet, 18 jaar of
                                    ouder
                                </li>
                                <li>
                                    Accounts: echt, actief en een professioneel
                                    account
                                </li>
                                <li>
                                    Voorbeeldwerk: beeld, geluid, montage en of
                                    de briefing gevolgd is
                                </li>
                                <li>
                                    UGC-maker zonder academy: eerst een
                                    proefopdracht (voorstel)
                                </li>
                            </ul>
                        </Paneel>
                    </div>

                    <div className="space-y-5">
                        {decision && (
                            <Paneel titel="Laatste besluit">
                                <p className="text-sm">
                                    <span className="font-semibold">
                                        {besluitNaam[
                                            `screening_${decision.decision}`
                                        ] ?? decision.decision}
                                    </span>
                                    {decision.level
                                        ? ` op ${decision.level}`
                                        : ""}
                                    , door {decision.by ?? "onbekend"} op{" "}
                                    {datumTijd(decision.at)}
                                </p>
                                {decision.reason && (
                                    <p className="mt-2 rounded-xl bg-muted p-3 text-sm whitespace-pre-line">
                                        {decision.reason}
                                    </p>
                                )}
                            </Paneel>
                        )}

                        {open && (
                            <Paneel
                                titel="Besluit"
                                sub="Altijd een menselijke beslissing, met reden. De reden komt in het logboek."
                            >
                                <Fouten negeer={["level", "reason"]} />
                                <div
                                    className="grid gap-2"
                                    role="group"
                                    aria-label="Kies een besluit"
                                >
                                    <Button
                                        variant={
                                            keuze === "goedkeuren"
                                                ? "default"
                                                : "outline"
                                        }
                                        className="justify-start"
                                        onClick={() => setKeuze("goedkeuren")}
                                        aria-pressed={keuze === "goedkeuren"}
                                    >
                                        <Check className="size-4" aria-hidden />
                                        Goedkeuren
                                    </Button>
                                    <Button
                                        variant={
                                            keuze === "aanvulling"
                                                ? "default"
                                                : "outline"
                                        }
                                        className="justify-start"
                                        onClick={() => setKeuze("aanvulling")}
                                        aria-pressed={keuze === "aanvulling"}
                                    >
                                        <MessageSquareWarning
                                            className="size-4"
                                            aria-hidden
                                        />
                                        Meer informatie vragen
                                    </Button>
                                    <Button
                                        variant={
                                            keuze === "afwijzen"
                                                ? "default"
                                                : "outline"
                                        }
                                        className="justify-start"
                                        onClick={() => setKeuze("afwijzen")}
                                        aria-pressed={keuze === "afwijzen"}
                                    >
                                        <X className="size-4" aria-hidden />
                                        Afwijzen
                                    </Button>
                                </div>
                                {keuze && (
                                    <BesluitForm
                                        key={keuze}
                                        keuze={keuze}
                                        leadId={lead.id}
                                        levels={levels}
                                    />
                                )}
                            </Paneel>
                        )}

                        <Paneel titel="Logboek">
                            {history.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nog geen besluiten.
                                </p>
                            ) : (
                                <ol className="space-y-2 text-sm">
                                    {history.map((h, i) => (
                                        <li key={i}>
                                            <p className="font-semibold">
                                                {besluitNaam[h.event] ??
                                                    gebeurtenis(h.event)}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {h.by ?? "Systeem"},{" "}
                                                {datumTijd(h.at)}
                                            </p>
                                            {h.reason && (
                                                <p className="mt-0.5">
                                                    {h.reason}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </Paneel>
                    </div>
                </div>
            </Pagina>
        </>
    );
}

function BesluitForm({
    keuze,
    leadId,
    levels,
}: {
    keuze: Keuze;
    leadId: number;
    levels: Props["levels"];
}) {
    const form = useForm({ level: levels[0]?.value ?? "bronze", reason: "" });
    const tekst: Record<Keuze, { label: string; hint: string; knop: string }> =
        {
            goedkeuren: {
                label: "Reden van goedkeuring",
                hint: "Maakt een account, zet de creator op actief en stuurt de welkomstmail met een link om een wachtwoord te kiezen.",
                knop: "Goedkeuren en uitnodigen",
            },
            aanvulling: {
                label: "Wat ontbreekt er?",
                hint: 'Deze tekst gaat letterlijk in de e-mail "We missen nog iets" naar de aanmelder.',
                knop: "Aanvulling vragen",
            },
            afwijzen: {
                label: "Reden van afwijzing",
                hint: "De aanmelder krijgt deze reden per e-mail, met de mogelijkheid om over 90 dagen opnieuw aan te melden.",
                knop: "Afwijzen",
            },
        };
    const route = {
        goedkeuren: screening.goedkeuren,
        aanvulling: screening.aanvulling,
        afwijzen: screening.afwijzen,
    }[keuze];

    return (
        <form
            className="mt-4 space-y-3 border-t pt-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route.url(leadId), { preserveScroll: true });
            }}
            data-test={`besluit-${keuze}`}
        >
            {keuze === "goedkeuren" && (
                <Veld
                    label="Startniveau"
                    htmlFor="level"
                    fout={form.errors.level}
                    hint="Standaard Bronze. Startscore volgens het gemiddelde voor nieuwe creators."
                >
                    <select
                        id="level"
                        value={form.data.level}
                        onChange={(e) => form.setData("level", e.target.value)}
                        className={invoer}
                    >
                        {levels.map((l) => (
                            <option key={l.value} value={l.value}>
                                {l.name}
                            </option>
                        ))}
                    </select>
                </Veld>
            )}
            <Veld
                label={tekst[keuze].label}
                htmlFor="reason"
                fout={form.errors.reason}
                hint={tekst[keuze].hint}
            >
                <textarea
                    id="reason"
                    required
                    rows={4}
                    value={form.data.reason}
                    onChange={(e) => form.setData("reason", e.target.value)}
                    className={`${invoer} h-auto py-2`}
                />
            </Veld>
            <Button
                type="submit"
                disabled={form.processing}
                variant={keuze === "afwijzen" ? "destructive" : "default"}
                className="w-full"
            >
                {tekst[keuze].knop}
            </Button>
        </form>
    );
}

Detail.layout = {
    breadcrumbs: [{ title: "Screening", href: screening.index.url() }],
};
