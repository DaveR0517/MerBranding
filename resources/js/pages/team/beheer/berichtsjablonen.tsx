import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    Lock,
    Mail,
    Plus,
    Search,
    Smartphone,
    Trash2,
} from "lucide-react";
import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import * as team from "@/routes/team";
import * as beheer from "@/routes/team/beheer";
import { opslaan, testmail } from "@/routes/team/beheer/berichtsjablonen";
import {
    Kop,
    Label,
    Leeg,
    Pagina,
    Paneel,
    Veld,
    invoer,
} from "../_onderdelen/basis";
import { Cijfer, Voorstel } from "./_onderdelen/beheer";

type Kanaal = "inapp" | "email" | "push";
type Sjabloon = {
    id: number;
    key: string;
    role: string;
    event: string;
    soort: string | null;
    ontvanger: string | null;
    moment: string | null;
    voorstel: boolean;
    channels: Record<Kanaal, string>;
    vast: Record<Kanaal, boolean>;
    subject: string;
    inapp: string | null;
    email: { aanhef: string; alineas: string[]; knop: string };
    voorbeeld: Record<string, string>;
    updated_at: string | null;
};
type Props = {
    sjablonen: Sjabloon[];
    rollen: Record<string, string>;
    soorten: string[];
    spelregels: string[];
};

const KANALEN: { key: Kanaal; naam: string }[] = [
    { key: "inapp", naam: "In-app" },
    { key: "email", naam: "E-mail" },
    { key: "push", naam: "Push" },
];
const STAND: Record<string, string> = {
    aan: "Aan",
    uit: "Uit",
    samenvatting: "Samenvatting",
    verplicht: "Verplicht",
    nee: "Niet beschikbaar",
};
const aan = (w: string | undefined) =>
    w === "aan" || w === "verplicht" || w === "samenvatting";

/** Vult {variabelen} in, net als MessageTemplate::vulIn. Onbekende variabelen blijven zichtbaar. */
function vulIn(
    tekst: string | null | undefined,
    vars: Record<string, string>,
): string {
    return (tekst ?? "").replace(/\{(\w+)\}/gu, (m, k: string) =>
        k in vars ? vars[k] : m,
    );
}

