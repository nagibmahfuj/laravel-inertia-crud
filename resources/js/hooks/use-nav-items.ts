import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Calendar,
    FileChartColumn,
    HelpCircle,
    LayoutGrid,
    MapPin,
    Package,
    QrCode,
    ShieldAlert,
    Users,
    UsersRound,
    BrainCircuit,
    Gift,
    TicketCheck,
    ArrowRightLeft,
    Map,
    UserCog,
    Trophy,
} from 'lucide-react';

/**
 * Map of icon name strings (from backend) to actual lucide-react components.
 * Add new icons here as needed.
 */
const iconMap: Record<string, LucideIcon> = {
    LayoutGrid,
    Users,
    UsersRound,
    Calendar,
    HelpCircle,
    BrainCircuit,
    QrCode,
    Gift,
    TicketCheck,
    MapPin,
    Package,
    ArrowRightLeft,
    ShieldAlert,
    FileChartColumn,
    Map,
    UserCog,
    Trophy,
};

export interface NavItem {
    title: string;
    href?: string;
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
        href: item.href,
        icon: item.icon ? iconMap[item.icon] : null,
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
            if (item.href) {
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
