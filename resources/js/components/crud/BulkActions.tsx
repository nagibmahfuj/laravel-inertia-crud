import { Button } from '@/components/ui/button';
import { Trash2, Download } from 'lucide-react';

interface BulkActionsProps {
    selectedCount: number;
    onDelete?: () => void;
    onExport?: () => void;
    children?: React.ReactNode;
}

export function BulkActions({ selectedCount, onDelete, onExport, children }: BulkActionsProps) {
    if (selectedCount === 0) return null;

    return (
        <div className="flex items-center gap-2 rounded-md bg-muted p-2">
            <span className="text-sm font-medium">
                {selectedCount} selected
            </span>
            <div className="h-4 w-px bg-border" />
            {onDelete && (
                <Button variant="destructive" size="sm" onClick={onDelete}>
                    <Trash2 className="mr-1 h-3 w-3" />
                    Delete
                </Button>
            )}
            {onExport && (
                <Button variant="outline" size="sm" onClick={onExport}>
                    <Download className="mr-1 h-3 w-3" />
                    Export
                </Button>
            )}
            {children}
        </div>
    );
}
