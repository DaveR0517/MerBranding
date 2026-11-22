import {
    CircleUserRound,
    ListChecks,
    MapPin,
    Package,
    UsersRound,
} from "lucide-react";
import type { NavModule } from "@/lib/portaal";
import beheer from "@/routes/ondernemer/beheer";
import { edit as account } from "@/routes/profile";

/** Navigatie van module ondernemer-beheer. De teamkant (detail van een bedrijf) linkt vanuit de bedrijvenlijst. */
const nav: NavModule = {
    ondernemer: [
        {
            title: "Abonnement",
            href: () => beheer.abonnement.url(),
            icon: Package,
            order: 50,
        },
        {
            title: "Vestigingen",
            href: () => beheer.vestigingen.url(),
            icon: MapPin,
            order: 55,
        },
        {
            title: "Gebruikers",
            href: () => beheer.gebruikers.url(),
            icon: UsersRound,
            order: 60,
        },
        {
            title: "Aan de slag",
            href: () => beheer.welkom.url(),
            icon: ListChecks,
            order: 75,
        },
        {
            title: "Account",
            href: () => account.url(),
            icon: CircleUserRound,
            order: 90,
        },
    ],
};

export default nav;
