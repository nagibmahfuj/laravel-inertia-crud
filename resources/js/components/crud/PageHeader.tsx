import { Button } from '@/components/ui/button';
import { Plus, Download, Upload } from 'lucide-react';
import { Link } from '@inertiajs/react';

interface Action {
    label: string;
    icon?: 'add' | 'export' | 'import';
    href?: string;
    onClick?: () => void;
    variant?: 'default' | 'outline' | 'secondary' | 'ghost' | 'destructive';
}

interface PageHeaderProps {
    title: string;
    description?: string;
    actions?: Action[];
    children?: React.ReactNode;
}

const iconMap = {
    add: Plus,
    export: Download,
    import: Upload,
};

export function PageHeader({ title, description, actions, children }: PageHeaderProps) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 className="text-xl font-semibold tracking-tight">{title}</h2>
                {description && (
                    <p className="text-sm text-muted-foreground">{description}</p>
                )}
            </div>
            <div className="flex items-center gap-2">
                {actions?.map((action, i) => {
                    const Icon = action.icon ? iconMap[action.icon] : null;
                    const btn = (
                        <Button
                            key={i}
                            variant={action.variant ?? 'default'}
                            size="sm"
                            onClick={action.onClick}
                        >
                            {Icon && <Icon className="mr-2 h-4 w-4" />}
                            {action.label}
                        </Button>
                    );

                    if (action.href) {
                        return (
                            <Link key={i} href={action.href}>
                                {btn}
                            </Link>
                        );
                    }

                    return btn;
                })}
                {children}
            </div>
        </div>
    );
}
