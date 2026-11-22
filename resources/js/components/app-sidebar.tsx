import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { UITGELICHTE_SECTIES, isActief, navigatie, secties } from '@/lib/portaal';
import { start } from '@/routes';

export function AppSidebar() {
    const page = usePage();
    const { auth, unread } = page.props;
    const huidig = page.url;
    const links = navigatie(auth.portal, auth.permissions);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={start.url()}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {secties(links, auth.portal).map((groep) => (
                    <SidebarGroup
                        key={groep.naam}
                        className={
                            UITGELICHTE_SECTIES.includes(groep.naam)
                                ? 'mx-2 my-2 w-auto rounded-xl bg-brand-gradient p-1.5 shadow-lg shadow-violet/30 group-data-[collapsible=icon]:mx-0 group-data-[collapsible=icon]:p-0'
                                : 'px-2 py-0'
                        }
                    >
                        {groep.naam !== 'Meldingen' &&
                            (UITGELICHTE_SECTIES.includes(groep.naam) ? (
                                <SidebarGroupLabel className="font-heading text-sm font-extrabold tracking-wide text-white">
                                    ✦ {groep.naam}
                                </SidebarGroupLabel>
                            ) : (
                                <SidebarGroupLabel>{groep.naam}</SidebarGroupLabel>
                            ))}
                        <SidebarMenu>
                            {groep.links.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isActief(item.href, huidig)}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href}>
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                    {item.title === 'Meldingen' && unread > 0 && (
                                        <SidebarMenuBadge className="bg-rood text-white">
                                            {unread}
                                        </SidebarMenuBadge>
                                    )}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
