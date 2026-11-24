import { Head, useForm } from "@inertiajs/react";
import { Check, Sparkles, UserRoundCheck, Wine } from "lucide-react";
import type { FormEvent } from "react";
import InputError from "@/components/input-error";
import { Kaart, Pagina, PaginaKop, TweeKolommen } from "@/components/pagina";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { cn } from "@/lib/utils";
import { aanvragen, opslaan, overzicht } from "@/routes/ondernemer";
import { CreatorKiezen, blokkade } from "./_onderdelen/creator-kiezen";
import type { KiesbareCreator } from "./_onderdelen/creator-kiezen";
import { Tekstvak } from "./_onderdelen/opmaak";
import { Tegoed } from "./_onderdelen/tegoed";

type Niveau = { value: string; name: string; points: number; reward: string };

type Props = {
    available_points: number;
    locations: { id: number; name: string; city: string | null }[];
    levels: Niveau[];
    creators: KiesbareCreator[];
};

type Velden = {
    location_id: string;
    title: string;
    briefing: string;
    period_from: string;
    period_to: string;
    alcohol: "" | "1" | "0";
    creator_level: string;
    /** team: MEBRANDING kiest de creator; zelf: de ondernemer kiest en de uitnodiging gaat direct. */
    keuze: "team" | "zelf";
    creator_id: number | null;
};

