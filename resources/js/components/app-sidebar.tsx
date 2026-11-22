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
import { isActief, navigatie, secties } from '@/lib/portaal';
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
                    <SidebarGroup key={groep.naam} className="px-2 py-0">
                        {groep.naam !== 'Meldingen' && (
                            <SidebarGroupLabel>{groep.naam}</SidebarGroupLabel>
                        )}
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
