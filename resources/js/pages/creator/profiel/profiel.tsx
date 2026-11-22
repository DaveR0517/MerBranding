import { Head, Link, useForm } from "@inertiajs/react";
import {
    Camera,
    ExternalLink,
    FileSignature,
    Link2,
    MapPin,
    Pencil,
    Plus,
    Trash2,
} from "lucide-react";
import { useState } from "react";
import * as profielRoutes from "@/routes/creator/profiel";
import {
    Fout,
    Kaart,
    Kop,
    groteKnop,
    rustigeKnop,
    veld,
} from "../_onderdelen/opmaak";

type Profiel = {
    name: string;
    level: string;
    photo_url: string | null;
    bio: string | null;
    kind: string;
    niches: string[];
    city: string | null;
    radius_km: number | null;
    instagram: string | null;
    tiktok: string | null;
    portfolio_links: string[];
};

type Props = {
    profile: Profiel;
    options: { niches: string[]; kinds: { value: string; name: string }[] };
    agreement_signed: boolean;
};

const initialen = (naam: string) =>
    naam
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join("");

/** Profiel en werkgebied van de creator (ontwerp CreatorProfiel). */
export default function Profiel({ profile, options, agreement_signed }: Props) {
    const [bewerken, setBewerken] = useState(false);
    const soort =
        options.kinds.find((k) => k.value === profile.kind)?.name ??
        profile.kind;

    return (
        <>
            <Head title="Profiel" />
            <Kop
                boven="Profiel en werkgebied"
                titel={profile.name}
                onder={soort}
            />

            <div className="mx-auto w-full max-w-3xl space-y-5 px-4 py-5 md:px-6">
                {!agreement_signed && (
                    <Link
                        href={profielRoutes.start.url()}
                        className="flex items-center gap-3 rounded-2xl border-2 border-violet bg-violet/5 p-4 text-sm font-semibold text-violet hover:bg-violet/10"
                        data-test="raam-banner"
                    >
                        <FileSignature
                            className="size-5 shrink-0"
                            aria-hidden
                        />
                        Teken eerst je raamovereenkomst en rond je checklist af.
                    </Link>
                )}

                {bewerken ? (
                    <Bewerken
                        profile={profile}
                        options={options}
                        klaar={() => setBewerken(false)}
                    />
                ) : (
                    <Weergave
                        profile={profile}
                        soort={soort}
                        bewerk={() => setBewerken(true)}
                    />
                )}
            </div>
        </>
    );
}

function Foto({
    url,
    naam,
    groot = false,
}: {
    url: string | null;
    naam: string;
    groot?: boolean;
}) {
    const maat = groot ? "size-24 text-2xl" : "size-16 text-lg";

    return url ? (
        <img
            src={url}
            alt={`Profielfoto van ${naam}`}
            className={`${maat} shrink-0 rounded-full object-cover`}
        />
    ) : (
        <span
            className={`${maat} grid shrink-0 place-items-center rounded-full bg-brand-gradient font-heading font-extrabold text-white`}
            aria-hidden
        >
            {initialen(naam)}
        </span>
    );
}

