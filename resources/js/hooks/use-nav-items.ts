import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    ArrowRightLeft,
    BarChart3,
    Bell,
    BrainCircuit,
    Calendar,
    CheckCircle,
    Clock,
    Database,
    FileChartColumn,
    FileText,
    Folder,
    Gift,
    Globe,
    HelpCircle,
    Key,
    LayoutGrid,
    Lock,
    Mail,
    Map,
    MapPin,
    Music,
    Package,
    QrCode,
    Settings,
    Shield,
    ShieldAlert,
    ShoppingBag,
    Star,
    Tag,
    TicketCheck,
    Trophy,
    UserCog,
    Users,
    UsersRound,
    Video,
} from 'lucide-react';

/**
 * Map of icon name strings (from backend) to actual lucide-react components.
 * Add new icons here as needed.
 */
const iconMap: Record<string, LucideIcon> = {
    Activity,
    ArrowRightLeft,
    BarChart3,
    Bell,
    BrainCircuit,
    Calendar,
    CheckCircle,
    Clock,
    Database,
    FileChartColumn,
    FileText,
    Folder,
    Gift,
    Globe,
    HelpCircle,
    Key,
    LayoutGrid,
    Lock,
    Mail,
    Map,
    MapPin,
    Music,
    Package,
    QrCode,
    Settings,
    Shield,
    ShieldAlert,
    ShoppingBag,
    Star,
    Tag,
    TicketCheck,
    Trophy,
    UserCog,
    Users,
    UsersRound,
    Video,
};

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    items?: NavItem[];
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

interface NavItemData {
    title: string;
    href?: string;
    icon?: string;
    items?: NavItemData[];
}

interface NavGroupData {
    title: string;
    items: NavItemData[];
}

/**
 * Converts backend NavItemData (with icon as string) to frontend NavItem (with icon as LucideIcon).
 */
function mapNavItem(item: NavItemData): NavItem {
    return {
        title: item.title,
        href: item.href ?? '#',
        icon: item.icon ? (iconMap[item.icon] ?? null) : null,
        items: item.items ? item.items.map(mapNavItem) : undefined,
    };
}

/**
 * Reads from Inertia shared data and converts to frontend structures.
 * Expects 'navGroups' to be shared in HandleInertiaRequests.
 */
export function useNavGroups(): NavGroup[] {
    const { navGroups } = usePage<any>().props;

    if (!navGroups || !Array.isArray(navGroups)) {
        return [];
    }

    return navGroups.map((group: NavGroupData) => ({
        title: group.title,
        items: group.items.map(mapNavItem),
    }));
}

/**
 * Returns a flat list of all navigation items that have an href.
 */
export function useNavItems(): NavItem[] {
    const groups = useNavGroups();

    const flattenItems = (items: NavItem[]): NavItem[] => {
        return items.reduce((acc: NavItem[], item) => {
            if (item.href && item.href !== '#') {
                acc.push(item);
            }
            if (item.items) {
                acc.push(...flattenItems(item.items));
            }
            return acc;
        }, []);
    };

    return flattenItems(groups.flatMap((group) => group.items));
}
