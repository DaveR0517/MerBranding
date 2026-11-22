import {
    BarChart3,
    FileText,
    Film,
    Gauge,
    Receipt,
    TrendingUp,
    Wallet,
} from "lucide-react";
import * as creator from "@/routes/creator";
import * as ondernemer from "@/routes/ondernemer";
import * as team from "@/routes/team";
import type { NavModule } from "@/lib/portaal";

/** Navigatie van de module cijfers en geld. */
const nav: NavModule = {
    team: [
        {
            title: "Dashboard",
            href: () => team.dashboard.url(),
            icon: Gauge,
            mobile: true,
            order: 5,
            permission: "statistieken.bekijken",
        },
        {
            title: "Cijfers",
            href: () => team.cijfers.url(),
            icon: BarChart3,
            order: 35,
            permission: "samenwerkingen.bekijken",
        },
        {
            title: "Financieel",
            href: () => team.financieel.url(),
            icon: Wallet,
            order: 70,
            permission: "financieel.bekijken",
        },
    ],
    creator: [
        {
            title: "Cijfers",
            href: () => creator.cijfers.url(),
            icon: TrendingUp,
            mobile: true,
            order: 30,
        },
    ],
    ondernemer: [
        {
            title: "Resultaten",
            href: () => ondernemer.resultaten.url(),
            icon: TrendingUp,
            mobile: true,
            order: 30,
        },
        {
            title: "Mediabibliotheek",
            href: () => ondernemer.mediabibliotheek.url(),
            icon: Film,
            order: 40,
        },
        {
            title: "Maandrapport",
            href: () => ondernemer.maandrapport.url(),
            icon: FileText,
            order: 45,
        },
        {
            title: "Facturen",
            href: () => ondernemer.facturen.url(),
            icon: Receipt,
            order: 80,
        },
    ],
};

export default nav;
