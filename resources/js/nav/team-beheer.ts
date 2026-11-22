import {
    MessageSquareText,
    Package,
    SlidersHorizontal,
    UserCog,
} from "lucide-react";
import type { NavModule } from "@/lib/portaal";
import * as beheer from "@/routes/team/beheer";

/** Navigatie van de module team-en-instellingen: onderaan het teammenu (organisatie), elk item alleen met het recht. */
const nav: NavModule = {
    team: [
        {
            title: "Team en rechten",
            href: () => beheer.medewerkers.url(),
            icon: UserCog,
            order: 110,
            permission: "team.beheren",
        },
        {
            title: "Instellingen",
            href: () => beheer.instellingen.url(),
            icon: SlidersHorizontal,
            order: 120,
            permission: "instellingen.beheren",
        },
        {
            title: "Pakketten en prijzen",
            href: () => beheer.pakketten.url(),
            icon: Package,
            order: 130,
            permission: "instellingen.beheren",
        },
        {
            title: "Berichtsjablonen",
            href: () => beheer.berichtsjablonen.url(),
            icon: MessageSquareText,
            order: 140,
            permission: "instellingen.beheren",
        },
    ],
};

export default nav;
