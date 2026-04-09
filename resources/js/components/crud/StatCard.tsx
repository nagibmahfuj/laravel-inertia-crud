import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { LucideIcon } from 'lucide-react';

interface StatCardProps {
    title: string;
    value: string | number;
    change?: number;
    icon?: LucideIcon;
    description?: string;
}

export function StatCard({ title, value, change, icon: Icon, description }: StatCardProps) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
                {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
                {(change !== undefined || description) && (
                    <p className="text-xs text-muted-foreground">
                        {change !== undefined && (
                            <span
                                className={
                                    change > 0
                                        ? 'text-green-500'
                                        : change < 0
                                          ? 'text-red-500'
                                          : ''
                                }
                            >
                                {change > 0 ? '+' : ''}
                                {change}%
                            </span>
                        )}{' '}
                        {description ?? 'from last period'}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
