import { Head, Link, router, useForm } from "@inertiajs/react";
import { ArrowLeft, Check } from "lucide-react";
import type { FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { datum, euro } from "@/lib/datum";
import { GebruikersBeheer } from "@/pages/ondernemer/beheer/_onderdelen/gebruikers-beheer";
import { Grootboek } from "@/pages/ondernemer/beheer/_onderdelen/grootboek";
import { BedrijfsInstellingen } from "@/pages/ondernemer/beheer/_onderdelen/instellingen";
import type {
    Abonnement,
    Boeking,
    Gebruiker,
    Instellingen,
    Verzoek,
    Vestiging,
} from "@/pages/ondernemer/beheer/_onderdelen/types";
import { VestigingenBeheer } from "@/pages/ondernemer/beheer/_onderdelen/vestigingen-beheer";
import { Voorstel } from "@/pages/ondernemer/beheer/_onderdelen/voorstel";
import * as team from "@/routes/team";
import bb from "@/routes/team/bedrijfsbeheer";
import { Pagina, PaginaKop, Tabs } from "@/components/pagina";
import { Paneel, Veld, invoer, useTab } from "../_onderdelen/basis";

type Bedrijf = {
    id: number;
    name: string;
    kvk: string | null;
    branche: string | null;
    email: string | null;
    phone: string | null;
    account_manager_id: number | null;
    available_points: number;
        unlimited_credit: boolean;
    reserved_points: number;
} & Instellingen;

type Props = {
    company: Bedrijf;
    locations: Vestiging[];
    users: Gebruiker[];
    ledger: Boeking[];
    subscription: Abonnement | null;
    requests: Verzoek[];
    managers: { id: number; name: string }[];
    can: { beheren: boolean; corrigeren: boolean };
};

export default function Bedrijf({
    company,
    locations,
    users,
    ledger,
    subscription,
    requests,
    managers,
    can,
}: Props) {
    const id = company.id;
    const [tab, setTab] = useTab(`bedrijf-${company.id}`, "gegevens", [
        "gegevens",
        "vestigingen",
        "gebruikers",
    ] as const);

    return (
        <>
            <Head title={company.name} />
            <Pagina>
                <PaginaKop
                    boven={
                        <Link
                            href={team.bedrijven()}
                            className="inline-flex items-center gap-1 hover:text-foreground"
                        >
                            <ArrowLeft className="size-3.5" aria-hidden /> Alle
                            bedrijven
                        </Link>
                    }
                    titel={company.name}
                    omschrijving={
                        <>
                            {subscription ? (
                                <>
                                    {subscription.name} (
                                    {euro(subscription.price_cents)} p/m,{" "}
                                    {subscription.points_per_month} punten){" "}
                                    {subscription.is_proposal && <Voorstel />}
                                </>
                            ) : (
                                "Geen abonnement"
                            )}{" "}
                            ·{" "}
                            {company.unlimited_credit
                                ? `onbeperkt tegoed (verbruik ${-company.available_points} punten),`
                                : `${company.available_points} punten beschikbaar,`}{" "}
                            {company.reserved_points} gereserveerd
                        </>
                    }
                />

                {requests.length > 0 && (
                    <Paneel
                        titel="Open verzoeken over het abonnement"
                        sub="Het abonnement wijzigen doe je bij Pakketten en prijzen of handmatig; vink het verzoek daarna af."
                    >
                        <ul className="divide-y" data-test="verzoeken">
                            {requests.map((r) => (
                                <li
                                    key={r.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                                >
                                    <span>
                                        <span className="font-semibold">
                                            {r.title}
                                        </span>
                                        <span className="block text-muted-foreground">
                                            {datum(r.created_at)}
                                        </span>
                                    </span>
                                    {can.beheren && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    bb.verzoek.url({
                                                        bedrijf: id,
                                                        taak: r.id,
                                                    }),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Check /> Afgehandeld
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Paneel>
                )}

                <Tabs
                    tabs={[
                        { waarde: "gegevens", label: "Gegevens en tegoed" },
                        {
                            waarde: "vestigingen",
                            label: "Vestigingen",
                            aantal: locations.length,
                        },
                        {
                            waarde: "gebruikers",
                            label: "Gebruikers",
                            aantal: users.length,
                        },
                    ]}
                    actief={tab}
                    onKies={setTab}
                />

                {tab === "gegevens" && (
                    <div className="grid gap-5 @4xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)] @4xl:items-start">
                        <Gegevens
                            company={company}
                            managers={managers}
                            kan={can.beheren}
                        />
                        <Tegoed
                            company={company}
                            ledger={ledger}
                            kan={can.corrigeren}
                        />
                    </div>
                )}

                {tab === "vestigingen" && (
                    <section
                        className="space-y-3"
                        aria-label="Vestigingen en bezoektijden"
                    >
                        <VestigingenBeheer
                            locations={locations}
                            kanBewerken={can.beheren}
                            toevoegen={bb.vestigingen.toevoegen(id)}
                            bijwerken={(v) =>
                                bb.vestigingen.bijwerken({
                                    bedrijf: id,
                                    vestiging: v,
                                })
                            }
                        />
                        <BedrijfsInstellingen
                            waarden={company}
                            actie={bb.instellingen(id)}
                            kanWijzigen={can.beheren}
                            bedrijf={company.name}
                            voorTeam
                        />
                    </section>
                )}

                {tab === "gebruikers" && (
                    <section className="space-y-3" aria-label="Gebruikers">
                        <GebruikersBeheer
                            users={users}
                            kanBeheren={can.beheren}
                            uitnodigen={bb.gebruikers.uitnodigen(id)}
                            rol={(u) =>
                                bb.gebruikers.rol({ bedrijf: id, gebruiker: u })
                            }
                            verwijderen={(u) =>
                                bb.gebruikers.verwijderen({
                                    bedrijf: id,
                                    gebruiker: u,
                                })
                            }
                            opnieuw={(u) =>
                                bb.gebruikers.opnieuw({
                                    bedrijf: id,
                                    gebruiker: u,
                                })
                            }
                        />
                    </section>
                )}
            </Pagina>
        </>
    );
}

function Gegevens({
    company,
    managers,
    kan,
}: {
    company: Bedrijf;
    managers: Props["managers"];
    kan: boolean;
}) {
    const form = useForm({
        name: company.name,
        kvk: company.kvk ?? "",
        branche: company.branche ?? "",
        email: company.email ?? "",
        phone: company.phone ?? "",
        account_manager_id: company.account_manager_id
            ? String(company.account_manager_id)
            : "",
    });
    const { data, setData, errors, processing } = form;

    function opslaan(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            account_manager_id: d.account_manager_id || null,
        }));
        form.put(bb.gegevens.url(company.id), { preserveScroll: true });
    }

    const veld = (
        naam: Exclude<keyof typeof data, "account_manager_id">,
        label: string,
        type = "text",
    ) => (
        <Veld label={label} htmlFor={`g-${naam}`} fout={errors[naam]}>
            <input
                id={`g-${naam}`}
                type={type}
                value={data[naam]}
                onChange={(e) => setData(naam, e.target.value)}
                className={invoer}
                disabled={!kan}
            />
        </Veld>
    );

    return (
        <Paneel titel="Bedrijfsgegevens">
            <form
                onSubmit={opslaan}
                className="space-y-4"
                noValidate
                data-test="gegevens"
            >
                <fieldset disabled={!kan} className="grid gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">{veld("name", "Naam")}</div>
                    {veld("kvk", "KvK-nummer (optioneel)")}
                    {veld("branche", "Branche (optioneel)")}
                    {veld("email", "E-mailadres (optioneel)", "email")}
                    {veld("phone", "Telefoon (optioneel)", "tel")}
                    <Veld
                        label="Accountmanager"
                        htmlFor="g-am"
                        fout={errors.account_manager_id}
                        className="sm:col-span-2"
                    >
                        <select
                            id="g-am"
                            value={data.account_manager_id}
                            onChange={(e) =>
                                setData("account_manager_id", e.target.value)
                            }
                            className={invoer}
                        >
                            <option value="">Nog niemand</option>
                            {managers.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name}
                                </option>
                            ))}
                        </select>
                    </Veld>
                </fieldset>
                {kan ? (
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />} Gegevens opslaan
                    </Button>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Je hebt het recht bedrijven.beheren nodig om dit te
                        wijzigen.
                    </p>
                )}
            </form>
        </Paneel>
    );
}

