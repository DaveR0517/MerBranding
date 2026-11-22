import {
    CalendarDays,
    CircleUserRound,
    ShieldCheck,
    UserRoundSearch,
    Wallet,
} from "lucide-react";
import * as profiel from "@/routes/creator/profiel";
import * as screening from "@/routes/team/screening";
import type { NavModule } from "@/lib/portaal";

/** Navigatie van de module creator-profiel: screening (team) en profiel, agenda, vergoedingen, account (creator). */
const nav: NavModule = {
    team: [
        {
            title: "Screening",
            href: () => screening.index.url(),
            icon: UserRoundSearch,
            order: 45,
            permission: "creators.screenen",
        },
    ],
    creator: [
        {
            title: "Agenda",
            href: () => profiel.agenda.url(),
            icon: CalendarDays,
            mobile: true,
            order: 20,
        },
        {
            title: "Vergoedingen",
            href: () => profiel.vergoedingen.url(),
            icon: Wallet,
            order: 30,
        },
        {
            title: "Profiel",
            href: () => profiel.index.url(),
            icon: CircleUserRound,
            mobile: true,
            order: 40,
        },
        {
            title: "Account en uitbetaling",
            href: () => profiel.account.url(),
            icon: ShieldCheck,
            order: 50,
        },
    ],
};

export default nav;
