import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type StatusVariant = 'default' | 'success' | 'warning' | 'danger' | 'info';

interface StatusBadgeProps {
    status: string;
    variant?: StatusVariant;
    className?: string;
}

const variantMap: Record<StatusVariant, string> = {
    default: '',
    success:
        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    warning:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    danger: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
};

/**
 * Auto-detect variant from common status strings if not provided.
 */
const autoVariant = (status: string): StatusVariant => {
    const s = status.toLowerCase();
    if (
        [
            'active',
            'completed',
            'collected',
            'success',
            'verified',
            'imported',
            'scanned',
            'claimed',
            'approved',
            'received',
        ].includes(s)
    )
        return 'success';
    if (['pending', 'processing'].includes(s)) return 'warning';
    if (['failed', 'inactive', 'expired', 'damaged'].includes(s))
        return 'danger';
    if (['unused', 'new', 'ready'].includes(s)) return 'info';
    return 'default';
};

export function StatusBadge({ status, variant, className }: StatusBadgeProps) {
    const resolvedVariant = variant ?? autoVariant(status);

    return (
        <Badge
            variant="outline"
            className={cn(
                'font-medium capitalize',
                variantMap[resolvedVariant],
                className,
            )}
        >
            {status}
        </Badge>
    );
}
