import ChunkedFileInput from '@/components/crud/ChunkedFileInput';
import type { ImportProgressState } from '@/components/crud/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Download,
    FileText,
    Loader2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface FileImportProps {
    accept?: string;
    label?: string;
    onUpload: (file: File) => void;
    onUploadToken?: (token: string, fileName: string) => void;
    uploading?: boolean;
    progress?: number;
    maxSizeMB?: number;
    formatDownloadUrl?: string;
    chunked?: boolean;
    chunkEndpoint?: string;
    importProgress?: ImportProgressState | null;
    onResetProgress?: () => void;
}

export function FileImport({
    accept = '.csv,.txt',
    label = 'Import File',
    onUpload,
    onUploadToken,
    uploading = false,
    progress = 0,
    maxSizeMB = 100,
    formatDownloadUrl,
    chunked = false,
    chunkEndpoint,
    importProgress,
    onResetProgress,
}: FileImportProps) {
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [chunkedToken, setChunkedToken] = useState<{
        token: string;
        name: string;
    } | null>(null);
    const [isChunkUploading, setIsChunkUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const { errors } = usePage().props as any;

    useEffect(() => {
        if (errors?.file) {
            setError(errors.file);
        }
    }, [errors?.file]);

    const handleSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        setError(null);

        if (!file) return;

        if (file.size > maxSizeMB * 1024 * 1024) {
            setError(`File size exceeds ${maxSizeMB}MB limit`);
            return;
        }

        setSelectedFile(file);
    };

    const handleUpload = () => {
        if (chunked && chunkedToken && onUploadToken) {
            onUploadToken(chunkedToken.token, chunkedToken.name);
            return;
        }
        if (selectedFile) {
            onUpload(selectedFile);
        }
    };

    const handleClear = () => {
        setSelectedFile(null);
        setChunkedToken(null);
        setError(null);
        if (inputRef.current) inputRef.current.value = '';
    };

    // Background / completed import progress view
    if (importProgress) {
        const isRunning =
            importProgress.status === 'queued' ||
            importProgress.status === 'processing';
        const isDone = importProgress.status === 'completed';
        const isFailed = importProgress.status === 'failed';

        return (
            <div className="space-y-4">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        {isRunning && (
                            <Loader2 className="text-primary h-4 w-4 animate-spin" />
                        )}
                        {isDone && (
                            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                        )}
                        {isFailed && (
                            <AlertCircle className="text-destructive h-4 w-4" />
                        )}
                        <span className="truncate text-sm font-semibold">
                            {importProgress.file_name}
                        </span>
                    </div>
                    <Badge
                        variant={
                            isFailed
                                ? 'destructive'
                                : isDone
                                  ? 'default'
                                  : 'secondary'
                        }
                        className="capitalize"
                    >
                        {importProgress.status}
                    </Badge>
                </div>

                <Progress value={importProgress.progress} className="h-2.5" />

                <div className="grid grid-cols-3 gap-2 rounded-lg border p-3 text-center text-xs">
                    <div>
                        <p className="text-muted-foreground">Processed</p>
                        <p className="mt-0.5 font-mono text-sm font-semibold">
                            {importProgress.processed_rows}
                            {importProgress.total_rows > 0
                                ? ` / ${importProgress.total_rows}`
                                : ''}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground">Imported</p>
                        <p className="mt-0.5 font-mono text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                            {importProgress.successful_rows}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground">Failed</p>
                        <p className="text-destructive mt-0.5 font-mono text-sm font-semibold">
                            {importProgress.failed_rows}
                        </p>
                    </div>
                </div>

                {importProgress.error_message && (
                    <p className="text-destructive bg-destructive/10 rounded-md p-2.5 text-xs">
                        {importProgress.error_message}
                    </p>
                )}

                {importProgress.errors_sample?.length > 0 && (
                    <div className="bg-muted/30 max-h-36 space-y-1 overflow-y-auto rounded-md border p-2.5 text-xs">
                        <p className="text-muted-foreground font-medium">
                            Sample Row Errors:
                        </p>
                        {importProgress.errors_sample.slice(0, 10).map((err, i) => (
                            <p key={i} className="text-destructive">
                                Row {err.row}: {err.message}
                            </p>
                        ))}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-2 pt-1">
                    {importProgress.error_report_url ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            asChild
                        >
                            <a href={importProgress.error_report_url} download>
                                <Download className="mr-1.5 h-3.5 w-3.5" />
                                Download Error CSV
                            </a>
                        </Button>
                    ) : (
                        <div />
                    )}

                    {!isRunning && onResetProgress && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                handleClear();
                                onResetProgress();
                            }}
                        >
                            Import Another File
                        </Button>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <Label>{label}</Label>
                {formatDownloadUrl && (
                    <a
                        href={formatDownloadUrl}
                        download
                        className="text-primary flex items-center text-xs hover:underline"
                    >
                        <Download className="mr-1 h-3 w-3" />
                        Download Sample Format
                    </a>
                )}
            </div>

            {chunked ? (
                <ChunkedFileInput
                    accept={accept}
                    endpoint={chunkEndpoint}
                    maxSizeMB={maxSizeMB}
                    disabled={uploading}
                    placeholder="Select or drop a large CSV file to upload in chunks"
                    onChange={(val) => {
                        setError(null);
                        if (val && 'token' in val && val.token) {
                            setChunkedToken({
                                token: val.token,
                                name: val.name,
                            });
                        } else {
                            setChunkedToken(null);
                        }
                    }}
                    onUploadingChange={setIsChunkUploading}
                />
            ) : (
                <>
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
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={handleClear}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        )}
                    </div>

                    {selectedFile && (
                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                            <FileText className="h-4 w-4" />
                            <span>{selectedFile.name}</span>
                            <span className="text-xs">
                                ({(selectedFile.size / 1024 / 1024).toFixed(2)}{' '}
                                MB)
                            </span>
                        </div>
                    )}
                </>
            )}

            {error && <p className="text-destructive text-sm">{error}</p>}

            {uploading && progress > 0 && (
                <Progress value={progress} className="h-2" />
            )}

            {(selectedFile || chunkedToken) && (
                <Button
                    type="button"
                    onClick={handleUpload}
                    disabled={uploading || isChunkUploading}
                    size="sm"
                >
                    <Upload className="mr-2 h-4 w-4" />
                    {uploading ? 'Starting Import...' : 'Start Import'}
                </Button>
            )}
        </div>
    );
}