function Weergave({
    profile,
    soort,
    bewerk,
}: {
    profile: Profiel;
    soort: string;
    bewerk: () => void;
}) {
    const kanalen = [
        { naam: "Instagram", handle: profile.instagram },
        { naam: "TikTok", handle: profile.tiktok },
    ];

    return (
        <>
            <Kaart>
                <div className="flex items-center gap-4">
                    <Foto url={profile.photo_url} naam={profile.name} />
                    <div className="min-w-0">
                        <p className="flex flex-wrap items-center gap-2">
                            <span className="font-heading text-lg font-extrabold break-words">
                                {profile.name}
                            </span>
                            <span className="rounded-md bg-aubergine px-2 py-0.5 text-xs font-extrabold tracking-wider text-white uppercase">
                                {profile.level}
                            </span>
                        </p>
                        <p className="text-sm text-muted-foreground">{soort}</p>
                        {profile.city && (
                            <p className="mt-0.5 flex items-center gap-1 text-sm text-muted-foreground">
                                <MapPin
                                    className="size-4 shrink-0"
                                    aria-hidden
                                />
                                Woont in {profile.city}
                            </p>
                        )}
                    </div>
                </div>
                <p
                    className={`mt-4 whitespace-pre-line ${profile.bio ? "" : "text-muted-foreground italic"}`}
                >
                    {profile.bio ||
                        "Nog geen bio. Vertel kort wie je bent, wat je graag maakt en waarom je lokale zaken in beeld brengt."}
                </p>
            </Kaart>

            <Kaart titel="Werkgebied">
                <p>
                    {profile.radius_km
                        ? `Straal van ${profile.radius_km} km rond ${profile.city ?? "je woonplaats"}`
                        : "Nog geen straal opgegeven."}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    Je hoeft niet lokaal te zijn. Afstand weegt maar een klein
                    beetje mee.
                </p>
            </Kaart>

            <Kaart titel="Niches">
                {profile.niches.length === 0 ? (
                    <p className="text-muted-foreground">
                        Nog geen niches gekozen.
                    </p>
                ) : (
                    <ul className="flex flex-wrap gap-2">
                        {profile.niches.map((n) => (
                            <li
                                key={n}
                                className="rounded-full bg-violet/10 px-3 py-1 text-sm font-semibold text-violet"
                            >
                                {n}
                            </li>
                        ))}
                    </ul>
                )}
            </Kaart>

            <Kaart titel="Kanalen">
                <ul className="divide-y">
                    {kanalen.map((k) => (
                        <li
                            key={k.naam}
                            className="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0"
                        >
                            <span>
                                <span className="block font-semibold">
                                    {k.naam}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {k.handle ?? "Niet opgegeven"}
                                </span>
                            </span>
                            <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold text-muted-foreground">
                                Nog niet gekoppeld
                            </span>
                        </li>
                    ))}
                </ul>
                <p className="mt-3 rounded-xl bg-muted px-3 py-2 text-sm text-muted-foreground">
                    Koppelen met Instagram en TikTok volgt binnenkort. Dan
                    vragen we eerst je toestemming: welke gegevens we ophalen,
                    met wie we ze delen en hoe lang we ze bewaren. Voor
                    Instagram is een professioneel account (Creator of Bedrijf)
                    nodig.
                </p>
            </Kaart>

            <Kaart titel="Portfolio">
                {profile.portfolio_links.length === 0 ? (
                    <p className="text-muted-foreground">
                        Nog geen links naar je werk.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {profile.portfolio_links.map((l) => (
                            <li key={l}>
                                <a
                                    href={l}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex max-w-full items-center gap-2 font-semibold text-violet hover:underline"
                                >
                                    <Link2
                                        className="size-4 shrink-0"
                                        aria-hidden
                                    />
                                    <span className="truncate">
                                        {l.replace(/^https?:\/\//, "")}
                                    </span>
                                    <ExternalLink
                                        className="size-3.5 shrink-0"
                                        aria-hidden
                                    />
                                    <span className="sr-only">
                                        (opent in een nieuw tabblad)
                                    </span>
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </Kaart>

            <button
                type="button"
                onClick={bewerk}
                className={groteKnop}
                data-test="bewerken"
            >
                <Pencil className="size-5" aria-hidden />
                Profiel bewerken
            </button>
        </>
    );
}

function Bewerken({
    profile,
    options,
    klaar,
}: {
    profile: Profiel;
    options: Props["options"];
    klaar: () => void;
}) {
    const form = useForm<{
        photo: File | null;
        remove_photo: boolean;
        bio: string;
        kind: string;
        niches: string[];
        city: string;
        radius_km: string;
        instagram: string;
        tiktok: string;
        portfolio_links: string[];
    }>({
        photo: null,
        remove_photo: false,
        bio: profile.bio ?? "",
        kind: profile.kind,
        niches: profile.niches,
        city: profile.city ?? "",
        radius_km: profile.radius_km ? String(profile.radius_km) : "",
        instagram: (profile.instagram ?? "").replace(/^@/, ""),
        tiktok: (profile.tiktok ?? "").replace(/^@/, ""),
        portfolio_links: profile.portfolio_links.length
            ? profile.portfolio_links
            : [""],
    });
    const voorbeeld = form.data.photo
        ? URL.createObjectURL(form.data.photo)
        : form.data.remove_photo
          ? null
          : profile.photo_url;
    const fouten = form.errors as Record<string, string | undefined>;

    const wisselNiche = (n: string) =>
        form.setData(
            "niches",
            form.data.niches.includes(n)
                ? form.data.niches.filter((x) => x !== n)
                : [...form.data.niches, n],
        );

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(profielRoutes.opslaan.url(), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: klaar,
                });
            }}
            className="space-y-5"
            data-test="profiel-form"
        >
            <Kaart titel="Foto">
                <div className="flex flex-wrap items-center gap-4">
                    <Foto url={voorbeeld} naam={profile.name} groot />
                    <div className="flex flex-col gap-2">
                        <label className={`${rustigeKnop} cursor-pointer`}>
                            <Camera className="size-5" aria-hidden />
                            {voorbeeld ? "Andere foto kiezen" : "Foto kiezen"}
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="sr-only"
                                onChange={(e) => {
                                    form.setData((d) => ({
                                        ...d,
                                        photo: e.target.files?.[0] ?? null,
                                        remove_photo: false,
                                    }));
                                }}
                            />
                        </label>
                        {voorbeeld && (
                            <button
                                type="button"
                                className="text-sm font-semibold text-rood hover:underline"
                                onClick={() =>
                                    form.setData((d) => ({
                                        ...d,
                                        photo: null,
                                        remove_photo: true,
                                    }))
                                }
                            >
                                Foto verwijderen, initialen tonen
                            </button>
                        )}
                    </div>
                </div>
                <p className="mt-2 text-xs text-muted-foreground">
                    Jpg, png of webp, maximaal 5 MB. Zonder foto tonen we je
                    initialen.
                </p>
                <Fout tekst={form.errors.photo} />
            </Kaart>

            <Kaart titel="Over jou">
                <div className="space-y-4">
                    <fieldset>
                        <legend className="mb-2 text-sm font-semibold">
                            Soort creator
                        </legend>
                        <div className="grid gap-2 sm:grid-cols-3">
                            {options.kinds.map((k) => (
                                <label
                                    key={k.value}
                                    className="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border-2 border-border px-3 text-sm font-semibold has-[:checked]:border-violet has-[:checked]:bg-violet/5"
                                >
                                    <input
                                        type="radio"
                                        name="kind"
                                        value={k.value}
                                        checked={form.data.kind === k.value}
                                        onChange={() =>
                                            form.setData("kind", k.value)
                                        }
                                        className="size-4 accent-violet"
                                    />
                                    {k.name}
                                </label>
                            ))}
                        </div>
                        <Fout tekst={form.errors.kind} />
                    </fieldset>

                    <div>
                        <label
                            htmlFor="bio"
                            className="mb-1 block text-sm font-semibold"
                        >
                            Bio
                        </label>
                        <textarea
                            id="bio"
                            rows={4}
                            maxLength={600}
                            value={form.data.bio}
                            onChange={(e) =>
                                form.setData("bio", e.target.value)
                            }
                            className={veld}
                            placeholder="Wie je bent, wat je graag maakt en waarom je lokale zaken in beeld brengt."
                        />
                        <p className="mt-1 text-right text-xs text-muted-foreground">
                            {form.data.bio.length} van 600
                        </p>
                        <Fout tekst={form.errors.bio} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                htmlFor="city"
                                className="mb-1 block text-sm font-semibold"
                            >
                                Woonplaats
                            </label>
                            <input
                                id="city"
                                required
                                autoComplete="address-level2"
                                value={form.data.city}
                                onChange={(e) =>
                                    form.setData("city", e.target.value)
                                }
                                className={veld}
                            />
                            <Fout tekst={form.errors.city} />
                        </div>
                        <div>
                            <label
                                htmlFor="radius_km"
                                className="mb-1 block text-sm font-semibold"
                            >
                                Werkgebied in km (optioneel)
                            </label>
                            <input
                                id="radius_km"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={300}
                                value={form.data.radius_km}
                                onChange={(e) =>
                                    form.setData("radius_km", e.target.value)
                                }
                                className={veld}
                            />
                            <Fout tekst={form.errors.radius_km} />
                        </div>
                    </div>
                </div>
            </Kaart>

            <Kaart titel="Niches">
                <fieldset>
                    <legend className="sr-only">Kies je niches</legend>
                    <div className="flex flex-wrap gap-2">
                        {options.niches.map((n) => (
                            <label
                                key={n}
                                className="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border-2 border-border px-3 text-sm font-semibold has-[:checked]:border-violet has-[:checked]:bg-violet/10 has-[:checked]:text-violet"
                            >
                                <input
                                    type="checkbox"
                                    checked={form.data.niches.includes(n)}
                                    onChange={() => wisselNiche(n)}
                                    className="size-4 accent-violet"
                                />
                                {n}
                            </label>
                        ))}
                    </div>
                </fieldset>
                <Fout tekst={form.errors.niches} />
            </Kaart>

            <Kaart titel="Kanalen">
                <div className="grid gap-4 sm:grid-cols-2">
                    {(["instagram", "tiktok"] as const).map((k) => (
                        <div key={k}>
                            <label
                                htmlFor={k}
                                className="mb-1 block text-sm font-semibold"
                            >
                                {k === "instagram" ? "Instagram" : "TikTok"}
                            </label>
                            <div className="flex items-center gap-1">
                                <span
                                    className="text-lg text-muted-foreground"
                                    aria-hidden
                                >
                                    @
                                </span>
                                <input
                                    id={k}
                                    value={form.data[k]}
                                    onChange={(e) =>
                                        form.setData(k, e.target.value)
                                    }
                                    className={veld}
                                    placeholder="gebruikersnaam"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                />
                            </div>
                            <Fout tekst={form.errors[k]} />
                        </div>
                    ))}
                </div>
                <p className="mt-3 text-sm text-muted-foreground">
                    Koppelen met Instagram en TikTok volgt binnenkort.
                </p>
            </Kaart>

            <Kaart titel="Portfolio">
                <div className="space-y-2">
                    {form.data.portfolio_links.map((l, i) => (
                        <div key={i}>
                            <div className="flex items-center gap-2">
                                <label
                                    htmlFor={`link-${i}`}
                                    className="sr-only"
                                >
                                    Portfoliolink {i + 1}
                                </label>
                                <input
                                    id={`link-${i}`}
                                    type="url"
                                    inputMode="url"
                                    value={l}
                                    placeholder="https://"
                                    onChange={(e) =>
                                        form.setData(
                                            "portfolio_links",
                                            form.data.portfolio_links.map(
                                                (x, j) =>
                                                    j === i
                                                        ? e.target.value
                                                        : x,
                                            ),
                                        )
                                    }
                                    className={veld}
                                />
                                <button
                                    type="button"
                                    aria-label={`Link ${i + 1} verwijderen`}
                                    className="grid size-12 shrink-0 place-items-center rounded-xl border-2 text-muted-foreground hover:border-rood hover:text-rood"
                                    onClick={() =>
                                        form.setData(
                                            "portfolio_links",
                                            form.data.portfolio_links.filter(
                                                (_, j) => j !== i,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 className="size-5" aria-hidden />
                                </button>
                            </div>
                            <Fout tekst={fouten[`portfolio_links.${i}`]} />
                        </div>
                    ))}
                </div>
                {form.data.portfolio_links.length < 5 && (
                    <button
                        type="button"
                        className="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-violet hover:underline"
                        onClick={() =>
                            form.setData("portfolio_links", [
                                ...form.data.portfolio_links,
                                "",
                            ])
                        }
                    >
                        <Plus className="size-4" aria-hidden />
                        Link toevoegen
                    </button>
                )}
                <p className="mt-2 text-xs text-muted-foreground">
                    Je website, een showreel of losse video's. Maximaal 5 links.
                </p>
            </Kaart>

            <div className="grid gap-3 sm:grid-cols-2">
                <button type="button" onClick={klaar} className={rustigeKnop}>
                    Annuleren
                </button>
                <button
                    type="submit"
                    className={groteKnop}
                    disabled={form.processing}
                >
                    Opslaan
                </button>
            </div>
        </form>
    );
}

Profiel.layout = {
    breadcrumbs: [{ title: "Profiel", href: profielRoutes.index.url() }],
};