export default function Aanvragen({
    available_points,
    locations,
    levels,
    creators,
}: Props) {
    const form = useForm<Velden>({
        location_id: locations.length === 1 ? String(locations[0].id) : "",
        title: "",
        briefing: "",
        period_from: "",
        period_to: "",
        alcohol: "",
        creator_level: "",
        keuze: "team",
        creator_id: null,
    });
    const { data, setData, errors, processing } = form;
    const zelf = data.keuze === "zelf";
    const creator = zelf
        ? creators.find((c) => c.id === data.creator_id)
        : undefined;
    // Zelf gekozen: het niveau en de prijs zijn die van de creator.
    const gekozen = zelf
        ? creator && {
              name: `${creator.name} (${creator.level})`,
              points: creator.points,
          }
        : levels.find((l) => l.value === data.creator_level);
    const genoeg = !gekozen || gekozen.points <= available_points;
    const stad =
        locations.find((l) => String(l.id) === data.location_id)?.city ?? null;
    const fouten = errors as Partial<Record<string, string>>;

    // Verandert alcohol of tegoed waardoor de gekozen creator niet meer kan: keuze vervalt.
    function zetAlcohol(waarde: "1" | "0") {
        setData((d) => ({
            ...d,
            alcohol: waarde,
            creator_id:
                creator && blokkade(creator, waarde === "1", available_points)
                    ? null
                    : d.creator_id,
        }));
    }
    const vandaag = new Date().toLocaleDateString("sv-SE", {
        timeZone: "Europe/Amsterdam",
    });

    function versturen(e: FormEvent) {
        e.preventDefault();
        form.post(opslaan.url(), { preserveScroll: true });
    }

    return (
        <>
            <Head title="Samenwerking aanvragen" />
            <Pagina>
                <PaginaKop
                    titel="Samenwerking aanvragen"
                    omschrijving="Vertel wat je wilt laten zien. Wij zoeken de creator die bij je zaak past, of je kiest er zelf een."
                />
                <p className="rounded-lg bg-violet/10 px-3 py-2 text-sm text-violet @4xl:hidden">
                    Je hebt <strong>{available_points} punten</strong>{" "}
                    beschikbaar.
                </p>

                <form onSubmit={versturen} noValidate>
                    <TweeKolommen
                        hoofd={
                            <>
                                <Kaart className="space-y-5">
                                    <h2 className="font-heading text-lg font-bold">
                                        Wat wil je laten zien?
                                    </h2>

                                    <div className="grid gap-2">
                                        <Label htmlFor="location_id">
                                            Vestiging
                                        </Label>
                                        <Select
                                            value={data.location_id}
                                            onValueChange={(v) =>
                                                setData("location_id", v)
                                            }
                                        >
                                            <SelectTrigger
                                                id="location_id"
                                                className="w-full"
                                                aria-invalid={
                                                    !!errors.location_id
                                                }
                                            >
                                                <SelectValue placeholder="Kies een vestiging" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {locations.map((l) => (
                                                    <SelectItem
                                                        key={l.id}
                                                        value={String(l.id)}
                                                    >
                                                        {l.name}
                                                        {l.city
                                                            ? `, ${l.city}`
                                                            : ""}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.location_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="title">Titel</Label>
                                        <Input
                                            id="title"
                                            value={data.title}
                                            maxLength={120}
                                            onChange={(e) =>
                                                setData("title", e.target.value)
                                            }
                                            placeholder="Bijvoorbeeld: Wintersoep en appeltaart"
                                            aria-invalid={!!errors.title}
                                        />
                                        <InputError message={errors.title} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="briefing">
                                            Briefing
                                        </Label>
                                        <Tekstvak
                                            id="briefing"
                                            value={data.briefing}
                                            maxLength={5000}
                                            rows={6}
                                            onChange={(e) =>
                                                setData(
                                                    "briefing",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Wat moet erin? Welk gerecht of product, welke sfeer, wat moet de kijker onthouden?"
                                            aria-invalid={!!errors.briefing}
                                            aria-describedby="briefing-uitleg"
                                        />
                                        <p
                                            id="briefing-uitleg"
                                            className="text-xs text-muted-foreground"
                                        >
                                            De creator ziet je bedrijfsnaam, de
                                            vestiging en deze briefing.
                                        </p>
                                        <InputError message={errors.briefing} />
                                    </div>

                                    <fieldset className="grid gap-2">
                                        <legend className="mb-2 text-sm font-medium">
                                            Periode (optioneel)
                                        </legend>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor="period_from"
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Van
                                                </Label>
                                                <Input
                                                    id="period_from"
                                                    type="date"
                                                    min={vandaag}
                                                    value={data.period_from}
                                                    onChange={(e) =>
                                                        setData(
                                                            "period_from",
                                                            e.target.value,
                                                        )
                                                    }
                                                    aria-invalid={
                                                        !!errors.period_from
                                                    }
                                                />
                                                <InputError
                                                    message={errors.period_from}
                                                />
                                            </div>
                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor="period_to"
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Tot en met
                                                </Label>
                                                <Input
                                                    id="period_to"
                                                    type="date"
                                                    min={
                                                        data.period_from ||
                                                        vandaag
                                                    }
                                                    value={data.period_to}
                                                    onChange={(e) =>
                                                        setData(
                                                            "period_to",
                                                            e.target.value,
                                                        )
                                                    }
                                                    aria-invalid={
                                                        !!errors.period_to
                                                    }
                                                />
                                                <InputError
                                                    message={errors.period_to}
                                                />
                                            </div>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Leeg laten als het niet uitmaakt
                                            wanneer de creator komt.
                                        </p>
                                    </fieldset>
                                </Kaart>

                                <Kaart>
                                    <fieldset aria-describedby="alcohol-uitleg">
                                        <legend className="font-heading text-lg font-bold">
                                            Komt alcohol in beeld?
                                        </legend>
                                        <p
                                            id="alcohol-uitleg"
                                            className="mt-1 flex gap-2 text-sm text-muted-foreground"
                                        >
                                            <Wine
                                                className="mt-0.5 size-4 shrink-0"
                                                aria-hidden
                                            />
                                            Bij ja werken we alleen met creators
                                            van 25 jaar of ouder, plaatsen we
                                            niet op TikTok en staat het
                                            NIX18-logo in beeld.
                                        </p>
                                        <div className="mt-3 grid grid-cols-2 gap-3 sm:max-w-sm">
                                            {(
                                                [
                                                    ["1", "Ja"],
                                                    ["0", "Nee"],
                                                ] as const
                                            ).map(([waarde, tekst]) => (
                                                <Keuze
                                                    key={waarde}
                                                    naam="alcohol"
                                                    gekozen={
                                                        data.alcohol === waarde
                                                    }
                                                    onKies={() =>
                                                        zetAlcohol(waarde)
                                                    }
                                                >
                                                    {tekst}
                                                </Keuze>
                                            ))}
                                        </div>
                                        <InputError
                                            message={errors.alcohol}
                                            className="mt-2"
                                        />
                                    </fieldset>
                                </Kaart>

                                <Kaart>
                                    <fieldset>
                                        <legend className="font-heading text-lg font-bold">
                                            Wie kiest de creator?
                                        </legend>
                                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                            <Keuze
                                                naam="keuze"
                                                gekozen={!zelf}
                                                onKies={() =>
                                                    setData((d) => ({
                                                        ...d,
                                                        keuze: "team",
                                                        creator_id: null,
                                                    }))
                                                }
                                                className="items-start"
                                            >
                                                <span className="flex items-center gap-2 font-heading text-base font-bold">
                                                    <Sparkles
                                                        className="size-4 text-violet"
                                                        aria-hidden
                                                    />
                                                    MEBRANDING kiest
                                                </span>
                                                <span className="mt-1 block text-sm font-normal text-muted-foreground">
                                                    Wij zoeken binnen 2
                                                    werkdagen de creator die
                                                    past bij je briefing.
                                                    Aanbevolen.
                                                </span>
                                            </Keuze>
                                            <Keuze
                                                naam="keuze"
                                                gekozen={zelf}
                                                onKies={() =>
                                                    setData((d) => ({
                                                        ...d,
                                                        keuze: "zelf",
                                                        creator_level: "",
                                                    }))
                                                }
                                                className="items-start"
                                            >
                                                <span className="flex items-center gap-2 font-heading text-base font-bold">
                                                    <UserRoundCheck
                                                        className="size-4 text-violet"
                                                        aria-hidden
                                                    />
                                                    Ik kies zelf
                                                </span>
                                                <span className="mt-1 block text-sm font-normal text-muted-foreground">
                                                    Je kiest een creator en die
                                                    krijgt direct de
                                                    uitnodiging. Zegt die nee,
                                                    dan zoeken wij iemand
                                                    anders.
                                                </span>
                                            </Keuze>
                                        </div>
                                    </fieldset>
                                    {zelf && (
                                        <div className="mt-4">
                                            <CreatorKiezen
                                                creators={creators}
                                                gekozen={data.creator_id}
                                                onKies={(id) =>
                                                    setData("creator_id", id)
                                                }
                                                alcohol={data.alcohol === "1"}
                                                stad={stad}
                                                beschikbaar={available_points}
                                            />
                                            <InputError
                                                message={
                                                    fouten.creator_id ??
                                                    fouten.creator
                                                }
                                                className="mt-2"
                                            />
                                        </div>
                                    )}
                                </Kaart>

                                {!zelf && (
                                    <Kaart>
                                        <fieldset>
                                            <legend className="font-heading text-lg font-bold">
                                                Niveau van de creator
                                            </legend>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Hoe hoger het niveau, hoe groter
                                                het bereik van de creator. Het
                                                niveau bepaalt hoeveel punten de
                                                samenwerking kost.
                                            </p>
                                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                                {levels.map((l) => {
                                                    const teDuur =
                                                        l.points >
                                                        available_points;

                                                    return (
                                                        <Keuze
                                                            key={l.value}
                                                            naam="creator_level"
                                                            gekozen={
                                                                data.creator_level ===
                                                                l.value
                                                            }
                                                            onKies={() =>
                                                                setData(
                                                                    "creator_level",
                                                                    l.value,
                                                                )
                                                            }
                                                            className="items-start"
                                                        >
                                                            <span className="flex w-full items-baseline justify-between gap-2">
                                                                <span className="font-heading text-base font-bold">
                                                                    {l.name}
                                                                </span>
                                                                <span className="font-semibold whitespace-nowrap text-violet">
                                                                    {l.points}{" "}
                                                                    punten
                                                                </span>
                                                            </span>
                                                            <span className="mt-1 block text-sm font-normal text-muted-foreground">
                                                                {l.reward}
                                                            </span>
                                                            {teDuur && (
                                                                <span className="mt-1 block text-xs font-semibold text-rood">
                                                                    Te weinig
                                                                    tegoed
                                                                </span>
                                                            )}
                                                        </Keuze>
                                                    );
                                                })}
                                            </div>
                                            <InputError
                                                message={errors.creator_level}
                                                className="mt-2"
                                            />
                                        </fieldset>
                                    </Kaart>
                                )}
                            </>
                        }
                        zij={
                            <div className="space-y-4 @4xl:sticky @4xl:top-4">
                                <Tegoed beschikbaar={available_points}>
                                    <div
                                        className="mt-4 rounded-lg bg-white/10 p-3 text-sm"
                                        aria-live="polite"
                                    >
                                        {!gekozen && (
                                            <p>
                                                {zelf
                                                    ? "Kies een creator om te zien wat deze samenwerking kost."
                                                    : "Kies een niveau om te zien wat deze samenwerking kost."}
                                            </p>
                                        )}
                                        {gekozen && genoeg && (
                                            <p>
                                                <Check
                                                    className="mr-1 inline size-4"
                                                    aria-hidden
                                                />
                                                {gekozen.name} kost{" "}
                                                {gekozen.points} punten. Na
                                                versturen heb je nog{" "}
                                                {available_points -
                                                    gekozen.points}{" "}
                                                beschikbaar.
                                            </p>
                                        )}
                                        {gekozen && !genoeg && (
                                            <p className="font-semibold">
                                                {gekozen.name} kost{" "}
                                                {gekozen.points} punten, je hebt
                                                er {available_points}. Kies een
                                                lager niveau of neem contact op
                                                met je accountmanager.
                                            </p>
                                        )}
                                    </div>
                                </Tegoed>
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full"
                                    disabled={processing || !genoeg}
                                    data-test="versturen"
                                >
                                    {processing && <Spinner />}
                                    {zelf && creator
                                        ? `Versturen en ${creator.name.split(" ")[0]} uitnodigen`
                                        : "Aanvraag versturen"}
                                </Button>
                                <p className="text-center text-xs text-muted-foreground">
                                    Bij versturen zetten we de punten voor je
                                    apart (gereserveerd). Ze worden pas
                                    verbruikt na het bezoek. Annuleer je meer
                                    dan 48 uur voor het bezoek, dan krijg je ze
                                    terug.
                                </p>
                            </div>
                        }
                    />
                </form>
            </Pagina>
        </>
    );
}

/** Een keuzekaart die zich gedraagt als radioknop. */
function Keuze({
    naam,
    gekozen,
    onKies,
    className,
    children,
}: {
    naam: string;
    gekozen: boolean;
    onKies: () => void;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <label
            className={cn(
                "relative flex cursor-pointer flex-col rounded-xl border-2 p-3 font-semibold transition has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50",
                gekozen
                    ? "border-violet bg-violet/5"
                    : "border-border hover:border-violet/50",
                className,
            )}
        >
            <input
                type="radio"
                name={naam}
                checked={gekozen}
                onChange={onKies}
                className="sr-only"
            />
            {children}
        </label>
    );
}

Aanvragen.layout = {
    breadcrumbs: [
        { title: "Overzicht", href: overzicht() },
        { title: "Samenwerking aanvragen", href: aanvragen() },
    ],
};
