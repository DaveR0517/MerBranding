import { Head, Link } from "@inertiajs/react";
import { BarChart3, ChevronRight } from "lucide-react";
import { datum } from "@/lib/datum";
import * as creator from "@/routes/creator";
import * as cijfers from "@/routes/creator/cijfers";
import { Kaart, Kop } from "../_onderdelen/opmaak";

type Klus = {
    id: number;
    title: string;
    company: string;
    location: string;
    live_at: string | null;
    measure_until: string | null;
    measuring: boolean;
    entered: boolean;
};

export default function CijfersOverzicht({
    collaborations,
}: {
    collaborations: Klus[];
}) {
    const nu = collaborations.filter((c) => c.measuring);
    const eerder = collaborations.filter((c) => !c.measuring);

    return (
        <>
            <Head title="Cijfers" />
            <Kop
                boven="Cijfers"
                titel="Cijfers van je posts"
                onder="Tijdens de meetperiode van 14 dagen vul je hier je cijfers in, met een screenshot."
            />
            <div className="mx-auto w-full max-w-3xl space-y-4 p-4 md:p-6">
                {collaborations.length === 0 ? (
                    <Kaart>
                        <BarChart3 className="size-7 text-violet" aria-hidden />
                        <p className="mt-2 text-muted-foreground">
                            Zodra je een post live hebt gezet, kun je hier de
                            cijfers invullen.
                        </p>
                    </Kaart>
                ) : (
                    <>
                        {nu.length > 0 && (
                            <Lijst titel="Nu aan het meten" items={nu} />
                        )}
                        {eerder.length > 0 && (
                            <Lijst titel="Meetperiode voorbij" items={eerder} />
                        )}
                    </>
                )}
            </div>
        </>
    );
}

function Lijst({ titel, items }: { titel: string; items: Klus[] }) {
    return (
        <Kaart titel={titel}>
            <ul className="-mx-1 divide-y">
                {items.map((c) => (
                    <li key={c.id}>
                        <Link
                            href={cijfers.invoeren.url(c.id)}
                            className="flex min-h-14 items-center gap-3 rounded-lg px-1 py-3 hover:bg-violet/5"
                            data-test="klus-cijfers"
                        >
                            <span className="min-w-0 flex-1">
                                <span className="block truncate font-semibold">
                                    {c.title}
                                </span>
                                <span className="block truncate text-sm text-muted-foreground">
                                    {c.company}, {c.location}
                                    {c.measuring && c.measure_until
                                        ? ` · invullen tot ${datum(c.measure_until)}`
                                        : ""}
                                </span>
                            </span>
                            {c.measuring && !c.entered && (
                                <span className="rounded-full bg-violet px-2.5 py-0.5 text-xs font-bold text-white">
                                    Invullen
                                </span>
                            )}
                            <ChevronRight
                                className="size-4 text-muted-foreground"
                                aria-hidden
                            />
                        </Link>
                    </li>
                ))}
            </ul>
        </Kaart>
    );
}

CijfersOverzicht.layout = {
    breadcrumbs: [{ title: "Cijfers", href: creator.cijfers() }],
};
