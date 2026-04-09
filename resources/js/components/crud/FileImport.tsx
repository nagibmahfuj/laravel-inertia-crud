import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Upload, FileText, X, Download } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';

interface FileImportProps {
    accept?: string; // e.g., '.csv,.xlsx'
    label?: string;
    onUpload: (file: File) => void;
    uploading?: boolean;
    progress?: number;
    maxSizeMB?: number;
    formatDownloadUrl?: string;
}

export function FileImport({
    accept = '.csv,.xlsx,.xls',
    label = 'Import File',
    onUpload,
    uploading = false,
    progress = 0,
    maxSizeMB = 100,
    formatDownloadUrl,
}: FileImportProps) {
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const { errors } = usePage().props;

    useEffect(() => {
        if (errors?.file) {
            setError(errors?.file);
        }
    }, [errors?.file]);

    const handleSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        setError(null);

        if (!file) return;

        // Validate size
        if (file.size > maxSizeMB * 1024 * 1024) {
            setError(`File size exceeds ${maxSizeMB}MB limit`);
            return;
        }

        setSelectedFile(file);
    };

    const handleUpload = () => {
        if (selectedFile) {
            onUpload(selectedFile);
        }
    };

    const handleClear = () => {
        setSelectedFile(null);
        setError(null);
        if (inputRef.current) inputRef.current.value = '';
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <Label>{label}</Label>
                {formatDownloadUrl && (
                    <a
                        href={formatDownloadUrl}
                        download
                        className="flex items-center text-xs text-primary hover:underline"
                    >
                        <Download className="mr-1 h-3 w-3" />
                        Download Format
                    </a>
                )}
            </div>
            <div className="flex items-center gap-2">
                <Input
                    ref={inputRef}
                    type="file"
                    accept={accept}
                    onChange={handleSelect}
                    disabled={uploading}
                    className="flex-1"
                />
                {selectedFile && !uploading && (
                    <Button variant="ghost" size="icon" onClick={handleClear}>
                        <X className="h-4 w-4" />
                    </Button>
                )}
            </div>

            {selectedFile && (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <FileText className="h-4 w-4" />
                    <span>{selectedFile.name}</span>
                    <span className="text-xs">
                        ({(selectedFile.size / 1024 / 1024).toFixed(2)} MB)
                    </span>
                </div>
            )}

            {error && <p className="text-sm text-destructive">{error}</p>}

            {uploading && progress > 0 && (
                <Progress value={progress} className="h-2" />
            )}

            {selectedFile && (
                <Button onClick={handleUpload} disabled={uploading} size="sm">
                    <Upload className="mr-2 h-4 w-4" />
                    {uploading ? 'Uploading...' : 'Upload'}
                </Button>
            )}
        </div>
    );
}