export default function Berichtsjablonen({
    sjablonen,
    rollen,
    soorten,
    spelregels,
}: Props) {
    const [zoek, setZoek] = useState("");
    const [rol, setRol] = useState<string | null>(null);
    const [soort, setSoort] = useState("");
    const [kanaal, setKanaal] = useState("");
    const [gekozenId, setGekozenId] = useState<number | null>(null);

    const lijst = useMemo(() => {
        const z = zoek.trim().toLowerCase();

        return sjablonen.filter(
            (s) =>
                (!rol || s.role === rol) &&
                (!soort || s.soort === soort) &&
                (kanaal !== "email" || aan(s.channels.email)) &&
                (kanaal !== "push" || aan(s.channels.push)) &&
                (kanaal !== "vast" ||
                    Object.values(s.channels).includes("verplicht")) &&
                (kanaal !== "voorstel" || s.voorstel) &&
                (!z ||
                    [
                        s.key,
                        s.event,
                        s.subject,
                        s.inapp ?? "",
                        ...s.email.alineas,
                    ].some((t) => t.toLowerCase().includes(z))),
        );
    }, [sjablonen, zoek, rol, soort, kanaal]);

    const gekozen = sjablonen.find((s) => s.id === gekozenId) ?? null;
    const wissen = () => {
        setZoek("");
        setRol(null);
        setSoort("");
        setKanaal("");
    };

    return (
        <>
            <Head title="Berichtsjablonen" />
            <Pagina breed>
                <Kop
                    titel="Berichtsjablonen"
                    sub="Alle meldingen die MEBRANDING verstuurt, per rol en soort: onderwerp, e-mail en in-app. Teksten zijn aan te passen zonder ontwikkelaar; elke wijziging komt in het logboek."
                    acties={
                        <Button variant="outline" asChild>
                            <Link href={team.logboek.url()}>Logboek</Link>
                        </Button>
                    }
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Cijfer
                        label="Sjablonen"
                        waarde={sjablonen.length}
                        sub="Uit de meldingenmatrix"
                    />
                    <Cijfer
                        label="E-mail verplicht"
                        waarde={
                            sjablonen.filter(
                                (s) => s.channels.email === "verplicht",
                            ).length
                        }
                        sub="Beveiliging, geld en voortgang"
                    />
                    <Cijfer
                        label="Met push"
                        waarde={
                            sjablonen.filter((s) => aan(s.channels.push)).length
                        }
                        sub="Push volgt later, nog niet gekoppeld"
                    />
                    <Cijfer
                        label="Voorstel"
                        waarde={sjablonen.filter((s) => s.voorstel).length}
                        sub="Concepttekst, nog te bevestigen"
                    />
                </div>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
                    <div
                        className={cn(
                            "min-w-0 space-y-3",
                            gekozen && "hidden lg:block",
                        )}
                    >
                        <div className="relative">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <label htmlFor="zoek" className="sr-only">
                                Zoeken in sjablonen
                            </label>
                            <input
                                id="zoek"
                                type="search"
                                placeholder="Zoeken in sjablonen"
                                value={zoek}
                                onChange={(e) => setZoek(e.target.value)}
                                className={`${invoer} pl-9`}
                            />
                        </div>
                        <div
                            className="flex flex-wrap gap-1.5"
                            role="group"
                            aria-label="Filter op rol"
                        >
                            {[null, ...Object.keys(rollen)].map((r) => (
                                <button
                                    key={r ?? "alle"}
                                    type="button"
                                    aria-pressed={rol === r}
                                    onClick={() => setRol(r)}
                                    className={`rounded-full border px-2.5 py-1 text-xs font-semibold ${rol === r ? "border-violet bg-violet text-white" : "bg-background hover:border-violet"}`}
                                >
                                    {r ? rollen[r] : "Alle"}{" "}
                                    <span className="opacity-70">
                                        {r
                                            ? sjablonen.filter(
                                                  (s) => s.role === r,
                                              ).length
                                            : sjablonen.length}
                                    </span>
                                </button>
                            ))}
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <label className="sr-only" htmlFor="soort">
                                Soort
                            </label>
                            <select
                                id="soort"
                                value={soort}
                                onChange={(e) => setSoort(e.target.value)}
                                className={invoer}
                            >
                                <option value="">Alle soorten</option>
                                {soorten.map((s) => (
                                    <option key={s}>{s}</option>
                                ))}
                            </select>
                            <label className="sr-only" htmlFor="kanaal">
                                Kanaal
                            </label>
                            <select
                                id="kanaal"
                                value={kanaal}
                                onChange={(e) => setKanaal(e.target.value)}
                                className={invoer}
                            >
                                <option value="">Alle kanalen</option>
                                <option value="email">Met e-mail</option>
                                <option value="push">Met push</option>
                                <option value="vast">Niet uit te zetten</option>
                                <option value="voorstel">
                                    Alleen voorstel
                                </option>
                            </select>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {lijst.length} sjablonen
                        </p>

                        {lijst.length === 0 ? (
                            <div className="space-y-2">
                                <Leeg>
                                    Geen sjabloon past bij deze filters.
                                </Leeg>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={wissen}
                                >
                                    Filters wissen
                                </Button>
                            </div>
                        ) : (
                            <ul
                                className="max-h-[70vh] divide-y overflow-y-auto rounded-2xl border bg-card"
                                data-test="sjablonen"
                            >
                                {lijst.map((s) => (
                                    <li key={s.id}>
                                        <button
                                            type="button"
                                            onClick={() => setGekozenId(s.id)}
                                            aria-current={s.id === gekozenId}
                                            className={cn(
                                                "block w-full p-3 text-left text-sm hover:bg-muted/50",
                                                s.id === gekozenId &&
                                                    "bg-violet/5",
                                            )}
                                        >
                                            <span className="flex items-center gap-2">
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {s.key}
                                                </span>
                                                {s.voorstel && <Voorstel />}
                                                <span className="ml-auto flex gap-1 text-muted-foreground">
                                                    {aan(s.channels.email) && (
                                                        <Mail
                                                            className="size-3.5"
                                                            aria-label="E-mail"
                                                        />
                                                    )}
                                                    {aan(s.channels.push) && (
                                                        <Smartphone
                                                            className="size-3.5"
                                                            aria-label="Push"
                                                        />
                                                    )}
                                                </span>
                                            </span>
                                            <span className="mt-0.5 block font-semibold">
                                                {s.subject}
                                            </span>
                                            <span className="block truncate text-muted-foreground">
                                                {s.event}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="min-w-0">
                        {gekozen ? (
                            <Bewerken
                                key={gekozen.id}
                                sjabloon={gekozen}
                                rolnaam={rollen[gekozen.role] ?? gekozen.role}
                                terug={() => setGekozenId(null)}
                            />
                        ) : (
                            <Paneel
                                titel="Spelregels"
                                sub="Kies links een sjabloon om het te bewerken."
                                className="hidden lg:block"
                            >
                                <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                    {spelregels.map((r) => (
                                        <li key={r}>{r}</li>
                                    ))}
                                </ul>
                            </Paneel>
                        )}
                    </div>
                </div>
            </Pagina>
        </>
    );
}

function Bewerken({
    sjabloon,
    rolnaam,
    terug,
}: {
    sjabloon: Sjabloon;
    rolnaam: string;
    terug: () => void;
}) {
    const form = useForm({
        subject: sjabloon.subject,
        inapp: sjabloon.inapp ?? "",
        email: {
            aanhef: sjabloon.email.aanhef,
            alineas: sjabloon.email.alineas.length
                ? sjabloon.email.alineas
                : [""],
            knop: sjabloon.email.knop,
        },
        channels: { ...sjabloon.channels },
    });
    const errors = form.errors as Record<string, string>;
    const vars = { voornaam: "Sanne", ...sjabloon.voorbeeld };
    const variabelen = [
        ...new Set(
            `${form.data.subject} ${form.data.inapp} ${form.data.email.aanhef} ${form.data.email.alineas.join(" ")}`.match(
                /\{\w+\}/g,
            ) ?? [],
        ),
    ];
    const id = `s-${sjabloon.id}`;

    const zetAlinea = (i: number, tekst: string) =>
        form.setData("email", {
            ...form.data.email,
            alineas: form.data.email.alineas.map((a, j) =>
                j === i ? tekst : a,
            ),
        });

    const bewaar = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(opslaan.url(sjabloon.id), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    return (
        <div className="space-y-5">
            <Button
                variant="ghost"
                size="sm"
                className="lg:hidden"
                onClick={terug}
            >
                <ArrowLeft className="size-4" aria-hidden /> Alle sjablonen
            </Button>
            <form
                onSubmit={bewaar}
                className="space-y-5"
                data-test="sjabloon-bewerken"
            >
                <Paneel
                    titel={
                        <span className="flex flex-wrap items-center gap-2">
                            {sjabloon.event} {sjabloon.voorstel && <Voorstel />}
                        </span>
                    }
                    sub={
                        <>
                            <span className="font-mono">{sjabloon.key}</span> ·{" "}
                            {rolnaam}
                            {sjabloon.soort && ` · ${sjabloon.soort}`}
                        </>
                    }
                >
                    <dl className="grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Ontvanger
                            </dt>
                            <dd>{sjabloon.ontvanger ?? "-"}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Moment
                            </dt>
                            <dd>{sjabloon.moment ?? "-"}</dd>
                        </div>
                    </dl>

                    <fieldset className="mt-4">
                        <legend className="text-sm font-semibold">
                            Kanalen
                        </legend>
                        <div className="mt-1.5 grid gap-2 sm:grid-cols-3">
                            {KANALEN.map((k) => (
                                <div
                                    key={k.key}
                                    className="rounded-xl border p-2.5"
                                >
                                    <label
                                        htmlFor={`${id}-${k.key}`}
                                        className="text-xs font-semibold text-muted-foreground"
                                    >
                                        {k.naam}
                                    </label>
                                    {sjabloon.vast[k.key] ? (
                                        <p
                                            id={`${id}-${k.key}`}
                                            className="mt-1 flex items-center gap-1.5 text-sm font-semibold"
                                        >
                                            <Lock
                                                className="size-3.5"
                                                aria-hidden
                                            />{" "}
                                            {STAND[form.data.channels[k.key]] ??
                                                form.data.channels[k.key]}
                                            <span className="sr-only">
                                                , niet te wijzigen
                                            </span>
                                        </p>
                                    ) : (
                                        <select
                                            id={`${id}-${k.key}`}
                                            value={form.data.channels[k.key]}
                                            onChange={(e) =>
                                                form.setData("channels", {
                                                    ...form.data.channels,
                                                    [k.key]: e.target.value,
                                                })
                                            }
                                            className={`${invoer} mt-1 h-9`}
                                        >
                                            <option value="aan">Aan</option>
                                            <option value="uit">Uit</option>
                                            {k.key === "email" && (
                                                <option value="samenvatting">
                                                    Samenvatting
                                                </option>
                                            )}
                                        </select>
                                    )}
                                    {errors[`channels.${k.key}`] && (
                                        <p className="mt-1 text-xs text-rood">
                                            {errors[`channels.${k.key}`]}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                        <p className="mt-1.5 text-xs text-muted-foreground">
                            Verplicht (slot) is niet uit te zetten: beveiliging,
                            geld, juridische zaken en alles wat de voortgang
                            blokkeert.
                        </p>
                    </fieldset>
                </Paneel>

                <div className="grid gap-5 xl:grid-cols-2">
                    <Paneel titel="Teksten">
                        <div className="space-y-3">
                            <Veld
                                label="Onderwerp"
                                htmlFor={`${id}-onderwerp`}
                                fout={errors.subject}
                                hint="Kort, belangrijkste woord eerst, geen uitroeptekens."
                            >
                                <input
                                    id={`${id}-onderwerp`}
                                    required
                                    value={form.data.subject}
                                    onChange={(e) =>
                                        form.setData("subject", e.target.value)
                                    }
                                    className={invoer}
                                />
                            </Veld>
                            <Veld
                                label="In-app tekst"
                                htmlFor={`${id}-inapp`}
                                fout={errors.inapp}
                            >
                                <textarea
                                    id={`${id}-inapp`}
                                    rows={2}
                                    value={form.data.inapp}
                                    onChange={(e) =>
                                        form.setData("inapp", e.target.value)
                                    }
                                    className={`${invoer} h-auto py-2`}
                                />
                            </Veld>
                            <Veld
                                label="E-mail: aanhef"
                                htmlFor={`${id}-aanhef`}
                                fout={errors["email.aanhef"]}
                            >
                                <input
                                    id={`${id}-aanhef`}
                                    value={form.data.email.aanhef}
                                    onChange={(e) =>
                                        form.setData("email", {
                                            ...form.data.email,
                                            aanhef: e.target.value,
                                        })
                                    }
                                    className={invoer}
                                />
                            </Veld>
                            {form.data.email.alineas.map((a, i) => (
                                <div key={i} className="flex items-start gap-2">
                                    <Veld
                                        label={`E-mail: alinea ${i + 1}`}
                                        htmlFor={`${id}-alinea-${i}`}
                                        fout={errors[`email.alineas.${i}`]}
                                        className="flex-1"
                                    >
                                        <textarea
                                            id={`${id}-alinea-${i}`}
                                            rows={3}
                                            value={a}
                                            onChange={(e) =>
                                                zetAlinea(i, e.target.value)
                                            }
                                            className={`${invoer} h-auto py-2`}
                                        />
                                    </Veld>
                                    {form.data.email.alineas.length > 1 && (
                                        <button
                                            type="button"
                                            className="mt-7 rounded-md p-2 text-muted-foreground hover:text-rood"
                                            onClick={() =>
                                                form.setData("email", {
                                                    ...form.data.email,
                                                    alineas:
                                                        form.data.email.alineas.filter(
                                                            (_, j) => j !== i,
                                                        ),
                                                })
                                            }
                                            aria-label={`Alinea ${i + 1} weghalen`}
                                        >
                                            <Trash2
                                                className="size-4"
                                                aria-hidden
                                            />
                                        </button>
                                    )}
                                </div>
                            ))}
                            {form.data.email.alineas.length < 8 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        form.setData("email", {
                                            ...form.data.email,
                                            alineas: [
                                                ...form.data.email.alineas,
                                                "",
                                            ],
                                        })
                                    }
                                >
                                    <Plus className="size-4" aria-hidden />{" "}
                                    Alinea
                                </Button>
                            )}
                            <Veld
                                label="E-mail: tekst op de knop"
                                htmlFor={`${id}-knop`}
                                fout={errors["email.knop"]}
                            >
                                <input
                                    id={`${id}-knop`}
                                    value={form.data.email.knop}
                                    onChange={(e) =>
                                        form.setData("email", {
                                            ...form.data.email,
                                            knop: e.target.value,
                                        })
                                    }
                                    className={invoer}
                                />
                            </Veld>
                            {variabelen.length > 0 && (
                                <p className="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                    Variabelen:
                                    {variabelen.map((v) => (
                                        <Label key={v} kleur="grijs">
                                            {v}
                                        </Label>
                                    ))}
                                </p>
                            )}
                        </div>
                    </Paneel>

                    <Paneel
                        titel="Voorbeeld"
                        sub="Met de voorbeeldwaarden uit de meldingenmatrix."
                    >
                        <div className="space-y-4" data-test="voorbeeld">
                            {aan(form.data.channels.inapp) && (
                                <div className="rounded-xl border bg-background p-3 text-sm">
                                    <p className="text-xs font-semibold text-muted-foreground">
                                        In-app
                                    </p>
                                    <p className="mt-1 font-semibold">
                                        {vulIn(form.data.subject, vars)}
                                    </p>
                                    {form.data.inapp && (
                                        <p className="text-muted-foreground">
                                            {vulIn(form.data.inapp, vars)}
                                        </p>
                                    )}
                                </div>
                            )}
                            {aan(form.data.channels.email) ? (
                                <div className="overflow-hidden rounded-xl border bg-white text-sm text-[#2A2840]">
                                    <div className="bg-aubergine-dark px-4 py-3 font-heading text-sm font-extrabold tracking-wide text-white">
                                        MEBRANDING
                                    </div>
                                    <div className="space-y-3 p-4">
                                        <p className="text-xs text-[#5E5A78]">
                                            Onderwerp:{" "}
                                            {vulIn(form.data.subject, vars)}
                                        </p>
                                        <p>
                                            {vulIn(
                                                form.data.email.aanhef,
                                                vars,
                                            )}
                                        </p>
                                        {form.data.email.alineas
                                            .filter(Boolean)
                                            .map((a, i) => (
                                                <p key={i}>{vulIn(a, vars)}</p>
                                            ))}
                                        {form.data.email.knop && (
                                            <span className="inline-block bg-violet px-4 py-2.5 text-sm font-bold text-white">
                                                {form.data.email.knop}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    E-mail staat uit voor deze melding.
                                </p>
                            )}
                        </div>
                    </Paneel>
                </div>

                <div className="sticky bottom-20 z-10 flex flex-wrap items-center justify-end gap-2 rounded-2xl border bg-card/95 p-3 shadow-sm backdrop-blur md:bottom-4">
                    <p className="mr-auto text-xs text-muted-foreground">
                        {form.isDirty
                            ? "Niet opgeslagen wijzigingen."
                            : "Alles is opgeslagen."}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() =>
                            form.post(testmail.url(sjabloon.id), {
                                preserveScroll: true,
                            })
                        }
                        data-test="testmail"
                    >
                        Testmail naar mij
                    </Button>
                    <Button
                        type="submit"
                        className="bg-violet text-white hover:bg-violet/90"
                        disabled={form.processing || !form.isDirty}
                    >
                        Opslaan
                    </Button>
                </div>
            </form>
        </div>
    );
}

Berichtsjablonen.layout = {
    breadcrumbs: [
        { title: "Berichtsjablonen", href: beheer.berichtsjablonen.url() },
    ],
};
