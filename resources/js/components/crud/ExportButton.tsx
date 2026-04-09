import { Button } from '@/components/ui/button';
import { Download } from 'lucide-react';
import { useState } from 'react';

interface ExportButtonProps {
    href: string;
    label?: string;
    params?: Record<string, string>;
}

export function ExportButton({ href, label = 'Export', params }: ExportButtonProps) {
    const [loading, setLoading] = useState(false);

    const handleExport = () => {
        setLoading(true);

        // Build URL with query params
        const url = new URL(href, window.location.origin);
        if (params) {
            Object.entries(params).forEach(([key, value]) => {
                if (value) url.searchParams.set(key, value);
            });
        }

        // Trigger download via hidden link
        const link = document.createElement('a');
        link.href = url.toString();
        link.download = '';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        setTimeout(() => setLoading(false), 2000);
    };

    return (
        <Button
            variant="outline"
            size="sm"
            onClick={handleExport}
            disabled={loading}
        >
            <Download className="mr-2 h-4 w-4" />
            {loading ? 'Exporting...' : label}
        </Button>
    );
}
