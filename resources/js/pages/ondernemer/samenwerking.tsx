import { Head, router, useForm } from "@inertiajs/react";
import {
    CalendarClock,
    Clock,
    ExternalLink,
    Star,
    UserRoundCheck,
    X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import type { FormEvent } from "react";
import InputError from "@/components/input-error";
import {
    Kaart,
    Pagina,
    PaginaKop,
    Sectie,
    TweeKolommen,
} from "@/components/pagina";
import { StatusBadge } from "@/components/samenwerking/status-badge";
import { Tijdlijn } from "@/components/samenwerking/tijdlijn";
import { Snelkoppelingen } from "@/components/samenwerking/snelkoppelingen";
import {
    FeedbackLijst,
    VideoSpeler,
    tijdcode,
} from "@/components/samenwerking/video-speler";
import type { VideoSpelerHandle } from "@/components/samenwerking/video-speler";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { datum, datumTijd } from "@/lib/datum";
import { cn } from "@/lib/utils";
import {
    annuleren,
    beoordelen,
    opmerking,
    overzicht,
    uitnodigen,
} from "@/routes/ondernemer";
import type { SamenwerkingVol, Versie } from "@/types/samenwerking";
import { CreatorKiezen } from "./_onderdelen/creator-kiezen";
import type { KiesbareCreator } from "./_onderdelen/creator-kiezen";
import { Gegeven, Tekstvak } from "./_onderdelen/opmaak";

type Props = {
    collaboration: SamenwerkingVol;
    can_cancel: boolean;
    available_points: number;
    unlimited_credit: boolean;
    /** Zelf een creator kiezen: alleen zolang er niemand is en geen uitnodiging openstaat. */
    creators: KiesbareCreator[] | null;
    /** Een open uitnodiging die dit bedrijf zelf verstuurde. */
    own_invitation: { creator: string; expires_at: string } | null;
};

const annuleerbaar = [
    "nieuw",
    "creator_zoeken",
    "creator_gevonden",
    "bezoek_gepland",
];
const platformNaam: Record<string, string> = {
    instagram: "Instagram",
    tiktok: "TikTok",
    youtube: "YouTube",
    facebook: "Facebook",
};

function uur(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleTimeString("nl-NL", {
              timeZone: "Europe/Amsterdam",
              hour: "2-digit",
              minute: "2-digit",
          })
        : "";
}

/** Wie er aan zet is en wat er nu gebeurt, per status. */
function nu(
    c: SamenwerkingVol,
    versie: Versie | undefined,
    eigen: Props["own_invitation"],
): { aanZet: string; titel: string; tekst: string } {
    const creator = c.creator ?? "De creator";

    switch (c.status.value) {
        case "nieuw":
            return {
                aanZet: "MEBRANDING",
                titel: "Je aanvraag staat bij MEBRANDING",
                tekst: "Je accountmanager pakt hem binnen 1 werkdag op en zoekt een creator die past bij je briefing. Je hoeft nu niets te doen. Liever zelf kiezen? Dat kan hieronder.",
            };
        case "creator_zoeken":
            if (eigen) {
                return {
                    aanZet: eigen.creator,
                    titel: `${eigen.creator} is uitgenodigd`,
                    tekst: `${eigen.creator} kan reageren tot ${datumTijd(eigen.expires_at)}. Zegt ${eigen.creator.split(" ")[0]} ja door het contract te tekenen, dan kiest die een moment om langs te komen. Komt er een nee of geen reactie, dan zoekt MEBRANDING iemand anders. Je hoeft nu niets te doen.`,
                };
            }

            return {
                aanZet: "MEBRANDING",
                titel: "We zoeken een creator",
                tekst: "Er is een passende creator uitgenodigd. Zodra die ja zegt door het contract te tekenen, zie je hier wie het is.",
            };
        case "creator_gevonden":
            return {
                aanZet: creator,
                titel: `${creator} gaat het doen`,
                tekst: `${creator} heeft het contract getekend en kiest nu een tijdvak uit de tijden die je bij de vestiging opgaf. Dat staat direct vast en je krijgt meteen bericht.`,
            };
        case "bezoek_gepland":
            return {
                aanZet: creator,
                titel: `Bezoek staat vast: ${datumTijd(c.visit_at)}`,
                tekst: `${creator} koos dit tijdvak zelf. Op de dag tikt de creator op Ik ben er, dan zie je hier dat het bezoek begonnen is.`,
            };
        case "bezoek_geweest":
            return {
                aanZet: creator,
                titel: `${creator} is binnen`,
                tekst: `Ingecheckt op ${datumTijd(c.visited_at)}. Na het filmen gaat ${creator} de video maken.`,
            };
        case "content_maken":
            return {
                aanZet: creator,
                titel: `${creator} maakt de video`,
                tekst: `${c.content_deadline_at ? `Uiterlijk ${datumTijd(c.content_deadline_at)} komt versie 1. ` : ""}MEBRANDING controleert hem. Jij kijkt mee en krijgt een melding zodra hij klaarstaat.`,
            };
        case "ter_controle":
            return {
                aanZet: "MEBRANDING",
                titel: versie
                    ? `Versie ${versie.version} ter controle bij MEBRANDING`
                    : "Ter controle bij MEBRANDING",
                tekst: "Het team controleert de video. Jij kijkt mee: zie je iets, zet dan een opmerking op de tijdlijn. Het team neemt die mee in de feedback aan de creator.",
            };
        case "feedback":
            return {
                aanZet: creator,
                titel: `Feedback is naar ${creator}`,
                tekst: `${creator} verwerkt de feedback en levert daarna een nieuwe versie aan. Je kunt dan weer meekijken.`,
            };
        case "goedgekeurd":
            return {
                aanZet: creator,
                titel: `Goedgekeurd, live op ${datum(c.publish_on)}`,
                tekst: `MEBRANDING keurde de video goed. ${creator} plaatst hem op ${datum(c.publish_on)} op het eigen account, met de reclamevermelding. De versie zonder watermerk staat hieronder klaar om te downloaden.`,
            };
        case "live":
            return {
                aanZet: "Niemand",
                titel: "De video staat live",
                tekst: `Sinds ${datumTijd(c.live_at)}. De cijfers worden 14 dagen gemeten, tot en met ${datum(c.measure_until)}.`,
            };
        case "afgerond":
            return c.review
                ? {
                      aanZet: "Niemand",
                      titel: "Afgerond",
                      tekst: `De samenwerking is klaar. Je gaf ${creator} ${c.review.rating} van 5. Dank je wel.`,
                  }
                : {
                      aanZet: "Jij",
                      titel: "Afgerond, geef een beoordeling",
                      tekst: `De meetperiode is voorbij. Hoe was de samenwerking met ${creator}? Je beoordeling telt mee in het oordeel over de creator.`,
                  };
        case "geannuleerd":
            return {
                aanZet: "Niemand",
                titel: "Samenwerking geannuleerd",
                tekst: c.cancel_reason
                    ? `Reden: ${c.cancel_reason}`
                    : "Deze samenwerking is gestopt.",
            };
        case "niet_verschenen":
            return {
                aanZet: "MEBRANDING",
                titel: "Het bezoek is niet doorgegaan",
                tekst: "Je accountmanager neemt contact met je op en kiest samen met jou een vervolg: een nieuw bezoek, een andere creator of stoppen.",
            };
        default:
            return { aanZet: "MEBRANDING", titel: c.status.label, tekst: "" };
    }
}

export default function Samenwerking({
    collaboration: c,
    can_cancel,
    available_points,
    unlimited_credit,
    creators,
    own_invitation,
}: Props) {
    const speler = useRef<VideoSpelerHandle>(null);
    const versie = c.versions.at(-1);
    const status = c.status.value;
    const stand = nu(c, versie, own_invitation);
    const goedgekeurd = ["goedgekeurd", "live", "afgerond"].includes(status);
    const tijdlijn = useRef<HTMLDivElement>(null);

    // Op de telefoon scrolt de tijdlijn horizontaal: zet de huidige stap in beeld.
    useEffect(() => {
        const lijst = tijdlijn.current?.querySelector("ol");
        const huidig = lijst?.querySelector<HTMLElement>("[aria-current=step]");
        if (lijst && huidig) {
            lijst.scrollLeft =
                huidig.offsetLeft -
                lijst.offsetLeft -
                (lijst.clientWidth - huidig.clientWidth) / 2;
        }
    }, [status]);

    return (
        <>
            <Head title={c.title} />
            <Pagina>
                <PaginaKop
                    boven={`Samenwerking ${c.number}`}
                    titel={c.title}
                    omschrijving={`Bij ${c.location}. Kost ${c.points} punten.`}
                    acties={
                        <Snelkoppelingen
                            omgeving="ondernemer"
                            id={c.id}
                            status={c.status.value}
                        />
                    }
                    extra={<StatusBadge status={c.status} />}
                />

                {c.steps.length > 0 &&
                    !["geannuleerd", "niet_verschenen"].includes(status) && (
                        <Kaart compact>
                            <h2 className="sr-only">Voortgang</h2>
                            <div ref={tijdlijn}>
                                <Tijdlijn stappen={c.steps} />
                            </div>
                        </Kaart>
                    )}

                <TweeKolommen
                    hoofd={
                        <>
                            <Kaart className="border-l-4 border-l-violet">
                                <section
                                    aria-labelledby="nu-titel"
                                    data-test="nu"
                                >
                                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-xs font-bold tracking-wide text-muted-foreground uppercase">
                                            Nu
                                            {c.status.number
                                                ? ` · ${c.status.number}`
                                                : ""}{" "}
                                            {c.status.label}
                                        </span>
                                        <span className="rounded-full border px-2.5 py-0.5 text-xs">
                                            Aan zet:{" "}
                                            <strong>{stand.aanZet}</strong>
                                        </span>
                                    </div>
                                    <h2
                                        id="nu-titel"
                                        className="font-heading text-xl font-bold"
                                    >
                                        {stand.titel}
                                    </h2>
                                    {stand.tekst && (
                                        <p className="mt-2 text-muted-foreground">
                                            {stand.tekst}
                                        </p>
                                    )}

                                    {status === "live" &&
                                        c.publications.length > 0 && (
                                            <ul className="mt-4 flex flex-wrap gap-2">
                                                {c.publications.map((p) => (
                                                    <li key={p.url}>
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                        >
                                                            <a
                                                                href={p.url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                Bekijk op{" "}
                                                                {platformNaam[
                                                                    p.platform
                                                                ] ??
                                                                    p.platform}{" "}
                                                                <ExternalLink />
                                                            </a>
                                                        </Button>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}

                                    {status === "afgerond" && !c.review && (
                                        <Beoordelen
                                            id={c.id}
                                            creator={c.creator}
                                        />
                                    )}
                                    {c.review && (
                                        <Beoordeling
                                            rating={c.review.rating}
                                            body={c.review.body}
                                        />
                                    )}
                                </section>
                            </Kaart>
                            {creators && (
                                <ZelfKiezen
                                    c={c}
                                    creators={creators}
                                    beschikbaar={
                                        unlimited_credit
                                            ? Infinity
                                            : available_points + (c.points ?? 0)
                                    }
                                />
                            )}

                            {versie && (
                                <Sectie
                                    titel={
                                        goedgekeurd
                                            ? "Je video"
                                            : `Versie ${versie.version}, meekijken`
                                    }
                                    actie={
                                        goedgekeurd && c.publish_on ? (
                                            <span className="text-sm text-muted-foreground">
                                                Publicatie {datum(c.publish_on)}
                                            </span>
                                        ) : undefined
                                    }
                                >
                                    <Kaart>
                                        <div
                                            className={cn(
                                                "grid gap-6",
                                                status === "ter_controle" &&
                                                    "md:grid-cols-[minmax(0,320px)_1fr]",
                                            )}
                                        >
                                            <div className="mx-auto w-full max-w-[320px]">
                                                <VideoSpeler
                                                    key={versie.id}
                                                    versie={versie}
                                                    ref={speler}
                                                />
                                            </div>
                                            <div className="min-w-0 space-y-4">
                                                {versie.caption && (
                                                    <div>
                                                        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                            Bijschrift
                                                        </h3>
                                                        <p className="mt-1 text-sm whitespace-pre-line">
                                                            {versie.caption}
                                                        </p>
                                                    </div>
                                                )}
                                                {status === "ter_controle" && (
                                                    <Opmerking
                                                        id={c.id}
                                                        speler={speler}
                                                    />
                                                )}
                                                {versie.feedback.length > 0 && (
                                                    <div>
                                                        <h3 className="mb-2 text-sm font-semibold">
                                                            Opmerkingen bij deze
                                                            versie
                                                        </h3>
                                                        <FeedbackLijst
                                                            punten={
                                                                versie.feedback
                                                            }
                                                            onKlik={(s) =>
                                                                speler.current?.naar(
                                                                    s,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </Kaart>
                                </Sectie>
                            )}

                            {!versie &&
                                [
                                    "bezoek_gepland",
                                    "bezoek_geweest",
                                    "content_maken",
                                    "feedback",
                                ].includes(status) && (
                                    <Sectie titel="Video">
                                        <Kaart>
                                            <p className="text-sm text-muted-foreground">
                                                {status === "feedback"
                                                    ? "De creator werkt aan een nieuwe versie. Je krijgt een melding zodra die ter controle staat."
                                                    : "Nog geen video. Je krijgt een melding zodra versie 1 ter controle staat, dan kijk je mee."}
                                            </p>
                                        </Kaart>
                                    </Sectie>
                                )}
                            <Sectie titel="Briefing">
                                <Kaart>
                                    <p className="text-sm break-words whitespace-pre-line">
                                        {c.briefing}
                                    </p>
                                </Kaart>
                            </Sectie>
                        </>
                    }
                    zij={
                        <>
                            <Sectie titel="Gegevens">
                                <Kaart>
                                    <dl className="space-y-3 text-sm">
                                        <Gegeven label="Vestiging">
                                            {c.location}
                                            {c.location_address && (
                                                <span className="block text-muted-foreground">
                                                    {c.location_address}
                                                </span>
                                            )}
                                        </Gegeven>
                                        <Gegeven label="Creator">
                                            {c.creator ?? (
                                                <span className="text-muted-foreground">
                                                    Nog geen creator
                                                </span>
                                            )}
                                            {c.creator_level && (
                                                <span className="block text-muted-foreground">
                                                    Niveau {c.creator_level}
                                                </span>
                                            )}
                                        </Gegeven>
                                        <Gegeven label="Bezoek">
                                            {c.visit_at ? (
                                                <span className="inline-flex items-center gap-1.5">
                                                    <CalendarClock
                                                        className="size-4 text-violet"
                                                        aria-hidden
                                                    />
                                                    {datumTijd(c.visit_at)}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Nog niet gepland
                                                </span>
                                            )}
                                            {c.visited_at && (
                                                <span className="block text-muted-foreground">
                                                    Ingecheckt om{" "}
                                                    {uur(c.visited_at)}
                                                </span>
                                            )}
                                        </Gegeven>
                                        {(c.period_from || c.period_to) && (
                                            <Gegeven label="Periode">
                                                {c.period_from
                                                    ? datum(c.period_from)
                                                    : "nu"}{" "}
                                                tot en met{" "}
                                                {c.period_to
                                                    ? datum(c.period_to)
                                                    : "open"}
                                            </Gegeven>
                                        )}
                                        {c.experience && (
                                            <Gegeven label="Wat de creator ervaart">
                                                {c.experience}
                                            </Gegeven>
                                        )}
                                        <Gegeven label="Alcohol in beeld">
                                            {c.alcohol
                                                ? "Ja: creator 25+, geen TikTok, NIX18 in beeld"
                                                : "Nee"}
                                        </Gegeven>
                                    </dl>
                                </Kaart>
                            </Sectie>

                            {can_cancel && annuleerbaar.includes(status) && (
                                <Annuleren id={c.id} visitAt={c.visit_at} />
                            )}
                        </>
                    }
                />
            </Pagina>
        </>
    );
}

function Opmerking({
    id,
    speler,
}: {
    id: number;
    speler: React.RefObject<VideoSpelerHandle | null>;
}) {
    const form = useForm<{ timecode: number | null; body: string }>({
        timecode: null,
        body: "",
    });

    function versturen(e: FormEvent) {
        e.preventDefault();
        form.post(opmerking.url(id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form
            onSubmit={versturen}
            className="space-y-3 rounded-xl bg-muted/60 p-4"
            data-test="opmerking"
        >
            <h3 className="font-semibold">Opmerking voor het team</h3>
            <p className="text-xs text-muted-foreground">
                Alleen het team ziet je opmerking. Zij nemen hem mee in de
                feedback aan de creator.
            </p>
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        form.setData("timecode", speler.current?.tijd() ?? 0)
                    }
                >
                    <Clock /> Gebruik huidige tijd
                </Button>
                {form.data.timecode !== null ? (
                    <span className="inline-flex items-center gap-1 rounded bg-violet/10 px-2 py-1 font-mono text-xs font-semibold text-violet">
                        Op {tijdcode(form.data.timecode)}
                        <button
                            type="button"
                            onClick={() => form.setData("timecode", null)}
                            aria-label="Tijd weghalen"
                            className="rounded hover:bg-violet/20"
                        >
                            <X className="size-3.5" />
                        </button>
                    </span>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Zonder tijd geldt hij voor de hele video.
                    </span>
                )}
            </div>
            <InputError message={form.errors.timecode} />
            <div className="grid gap-2">
                <Label htmlFor="opmerking-body" className="sr-only">
                    Je opmerking
                </Label>
                <Tekstvak
                    id="opmerking-body"
                    rows={3}
                    maxLength={2000}
                    value={form.data.body}
                    onChange={(e) => form.setData("body", e.target.value)}
                    placeholder="Bijvoorbeeld: het logo mag eerder in beeld"
                    aria-invalid={!!form.errors.body}
                    className="bg-background"
                />
                <InputError message={form.errors.body} />
            </div>
            <Button type="submit" disabled={form.processing}>
                {form.processing && <Spinner />}
                Opmerking versturen
            </Button>
        </form>
    );
}

function Beoordelen({ id, creator }: { id: number; creator: string | null }) {
    const form = useForm<{ rating: number; body: string }>({
        rating: 0,
        body: "",
    });

    function versturen(e: FormEvent) {
        e.preventDefault();
        form.post(beoordelen.url(id), { preserveScroll: true });
    }

    return (
        <form
            onSubmit={versturen}
            className="mt-4 space-y-3 border-t pt-4"
            data-test="beoordelen"
        >
            <fieldset>
                <legend className="text-sm font-semibold">
                    Hoe was de samenwerking met {creator ?? "de creator"}?
                </legend>
                <div className="mt-2 flex gap-1">
                    {[1, 2, 3, 4, 5].map((n) => (
                        <label
                            key={n}
                            className="cursor-pointer rounded p-0.5 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50"
                        >
                            <input
                                type="radio"
                                name="rating"
                                value={n}
                                checked={form.data.rating === n}
                                onChange={() => form.setData("rating", n)}
                                className="sr-only"
                            />
                            <span className="sr-only">{n} van 5</span>
                            <Star
                                aria-hidden
                                className={cn(
                                    "size-8",
                                    n <= form.data.rating
                                        ? "fill-amber-400 text-amber-400"
                                        : "text-muted-foreground/50",
                                )}
                            />
                        </label>
                    ))}
                </div>
                <InputError message={form.errors.rating} className="mt-1" />
            </fieldset>
            <div className="grid gap-2">
                <Label htmlFor="review-body">Toelichting (optioneel)</Label>
                <Tekstvak
                    id="review-body"
                    rows={3}
                    maxLength={2000}
                    value={form.data.body}
                    onChange={(e) => form.setData("body", e.target.value)}
                    placeholder="Wat ging goed, wat kan beter?"
                />
                <InputError message={form.errors.body} />
            </div>
            <Button
                type="submit"
                disabled={form.processing || form.data.rating === 0}
            >
                {form.processing && <Spinner />}
                Beoordeling versturen
            </Button>
        </form>
    );
}

function Beoordeling({
    rating,
    body,
}: {
    rating: number;
    body: string | null;
}) {
    return (
        <div className="mt-4 border-t pt-4">
            <p className="flex gap-0.5" aria-label={`${rating} van 5 sterren`}>
                {[1, 2, 3, 4, 5].map((n) => (
                    <Star
                        key={n}
                        aria-hidden
                        className={cn(
                            "size-5",
                            n <= rating
                                ? "fill-amber-400 text-amber-400"
                                : "text-muted-foreground/40",
                        )}
                    />
                ))}
            </p>
            {body && <p className="mt-2 text-sm italic">"{body}"</p>}
        </div>
    );
}

function Annuleren({ id, visitAt }: { id: number; visitAt: string | null }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ reason: string }>({ reason: "" });
    // Binnen 48 uur voor het bezoek: de punten zijn dan verbruikt.
    const [laat] = useState(
        () =>
            visitAt !== null &&
            new Date(visitAt).getTime() - Date.now() < 48 * 3600 * 1000,
    );
    const errors = form.errors as Record<string, string | undefined>;

    function versturen(e: FormEvent) {
        e.preventDefault();
        form.post(annuleren.url(id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Sectie titel="Annuleren">
            <Kaart>
                <p className="text-sm text-muted-foreground">
                    Annuleer je meer dan 48 uur voor het bezoek, dan krijg je je
                    punten terug. Binnen 48 uur voor het bezoek zijn ze
                    verbruikt.
                </p>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button
                            variant="outline"
                            className="mt-3 w-full text-rood hover:text-rood"
                        >
                            Samenwerking annuleren
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <form onSubmit={versturen} className="space-y-4">
                            <DialogHeader>
                                <DialogTitle>
                                    Samenwerking annuleren?
                                </DialogTitle>
                                <DialogDescription>
                                    {laat
                                        ? "Het bezoek is binnen 48 uur. Annuleer je nu, dan zijn de punten verbruikt en krijg je ze niet terug."
                                        : "Je krijgt de gereserveerde punten terug. Binnen 48 uur voor het bezoek zouden ze verbruikt zijn."}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-2">
                                <Label htmlFor="reason">Reden</Label>
                                <Tekstvak
                                    id="reason"
                                    rows={3}
                                    maxLength={500}
                                    required
                                    value={form.data.reason}
                                    onChange={(e) =>
                                        form.setData("reason", e.target.value)
                                    }
                                    placeholder="Waarom stop je deze samenwerking?"
                                    aria-invalid={!!errors.reason}
                                />
                                <InputError
                                    message={errors.reason ?? errors.status}
                                />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Terug
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={
                                        form.processing ||
                                        form.data.reason.trim() === ""
                                    }
                                >
                                    {form.processing && <Spinner />}
                                    Ja, annuleren
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </Kaart>
        </Sectie>
    );
}

Samenwerking.layout = {
    breadcrumbs: [{ title: "Overzicht", href: overzicht() }],
};

/** Zelf een creator kiezen voor een aanvraag waar nog niemand voor is (of die net nee zei). */
function ZelfKiezen({
    c,
    creators,
    beschikbaar,
}: {
    c: SamenwerkingVol;
    creators: KiesbareCreator[];
    beschikbaar: number;
}) {
    const [gekozen, setGekozen] = useState<number | null>(null);
    const [bezig, setBezig] = useState(false);
    const [fout, setFout] = useState<string | null>(null);
    const naam = creators.find((m) => m.id === gekozen)?.name;

    return (
        <Kaart>
            <section aria-labelledby="zelf-kiezen" data-test="zelf-kiezen">
                <h2
                    id="zelf-kiezen"
                    className="flex items-center gap-2 font-heading text-lg font-bold"
                >
                    <UserRoundCheck
                        className="size-5 text-violet"
                        aria-hidden
                    />
                    Zelf een creator kiezen
                </h2>
                <p className="mt-1 mb-4 text-sm text-muted-foreground">
                    Ken je iemand die past? Kies zelf, dan gaat de uitnodiging
                    direct. Anders zoekt MEBRANDING voor je. Een ander niveau
                    dan je aanvroeg past de punten automatisch aan.
                </p>
                <CreatorKiezen
                    creators={creators}
                    gekozen={gekozen}
                    onKies={(id) => {
                        setGekozen(id);
                        setFout(null);
                    }}
                    alcohol={c.alcohol}
                    stad={null}
                    beschikbaar={beschikbaar}
                />
                <InputError message={fout ?? undefined} className="mt-2" />
                <Button
                    className="mt-4 w-full sm:w-auto"
                    disabled={!gekozen || bezig}
                    data-test="zelf-uitnodigen"
                    onClick={() =>
                        router.post(
                            uitnodigen.url(c.id),
                            { creator_id: gekozen },
                            {
                                preserveScroll: true,
                                onStart: () => setBezig(true),
                                onFinish: () => setBezig(false),
                                onError: (e) =>
                                    setFout(e.creator ?? e.creator_id ?? null),
                            },
                        )
                    }
                >
                    {bezig && <Spinner />}
                    {naam ? `${naam.split(" ")[0]} uitnodigen` : "Uitnodigen"}
                </Button>
            </section>
        </Kaart>
    );
}
