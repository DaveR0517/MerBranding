import { Head, Link } from "@inertiajs/react";
import { Check, ShieldCheck, ShieldOff } from "lucide-react";
import { Button } from "@/components/ui/button";
import * as beheer from "@/routes/team/beheer";
import { edit as beveiliging } from "@/routes/security";
import { Kop, Label, Leeg, Pagina, Paneel } from "../_onderdelen/basis";

/** Voor elk teamlid: je eigen rollen en wat je daarmee mag. Hier landt de link uit de melding "Je rechten zijn gewijzigd". */
export default function MijnRechten({
    rollen,
    rechten,
    tweestaps,
}: {
    rollen: string[];
    rechten: string[];
    tweestaps: boolean;
}) {
    return (
        <>
            <Head title="Mijn rechten" />
            <Pagina>
                <Kop
                    titel="Mijn rechten"
                    sub="Je rollen in het team en wat je daarmee mag. Rollen wijzigen doet een beheerder in Team en rechten."
                />

                <Paneel titel="Rollen">
                    <p className="flex flex-wrap gap-1.5">
                        {rollen.map((r) => (
                            <Label
                                key={r}
                                kleur={r === "Super Admin" ? "rood" : "violet"}
                            >
                                {r}
                            </Label>
                        ))}
                    </p>
                </Paneel>

                <Paneel titel="Tweestapsverificatie">
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <p
                            className={`inline-flex items-center gap-1.5 font-semibold ${tweestaps ? "text-emerald-700" : "text-rood"}`}
                        >
                            {tweestaps ? (
                                <ShieldCheck className="size-4" aria-hidden />
                            ) : (
                                <ShieldOff className="size-4" aria-hidden />
                            )}
                            {tweestaps ? "Aan" : "Uit, verplicht voor het team"}
                        </p>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={beveiliging.url()}>Beveiliging</Link>
                        </Button>
                    </div>
                </Paneel>

                <Paneel titel="Wat je mag">
                    {rechten.length === 0 ? (
                        <Leeg>Geen losse rechten.</Leeg>
                    ) : (
                        <ul className="grid gap-1.5 text-sm sm:grid-cols-2">
                            {rechten.map((r) => (
                                <li key={r} className="flex items-center gap-2">
                                    <Check
                                        className="size-4 text-violet"
                                        aria-hidden
                                    />{" "}
                                    {r}
                                </li>
                            ))}
                        </ul>
                    )}
                </Paneel>
            </Pagina>
        </>
    );
}

MijnRechten.layout = {
    breadcrumbs: [{ title: "Mijn rechten", href: beheer.mijnRechten.url() }],
};
