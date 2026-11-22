import { ConfirmDialog } from '@/components/crud/ConfirmDialog';
import { resolveLucideIcon } from '@/components/crud/icon-map';
import type { CustomActionConfig } from '@/components/crud/types';
import { Button } from '@/components/ui/button';
import { Download, RotateCcw, Trash2 } from 'lucide-react';

interface BulkActionsProps {
    selectedCount: number;
    onDelete?: () => void;
    onRestore?: () => void;
    onForceDelete?: () => void;
    onExport?: () => void;
    customActions?: CustomActionConfig[];
    onCustomAction?: (action: CustomActionConfig) => void;
    children?: React.ReactNode;
}

export function BulkActions({
    selectedCount,
    onDelete,
    onRestore,
    onForceDelete,
    onExport,
    customActions,
    onCustomAction,
    children,
}: BulkActionsProps) {
    if (selectedCount === 0) return null;

    return (
        <div className="bg-muted flex flex-wrap items-center gap-2 rounded-md p-2">
            <span className="px-1 text-sm font-medium">
                {selectedCount} selected
            </span>
            <div className="bg-border h-4 w-px" />

            {onRestore && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onRestore}
                >
                    <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                    Restore
                </Button>
            )}

            {onDelete && (
                <ConfirmDialog
                    trigger={
                        <Button type="button" variant="destructive" size="sm">
                            <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                            Delete
                        </Button>
                    }
                    title={`Delete ${selectedCount} selected record(s)?`}
                    description="This action will remove the selected records."
                    confirmLabel="Delete"
                    onConfirm={onDelete}
                />
            )}

            {onForceDelete && (
                <ConfirmDialog
                    trigger={
                        <Button type="button" variant="destructive" size="sm">
                            <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                            Delete Permanently
                        </Button>
                    }
                    title={`Permanently delete ${selectedCount} record(s)?`}
                    description="This cannot be undone and will remove associated files."
                    confirmLabel="Delete Permanently"
                    onConfirm={onForceDelete}
                />
            )}

            {onExport && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onExport}
                >
                    <Download className="mr-1.5 h-3.5 w-3.5" />
                    Export
                </Button>
            )}

            {customActions?.map((action) => {
                const Icon = resolveLucideIcon(action.icon);
                const triggerBtn = (
                    <Button
                        key={action.key}
                        type="button"
                        variant={action.variant ?? 'outline'}
                        size="sm"
                        onClick={
                            action.confirm
                                ? undefined
                                : () => onCustomAction?.(action)
                        }
                    >
                        {Icon && <Icon className="mr-1.5 h-3.5 w-3.5" />}
                        {action.label}
                    </Button>
                );

                if (action.confirm) {
                    return (
                        <ConfirmDialog
                            key={action.key}
                            trigger={triggerBtn}
                            title={
                                action.confirm.title ??
                                `${action.label} (${selectedCount} selected)?`
                            }
                            description={
                                action.confirm.description ??
                                'Are you sure you want to run this action?'
                            }
                            confirmLabel={
                                action.confirm.confirmLabel ?? action.label
                            }
                            variant={
                                action.variant === 'destructive'
                                    ? 'destructive'
                                    : 'default'
                            }
                            onConfirm={() => onCustomAction?.(action)}
                        />
                    );
                }

                return triggerBtn;
            })}

            {children}
        </div>
    );
}
