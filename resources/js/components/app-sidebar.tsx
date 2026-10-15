import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    LayoutGrid,
    ListTodo,
    MessageCircle,
    Package,
    Rows3,
    Sparkles,
    UserRound,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth, displayTimezone } = usePage().props;
    const owner = auth.user.role === 'owner';

    const mainNavItems: NavItem[] = [
        { title: 'Command centre', href: '/dashboard', icon: LayoutGrid },
        { title: 'Leads', href: '/leads', icon: Rows3 },
        { title: 'Contacts', href: '/contacts', icon: UserRound },
        { title: 'Calendar', href: '/calendar', icon: CalendarDays },
        { title: 'WhatsApp', href: '/whatsapp', icon: MessageCircle },
        { title: 'Intelligence', href: '/ai', icon: Sparkles },
        { title: 'Follow-ups', href: '/follow-ups', icon: ListTodo },
    ];

    if (owner) {
        mainNavItems.push(
            { title: 'Products', href: '/products', icon: Package },
            { title: 'Pipelines', href: '/pipelines', icon: Rows3 },
            { title: 'Users', href: '/users', icon: Users },
        );
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <p className="px-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                    Times in {displayTimezone}
                </p>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
