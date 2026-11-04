import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Inbox,
    KanbanSquare,
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth, displayTimezone, inboxUnread } = usePage().props;
    const owner = auth.user.role === 'owner';

    const workItems: NavItem[] = [
        { title: 'Command centre', href: dashboard(), icon: LayoutGrid },
        { title: 'Inbox', href: '/inbox', icon: Inbox, badge: inboxUnread },
        { title: 'Calendar', href: '/calendar', icon: CalendarDays },
        { title: 'Leads', href: '/leads', icon: Rows3 },
        { title: 'Contacts', href: '/contacts', icon: UserRound },
        { title: 'Follow-ups', href: '/follow-ups', icon: ListTodo },
    ];

    const setupItems: NavItem[] = [
        { title: 'WhatsApp', href: '/whatsapp', icon: MessageCircle },
        { title: 'Intelligence', href: '/ai', icon: Sparkles },
    ];

    if (owner) {
        setupItems.push(
            { title: 'Products', href: '/products', icon: Package },
            { title: 'Pipelines', href: '/pipelines', icon: KanbanSquare },
            { title: 'Users', href: '/users', icon: Users },
        );
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4">
                <NavMain items={workItems} label="Work" />
                <NavMain items={setupItems} label="Setup" />
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
