import { Link, router, usePage } from "@inertiajs/react";
import { Copy, X } from "lucide-react";
import { useEffect, useState } from "react";
import { isActief, navigatie, tellerTekst, tellerVan } from "@/lib/portaal";
import { terug } from "@/routes";
import type { Auth } from "@/types/auth";

/** Lange woorden in de onderbalk breken op een vast punt af in plaats van afgekapt te worden. */
const AFBREKEN: Record<string, string> = {
    Samenwerkingen: "Samen\u00ADwerkingen",
    Werkvoorraad: "Werk\u00ADvoorraad",
};

function afbreekbaar(titel: string): string {
    return AFBREKEN[titel] ?? titel;
}

/** Onderbalk op de telefoon (de zijbalk is daar verborgen). */
export function OnderBalk() {
    const page = usePage();
    const { auth, unread, tellers } = page.props as unknown as {
        auth: Auth;
        unread: number;
        tellers?: Record<string, number>;
    };
    const items = navigatie(auth.portal, auth.permissions).filter(
        (i) => i.mobile,
    );

    return (
        <nav
            aria-label="Hoofdmenu"
            className="fixed inset-x-0 bottom-0 z-40 flex border-t border-white/10 bg-aubergine-dark pb-[env(safe-area-inset-bottom)] md:hidden"
        >
            {items.map((item) => {
                const actief = isActief(item.href, page.url);
                const aantal = tellerVan(item, tellers, unread);

                return (
                    <Link
                        key={item.title}
                        href={item.href}
                        aria-current={actief ? "page" : undefined}
                        className={`relative flex min-w-0 flex-1 flex-col items-center gap-1 py-2 text-[11px] font-semibold ${actief ? "text-white" : "text-white/65"}`}
                    >
                        <item.icon className="size-5" />
                        <span className="max-w-full px-1 text-center leading-tight [hyphens:manual]">
                            {afbreekbaar(item.title)}
                        </span>
                        {aantal > 0 && (
                            <span
                                className="absolute top-1 right-[calc(50%-18px)] min-w-4 rounded-full bg-rood px-1 text-[10px] leading-4 text-white"
                                aria-label={`${aantal} open`}
                            >
                                {tellerTekst(aantal)}
                            </span>
                        )}
                    </Link>
                );
            })}
        </nav>
    );
}

/** Berichtbalk na een actie ("Je aanvraag is verstuurd"). */
export function Flash() {
    const { flash } = usePage().props as unknown as {
        flash: { melding: string | null };
    };
    const [zichtbaar, setZichtbaar] = useState<string | null>(null);

    useEffect(() => {
        if (flash?.melding) {
            setZichtbaar(flash.melding);
            const t = setTimeout(() => setZichtbaar(null), 6000);

            return () => clearTimeout(t);
        }
    }, [flash]);

    if (!zichtbaar) {
        return null;
    }

    return (
        <div
            role="status"
            className="fixed inset-x-4 bottom-20 z-50 mx-auto max-w-md rounded-lg bg-aubergine-dark px-4 py-3 text-sm text-white shadow-lg md:bottom-6"
        >
            {zichtbaar}
        </div>
    );
}

type Uitnodiging = { naam: string; email: string; link: string };

/**
 * Zolang er geen maildienst is: na het uitnodigen van iemand (creator, ondernemer, medewerker, deelnemer)
 * staat hier de persoonlijke link, om zelf door te sturen. Blijft staan tot je hem sluit of verder klikt.
 */
export function UitnodigingsBalk() {
    const { flash } = usePage().props as unknown as {
        flash: { uitnodiging: Uitnodiging | null };
    };
    const [weg, setWeg] = useState<string | null>(null);
    const [gekopieerd, setGekopieerd] = useState(false);
    const u = flash?.uitnodiging;

    if (!u || weg === u.link) {
        return null;
    }

    const kopieer = async () => {
        try {
            await navigator.clipboard.writeText(u.link);
            setGekopieerd(true);
            setTimeout(() => setGekopieerd(false), 2500);
        } catch {
            setGekopieerd(false);
        }
    };

    return (
        <section
            role="status"
            aria-label="Uitnodigingslink"
            data-test="uitnodigingslink"
            className="mx-4 mt-4 rounded-xl border border-violet/40 bg-violet/5 p-4 md:mx-6"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-semibold">
                        Stuur {u.naam} deze uitnodigingslink
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Er is nog geen maildienst, dus {u.email} krijgt geen
                        mail. Stuur de link zelf, bijvoorbeeld via WhatsApp.
                        Daarmee kiest {u.naam.split(" ")[0]} een wachtwoord en
                        logt in. De link werkt 7 dagen en wordt niet bewaard.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => setWeg(u.link)}
                    className="-m-1 grid size-10 shrink-0 place-items-center rounded-lg text-muted-foreground hover:bg-muted md:size-8"
                    aria-label="Sluiten"
                >
                    <X className="size-4" aria-hidden />
                </button>
            </div>
            <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                <input
                    readOnly
                    value={u.link}
                    aria-label="Uitnodigingslink"
                    onFocus={(e) => e.currentTarget.select()}
                    className="h-10 min-w-0 flex-1 rounded-lg border bg-card px-3 font-mono text-xs"
                />
                <button
                    type="button"
                    onClick={kopieer}
                    className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-aubergine px-4 text-sm font-semibold text-white hover:bg-aubergine-dark"
                >
                    <Copy className="size-4" aria-hidden />
                    {gekopieerd ? "Gekopieerd" : "Kopieer link"}
                </button>
            </div>
        </section>
    );
}

/** Balk bovenaan als een Super Admin is ingelogd als iemand anders. */
export function InloggenAlsBalk() {
    const { auth } = usePage().props as unknown as { auth: Auth };

    if (!auth.impersonating) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-3 bg-rood px-4 py-2 text-sm text-white">
            <span>
                Je bekijkt het platform als <strong>{auth.user.name}</strong>.
            </span>
            <button
                type="button"
                onClick={() => router.post(terug.url())}
                className="rounded bg-white/20 px-3 py-1 font-semibold hover:bg-white/30"
            >
                Terug naar je eigen account
            </button>
        </div>
    );
}
