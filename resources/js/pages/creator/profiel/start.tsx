import { Head, Link, useForm } from "@inertiajs/react";
import { Check, ChevronRight, Circle, FileSignature } from "lucide-react";
import * as profiel from "@/routes/creator/profiel";
import {
    Fout,
    Kaart,
    Kop,
    Uitkomst,
    groteKnop,
    veld,
} from "../_onderdelen/opmaak";

type Props = {
    name: string;
    level: string;
    checklist: { key: string; label: string; done: boolean; href: string }[];
    agreement: {
        version: string;
        signed_at: string | null;
        signed_name: string | null;
        text: string[];
    };
};

/** Eerste keer: checklist en de raamovereenkomst tekenen (docs/02 flow 2, stap 6). */
export default function Start({ name, level, checklist, agreement }: Props) {
    const klaar = checklist.filter((c) => c.done).length;
    const voornaam = name.split(" ")[0];

    return (
        <>
            <Head title="Aan de slag" />
            <Kop
                boven="Aan de slag"
                titel={`Welkom, ${voornaam}`}
                onder={
                    <span className="flex flex-wrap items-center gap-2">
                        <span className="rounded-md bg-white px-2 py-0.5 text-xs font-extrabold tracking-wider text-aubergine-dark uppercase">
                            {level}
                        </span>
                        Rond je checklist af, dan kun je uitnodigingen krijgen.
                    </span>
                }
            />

            <div className="mx-auto w-full max-w-3xl space-y-5 px-4 py-5 md:px-6">
                <Kaart
                    titel={`Je checklist, ${klaar} van ${checklist.length} klaar`}
                >
                    <div
                        className="mb-3 h-2 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={checklist.length}
                        aria-valuenow={klaar}
                        aria-label="Voortgang checklist"
                    >
                        <div
                            className="h-full rounded-full bg-brand-gradient"
                            style={{
                                width: `${(klaar / checklist.length) * 100}%`,
                            }}
                        />
                    </div>
                    <ul className="divide-y" data-test="checklist">
                        {checklist.map((c) => (
                            <li key={c.key}>
                                <Link
                                    href={c.href}
                                    className="flex min-h-12 items-center gap-3 py-2.5 hover:text-violet"
                                >
                                    {c.done ? (
                                        <span className="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-800">
                                            <Check
                                                className="size-4"
                                                aria-hidden
                                            />
                                        </span>
                                    ) : (
                                        <Circle
                                            className="size-7 shrink-0 text-muted-foreground/50"
                                            aria-hidden
                                        />
                                    )}
                                    <span
                                        className={`flex-1 text-sm ${c.done ? "text-muted-foreground" : "font-semibold"}`}
                                    >
                                        <span className="sr-only">
                                            {c.done ? "Klaar: " : "Nog doen: "}
                                        </span>
                                        {c.label}
                                    </span>
                                    <ChevronRight
                                        className="size-5 shrink-0 text-muted-foreground"
                                        aria-hidden
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-3 rounded-xl bg-muted px-3 py-2 text-sm text-muted-foreground">
                        Instagram en TikTok koppelen volgt binnenkort. Tot die
                        tijd vul je je gebruikersnaam in bij je profiel.
                    </p>
                </Kaart>

                <div id="raamovereenkomst" className="scroll-mt-4">
                    {agreement.signed_at ? (
                        <Kaart titel="Raamovereenkomst">
                            <Uitkomst goed>
                                Getekend door {agreement.signed_name} op{" "}
                                {new Date(
                                    agreement.signed_at,
                                ).toLocaleDateString("nl-NL", {
                                    day: "numeric",
                                    month: "long",
                                    year: "numeric",
                                })}
                                , versie {agreement.version}.
                            </Uitkomst>
                        </Kaart>
                    ) : (
                        <Raamovereenkomst agreement={agreement} />
                    )}
                </div>
            </div>
        </>
    );
}

function Raamovereenkomst({ agreement }: Pick<Props, "agreement">) {
    const form = useForm({ signed_name: "", akkoord: false });

    return (
        <Kaart
            accent
            titel={
                <span className="flex items-center gap-2">
                    <FileSignature className="size-5 text-violet" aria-hidden />
                    Raamovereenkomst tekenen
                </span>
            }
        >
            <p className="mb-3 text-sm text-muted-foreground">
                Eenmalig, naast het contract dat je per samenwerking tekent.
                Zonder getekende raamovereenkomst kun je nog geen uitnodigingen
                krijgen.
            </p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(profiel.tekenen.url(), { preserveScroll: true });
                }}
                className="space-y-4"
            >
                <div
                    className="max-h-64 space-y-2 overflow-y-auto rounded-xl bg-muted p-3 text-sm leading-relaxed"
                    tabIndex={0}
                    aria-label="Tekst van de raamovereenkomst"
                >
                    <p className="text-xs font-semibold text-muted-foreground">
                        Versie {agreement.version}
                    </p>
                    {agreement.text.map((alinea, i) => (
                        <p key={i}>{alinea}</p>
                    ))}
                </div>

                <div>
                    <label
                        htmlFor="signed_name"
                        className="mb-1 block text-sm font-semibold"
                    >
                        Je volledige naam
                    </label>
                    <input
                        id="signed_name"
                        name="signed_name"
                        autoComplete="name"
                        required
                        value={form.data.signed_name}
                        onChange={(e) =>
                            form.setData("signed_name", e.target.value)
                        }
                        className={`${veld} font-heading text-lg italic`}
                        aria-invalid={!!form.errors.signed_name}
                    />
                    <p className="mt-1 text-xs text-muted-foreground">
                        Typen is tekenen. We bewaren je naam, het tijdstip en de
                        versie van de overeenkomst.
                    </p>
                </div>
                <Fout tekst={form.errors.signed_name} />

                <label className="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-border p-3 has-[:checked]:border-violet has-[:checked]:bg-violet/5">
                    <input
                        type="checkbox"
                        name="akkoord"
                        required
                        checked={form.data.akkoord}
                        onChange={(e) =>
                            form.setData("akkoord", e.target.checked)
                        }
                        className="mt-0.5 size-5 shrink-0 accent-violet"
                    />
                    <span className="text-sm">
                        Ik heb de raamovereenkomst gelezen en ga akkoord.
                    </span>
                </label>
                <Fout tekst={form.errors.akkoord} />

                <button
                    type="submit"
                    className={groteKnop}
                    disabled={form.processing}
                >
                    <Check className="size-5" aria-hidden />
                    Teken de raamovereenkomst
                </button>
            </form>
        </Kaart>
    );
}

Start.layout = {
    breadcrumbs: [{ title: "Aan de slag", href: profiel.start.url() }],
};