function Tegoed({
    company,
    ledger,
    kan,
}: {
    company: Bedrijf;
    ledger: Boeking[];
    kan: boolean;
}) {
    const form = useForm({ points: "", reason: "" });
    const { data, setData, errors, processing } = form;

    function boeken(e: FormEvent) {
        e.preventDefault();
        form.post(bb.tegoed.url(company.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Paneel
            titel="Tegoed"
            sub={company.unlimited_credit ? `Onbeperkt tegoed: eigen zaak, geen abonnement. Saldo ${company.available_points} punten, de som van alle boekingen.` : `Saldo ${company.available_points} punten. Het saldo is de som van alle boekingen.`}
        >
            {kan && (
                <form
                    onSubmit={boeken}
                    className="mb-4 space-y-3 rounded-xl bg-muted/50 p-3"
                    noValidate
                    data-test="correctie"
                >
                    <p className="text-sm font-semibold">
                        Handmatige correctie
                    </p>
                    <div className="grid grid-cols-[7rem_minmax(0,1fr)] gap-2">
                        <Veld
                            label="Punten"
                            htmlFor="c-points"
                            fout={errors.points}
                            hint="Min voor aftrekken"
                        >
                            <input
                                id="c-points"
                                type="number"
                                step={1}
                                value={data.points}
                                onChange={(e) =>
                                    setData("points", e.target.value)
                                }
                                className={invoer}
                            />
                        </Veld>
                        <Veld
                            label="Reden (verplicht)"
                            htmlFor="c-reason"
                            fout={errors.reason}
                            hint="Komt in het grootboek en het logboek"
                        >
                            <input
                                id="c-reason"
                                value={data.reason}
                                onChange={(e) =>
                                    setData("reason", e.target.value)
                                }
                                className={invoer}
                            />
                        </Veld>
                    </div>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing || !data.points || !data.reason}
                    >
                        {processing && <Spinner />} Correctie boeken
                    </Button>
                </form>
            )}
            <div className="max-h-80 overflow-y-auto">
                <Grootboek ledger={ledger} />
            </div>
        </Paneel>
    );
}
