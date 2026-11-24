import { Link, usePage } from "@inertiajs/react";
import { Building2, Clapperboard, GraduationCap, Users } from "lucide-react";
import type { Portal } from "@/types/auth";
import type { Auth } from "@/types/auth";
import { cn } from "@/lib/utils";

const ICOON: Record<Exclude<Portal, "geen">, typeof Users> = {
    team: Users,
    ondernemer: Building2,
    creator: Clapperboard,
    academy: GraduationCap,
};

/**
 * Wissel tussen omgevingen voor wie er meer dan één heeft, bijvoorbeeld een teamlid met een eigen zaak.
 * Het menu eronder hoort bij de gekozen omgeving.
 */
export function OmgevingWissel() {
    const { auth } = usePage().props as unknown as { auth: Auth };

    if (!auth.omgevingen || auth.omgevingen.length < 2) {
        return null;
    }

    return (
        <nav
            aria-label="Wissel van omgeving"
            data-test="omgeving-wissel"
            className="mx-2 grid gap-1 rounded-xl bg-white/5 p-1 group-data-[collapsible=icon]:hidden"
        >
            {auth.omgevingen.map((o) => {
                const actief = o.key === auth.portal;
                const Icoon = o.key !== "geen" ? ICOON[o.key] : Users;

                return (
                    <Link
                        key={o.key}
                        href={o.url}
                        aria-current={actief ? "page" : undefined}
                        className={cn(
                            "flex min-h-10 items-center gap-2 rounded-lg px-2.5 text-sm font-semibold transition md:min-h-9",
                            actief
                                ? "bg-white text-aubergine shadow-sm"
                                : "text-white/75 hover:bg-white/10 hover:text-white",
                        )}
                    >
                        <Icoon className="size-4 shrink-0" aria-hidden />
                        <span className="truncate">{o.naam}</span>
                    </Link>
                );
            })}
        </nav>
    );
}
