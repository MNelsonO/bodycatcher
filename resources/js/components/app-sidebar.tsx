import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChartColumn,
    ChartPie,
    FileText,
    Inbox,
    KanbanSquare,
    Landmark,
    LayoutGrid,
    ListTodo,
    MessageCircle,
    Package,
    Rows3,
    Sparkles,
    Megaphone,
    Tags,
    UserRound,
    Users,
    Wallet,
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
    const { auth, displayTimezone, inboxUnread } = usePage().props;
    const owner = auth.user.role === 'owner';

    const workItems: NavItem[] = [
        { title: 'Command centre', href: '/dashboard', icon: LayoutGrid },
        {
            title: 'Inbox',
            href: '/inbox',
            icon: Inbox,
            badge: inboxUnread,
        },
        { title: 'Labels', href: '/labels', icon: Tags },
        { title: 'Ads', href: '/ads', icon: Megaphone },
        { title: 'Leads', href: '/leads', icon: Rows3 },
        { title: 'Contacts', href: '/contacts', icon: UserRound },
        { title: 'Calendar', href: '/calendar', icon: CalendarDays },
        { title: 'Follow-ups', href: '/follow-ups', icon: ListTodo },
    ];

    const moneyItems: NavItem[] = [
        { title: 'Invoices', href: '/invoices', icon: FileText },
        { title: 'Expenses', href: '/expenses', icon: Wallet },
        { title: 'Money', href: '/money', icon: Landmark },
        { title: 'Reports', href: '/reports', icon: ChartPie },
    ];

    const setupItems: NavItem[] = [
        { title: 'WhatsApp', href: '/whatsapp', icon: MessageCircle },
        { title: 'Intelligence', href: '/ai', icon: Sparkles },
        { title: 'Integrations', href: '/integrations', icon: ChartColumn },
    ];

    if (owner) {
        setupItems.push(
            { title: 'Products', href: '/products', icon: Package },
            { title: 'Pipelines', href: '/pipelines', icon: KanbanSquare },
            { title: 'Users', href: '/users', icon: Users },
        );
    }

    return (
        <Sidebar collapsible="icon" variant="sidebar">
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

            <SidebarContent className="gap-1 py-1">
                <NavMain items={workItems} label="Work" />
                <NavMain items={moneyItems} label="Money" />
                <NavMain items={setupItems} label="Setup" />
            </SidebarContent>

            <SidebarFooter>
                <p className="px-2 text-xs text-sidebar-foreground/70 group-data-[collapsible=icon]:hidden">
                    Times in {displayTimezone}
                </p>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
