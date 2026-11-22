import React, { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import {
    AlertCircle,
    CheckCircle2,
    Download,
    FileCheck,
    Loader2,
    RotateCcw,
    UploadCloud,
    X,
} from 'lucide-react';
import type { ChunkedUploadedFile, ExistingFile } from './types';

export type { ChunkedUploadedFile, ExistingFile };

export interface ChunkedFileInputProps {
    id?: string;
    value?: ChunkedUploadedFile | { removed?: boolean } | null;
    existingFile?: ExistingFile | null;
    accept?: string;
    endpoint?: string;
    chunkSizeMB?: number;
    maxSizeMB?: number;
    maxRetries?: number;
    uploadConfig?: string;
    placeholder?: string;
    disabled?: boolean;
    onChange: (value: ChunkedUploadedFile | { removed: boolean } | null) => void;
    onUploadingChange?: (isUploading: boolean) => void;
}

function formatBytes(bytes?: number | null): string {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}

function getCsrfToken(): string {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        const token = metaTag.getAttribute('content');
        if (token) return token;
    }

    const cookies = document.cookie.split(';');
    for (const cookie of cookies) {
        const [name, ...rest] = cookie.trim().split('=');
        if (name === 'XSRF-TOKEN') {
            return decodeURIComponent(rest.join('='));
        }
    }

    return '';
}

function matchesAccept(file: File, accept?: string): boolean {
    if (!accept) return true;

    const tokens = accept
        .split(',')
        .map((t) => t.trim().toLowerCase())
        .filter(Boolean);

    if (tokens.length === 0) return true;

    const fileName = file.name.toLowerCase();
    const mimeType = (file.type || '').toLowerCase();

    return tokens.some((token) => {
        if (token.startsWith('.')) {
            return fileName.endsWith(token);
        }
        if (token.endsWith('/*')) {
            const prefix = token.slice(0, -1);
            return mimeType.startsWith(prefix);
        }
        return mimeType === token;
    });
}

export default function ChunkedFileInput({
    id,
    value,
    existingFile,
    accept,
    endpoint = '/crud/chunk-upload',
    chunkSizeMB = 5,
    maxSizeMB = 512,
    maxRetries = 3,
    uploadConfig,
    placeholder,
    disabled = false,
    onChange,
    onUploadingChange,
}: ChunkedFileInputProps) {
    const [isDragging, setIsDragging] = useState(false);
    const [uploadState, setUploadState] = useState<
        'idle' | 'uploading' | 'assembling' | 'completed' | 'error'
    >('idle');
    const [progress, setProgress] = useState(0);
    const [uploadedBytes, setUploadedBytes] = useState(0);
    const [totalBytes, setTotalBytes] = useState(0);
    const [currentChunk, setCurrentChunk] = useState(0);
    const [totalChunks, setTotalChunks] = useState(0);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const [selectedFileName, setSelectedFileName] = useState<string>('');
    const [selectedFileSize, setSelectedFileSize] = useState<number>(0);
    const [isReplaced, setIsReplaced] = useState(false);
    const [isRemoved, setIsRemoved] = useState(false);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const activeXhrRef = useRef<XMLHttpRequest | null>(null);
    const activeUploadIdRef = useRef<string | null>(null);
    const isCancelledRef = useRef<boolean>(false);
    const currentFileRef = useRef<File | null>(null);

    const chunkSizeBytes = Math.max(1, chunkSizeMB) * 1024 * 1024;
    const maxSizeBytes = Math.max(1, maxSizeMB) * 1024 * 1024;

    // Notify parent about uploading status
    useEffect(() => {
        const isUp = uploadState === 'uploading' || uploadState === 'assembling';
        onUploadingChange?.(isUp);
    }, [uploadState, onUploadingChange]);

    // Abort in-flight request on unmount
    useEffect(() => {
        return () => {
            isCancelledRef.current = true;
            if (activeXhrRef.current) {
                activeXhrRef.current.abort();
                activeXhrRef.current = null;
            }
        };
    }, []);

    const notifyServerCancel = (uploadId: string | null) => {
        if (!uploadId) return;
        const csrfToken = getCsrfToken();
        const cancelUrl = `${endpoint.replace(/\/$/, '')}/${encodeURIComponent(uploadId)}`;

        fetch(cancelUrl, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                ...(csrfToken
                    ? {
                          'X-CSRF-TOKEN': csrfToken,
                          'X-XSRF-TOKEN': csrfToken,
                      }
                    : {}),
            },
            credentials: 'same-origin',
        }).catch(() => {
            // Best-effort server cleanup
        });
    };

    const handleFileSelect = (file: File) => {
        if (!file) return;

        if (!matchesAccept(file, accept)) {
            setUploadState('error');
            setErrorMessage(
                `Unsupported file type. Allowed: ${accept}`,
            );
            if (fileInputRef.current) fileInputRef.current.value = '';
            return;
        }

        if (file.size > maxSizeBytes) {
            setUploadState('error');
            setErrorMessage(
                `File size (${formatBytes(file.size)}) exceeds the maximum limit of ${maxSizeMB} MB.`,
            );
            if (fileInputRef.current) fileInputRef.current.value = '';
            return;
        }

        currentFileRef.current = file;
        setSelectedFileName(file.name);
        setSelectedFileSize(file.size);
        setIsRemoved(false);
        setIsReplaced(true);
        startChunkedUpload(file);
    };

    const startChunkedUpload = async (file: File) => {
        isCancelledRef.current = false;
        setUploadState('uploading');
        setProgress(0);
        setUploadedBytes(0);
        setTotalBytes(file.size);
        setErrorMessage(null);

        const chunksCount = Math.max(1, Math.ceil(file.size / chunkSizeBytes));
        setTotalChunks(chunksCount);

        const uploadId = `up_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;
        activeUploadIdRef.current = uploadId;
        const csrfToken = getCsrfToken();
        const attemptsLimit = Math.max(1, maxRetries);

        let bytesAccumulated = 0;

        for (let chunkIndex = 0; chunkIndex < chunksCount; chunkIndex++) {
            if (isCancelledRef.current) {
                setUploadState('idle');
                return;
            }

            setCurrentChunk(chunkIndex + 1);

            const start = chunkIndex * chunkSizeBytes;
            const end = Math.min(file.size, start + chunkSizeBytes);
            const chunkBlob = file.slice(start, end);

            let success = false;
            let attempts = 0;

            while (!success && attempts < attemptsLimit) {
                if (isCancelledRef.current) {
                    setUploadState('idle');
                    return;
                }

                attempts++;
                try {
                    const response = await uploadSingleChunk({
                        uploadId,
                        chunkIndex,
                        totalChunks: chunksCount,
                        fileName: file.name,
                        fileSize: file.size,
                        chunkBlob,
                        csrfToken,
                        onChunkProgress: (loadedChunkBytes) => {
                            const currentTotalLoaded =
                                bytesAccumulated + loadedChunkBytes;
                            setUploadedBytes(currentTotalLoaded);
                            const percent = Math.min(
                                99,
                                Math.round(
                                    (currentTotalLoaded / file.size) * 100,
                                ),
                            );
                            setProgress(percent);
                        },
                    });

                    if (response.completed) {
                        setUploadState('assembling');
                        setProgress(100);
                        activeUploadIdRef.current = null;

                        setUploadState('completed');
                        onChange({
                            token: response.token,
                            name: response.name || file.name,
                            size: response.size || file.size,
                        });
                        return;
                    }

                    bytesAccumulated += chunkBlob.size;
                    setUploadedBytes(bytesAccumulated);
                    setProgress(
                        Math.min(
                            99,
                            Math.round((bytesAccumulated / file.size) * 100),
                        ),
                    );
                    success = true;
                } catch (err: any) {
                    if (isCancelledRef.current) {
                        setUploadState('idle');
                        return;
                    }

                    // Do not retry 4xx validation/auth errors
                    if (err?.status && err.status >= 400 && err.status < 500) {
                        setUploadState('error');
                        setErrorMessage(
                            err?.message || `Upload rejected (HTTP ${err.status}).`,
                        );
                        return;
                    }

                    if (attempts >= attemptsLimit) {
                        setUploadState('error');
                        setErrorMessage(
                            err?.message ||
                                `Chunk ${chunkIndex + 1}/${chunksCount} upload failed after ${attemptsLimit} attempts.`,
                        );
                        return;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 1500));
                }
            }
        }
    };

    const uploadSingleChunk = ({
        uploadId,
        chunkIndex,
        totalChunks,
        fileName,
        fileSize,
        chunkBlob,
        csrfToken,
        onChunkProgress,
    }: {
        uploadId: string;
        chunkIndex: number;
        totalChunks: number;
        fileName: string;
        fileSize: number;
        chunkBlob: Blob;
        csrfToken: string;
        onChunkProgress: (loaded: number) => void;
    }): Promise<any> => {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            activeXhrRef.current = xhr;

            xhr.open('POST', endpoint, true);
            xhr.setRequestHeader('Accept', 'application/json');
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                xhr.setRequestHeader('X-XSRF-TOKEN', csrfToken);
            }

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    onChunkProgress(e.loaded);
                }
            };

            xhr.onload = () => {
                activeXhrRef.current = null;
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const json = JSON.parse(xhr.responseText);
                        resolve(json);
                    } catch {
                        reject(new Error('Invalid response received from server.'));
                    }
                } else {
                    let errMsg = `Upload failed with HTTP ${xhr.status}`;
                    try {
                        const json = JSON.parse(xhr.responseText);
                        if (json?.message) errMsg = json.message;
                    } catch {
                        // Ignore JSON parse error
                    }
                    const error = new Error(errMsg) as Error & { status?: number };
                    error.status = xhr.status;
                    reject(error);
                }
            };

            xhr.onerror = () => {
                activeXhrRef.current = null;
                reject(
                    new Error(
                        'Network error during chunk upload. Please check your connection.',
                    ),
                );
            };

            xhr.ontimeout = () => {
                activeXhrRef.current = null;
                reject(new Error('Chunk upload timed out. Retrying...'));
            };

            const formData = new FormData();
            formData.append('upload_id', uploadId);
            formData.append('chunk_index', String(chunkIndex));
            formData.append('total_chunks', String(totalChunks));
            formData.append('file_name', fileName);
            formData.append('file_size', String(fileSize));
            if (uploadConfig) {
                formData.append('upload_config', uploadConfig);
            }
            formData.append('chunk', chunkBlob, 'slice.bin');

            xhr.send(formData);
        });
    };

    const handleCancel = () => {
        isCancelledRef.current = true;
        if (activeXhrRef.current) {
            activeXhrRef.current.abort();
            activeXhrRef.current = null;
        }
        notifyServerCancel(activeUploadIdRef.current);
        activeUploadIdRef.current = null;

        setUploadState('idle');
        setProgress(0);
        setUploadedBytes(0);
        setErrorMessage(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handleRemove = () => {
        handleCancel();
        setIsRemoved(true);
        setIsReplaced(false);
        setSelectedFileName('');
        setSelectedFileSize(0);
        onChange({ removed: true });
    };

    const handleRetry = () => {
        if (currentFileRef.current) {
            startChunkedUpload(currentFileRef.current);
        }
    };

    const uploadedTokenValue =
        value && 'token' in value && value.token ? value : null;

    // State 1: Active chunked upload in progress or assembling
    if (uploadState === 'uploading' || uploadState === 'assembling') {
        return (
            <div className="border-primary/30 bg-primary/5 mt-1 space-y-3 rounded-xl border p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Loader2 className="text-primary h-4 w-4 animate-spin" />
                        <span className="text-foreground text-sm font-semibold">
                            {uploadState === 'assembling'
                                ? 'Finalizing & merging file on server...'
                                : `Uploading: ${selectedFileName}`}
                        </span>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={handleCancel}
                        className="text-muted-foreground hover:text-destructive h-7 text-xs"
                    >
                        <X className="mr-1 h-3.5 w-3.5" />
                        Cancel
                    </Button>
                </div>

                <Progress value={progress} className="h-2.5" />

                <div className="text-muted-foreground flex items-center justify-between text-xs">
                    <span>
                        {uploadState === 'assembling'
                            ? 'All chunks uploaded! Reassembling...'
                            : `Chunk ${currentChunk} of ${totalChunks} • ${formatBytes(uploadedBytes)} / ${formatBytes(totalBytes)}`}
                    </span>
                    <span className="text-foreground font-mono font-medium">
                        {progress}%
                    </span>
                </div>
            </div>
        );
    }

    // State 2: Upload error
    if (uploadState === 'error') {
        return (
            <div className="border-destructive/40 bg-destructive/10 mt-1 space-y-3 rounded-xl border p-4">
                <div className="text-destructive flex items-start gap-2">
                    <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                    <div className="space-y-1">
                        <p className="text-sm font-semibold">Upload failed</p>
                        <p className="text-destructive/90 text-xs">
                            {errorMessage}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2 pt-1">
                    {currentFileRef.current && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={handleRetry}
                            className="h-8 gap-1.5 text-xs"
                        >
                            <RotateCcw className="h-3.5 w-3.5" />
                            Retry Upload
                        </Button>
                    )}
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={handleCancel}
                        className="text-muted-foreground h-8 text-xs"
                    >
                        Choose Different File
                    </Button>
                </div>
            </div>
        );
    }

    // State 3: Newly completed upload ready for form submit
    if (uploadState === 'completed' || uploadedTokenValue) {
        return (
            <div className="mt-1 flex items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4">
                <div className="flex items-center gap-3">
                    <div className="rounded-full bg-emerald-500/20 p-2 text-emerald-500">
                        <CheckCircle2 className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-foreground text-sm font-semibold">
                                {uploadedTokenValue?.name || selectedFileName}
                            </span>
                            <Badge
                                variant="outline"
                                className="border-emerald-500/30 bg-emerald-500/10 text-xs text-emerald-500"
                            >
                                Ready
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {formatBytes(
                                uploadedTokenValue?.size || selectedFileSize,
                            )}{' '}
                            • Uploaded in {chunkSizeMB}MB chunks
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={disabled}
                        className="h-8 text-xs"
                    >
                        Replace
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={handleRemove}
                        disabled={disabled}
                        className="text-muted-foreground hover:text-destructive h-8 text-xs"
                    >
                        <X className="h-4 w-4" />
                    </Button>
                </div>

                <input
                    ref={fileInputRef}
                    id={id}
                    type="file"
                    accept={accept}
                    disabled={disabled}
                    className="hidden"
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) handleFileSelect(file);
                    }}
                />
            </div>
        );
    }

    // State 4: Existing file attached in edit mode
    if (existingFile && existingFile.name && !isRemoved && !isReplaced) {
        return (
            <div className="border-border bg-muted/20 mt-1 flex items-center justify-between rounded-xl border p-4">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary rounded-lg p-2.5">
                        <FileCheck className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-foreground text-sm font-semibold">
                                {existingFile.name}
                            </span>
                            <Badge variant="secondary" className="text-xs">
                                Current File
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {existingFile.size
                                ? formatBytes(existingFile.size)
                                : 'Stored on server'}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {existingFile.url && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            asChild
                            className="text-muted-foreground h-8 text-xs"
                        >
                            <a
                                href={existingFile.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Download className="mr-1 h-3.5 w-3.5" />
                                Download
                            </a>
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={disabled}
                        className="h-8 text-xs"
                    >
                        Replace
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={handleRemove}
                        disabled={disabled}
                        className="text-muted-foreground hover:text-destructive h-8 text-xs"
                    >
                        <X className="h-4 w-4" />
                    </Button>
                </div>

                <input
                    ref={fileInputRef}
                    id={id}
                    type="file"
                    accept={accept}
                    disabled={disabled}
                    className="hidden"
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) handleFileSelect(file);
                    }}
                />
            </div>
        );
    }

    // State 5: Default dropzone / file picker
    return (
        <div
            onDragOver={(e) => {
                e.preventDefault();
                setIsDragging(true);
            }}
            onDragLeave={() => setIsDragging(false)}
            onDrop={(e) => {
                e.preventDefault();
                setIsDragging(false);
                const file = e.dataTransfer.files?.[0];
                if (file) handleFileSelect(file);
            }}
            onClick={() => !disabled && fileInputRef.current?.click()}
            className={`mt-1 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-6 text-center transition-all ${
                isDragging
                    ? 'border-primary bg-primary/10'
                    : 'border-border/80 bg-muted/10 hover:border-primary/50 hover:bg-muted/30'
            } ${disabled ? 'pointer-events-none opacity-50' : ''}`}
        >
            <div className="bg-primary/10 text-primary rounded-full p-3">
                <UploadCloud className="h-6 w-6" />
            </div>
            <div>
                <p className="text-foreground text-sm font-semibold">
                    {placeholder || 'Click to select file or drag & drop here'}
                </p>
                <p className="text-muted-foreground mt-0.5 text-xs">
                    {accept
                        ? `Accepted: ${accept.split(',').join(', ')}`
                        : 'Large files supported'}{' '}
                    • Up to {maxSizeMB}MB ({chunkSizeMB}MB chunks)
                </p>
            </div>

            <input
                ref={fileInputRef}
                id={id}
                type="file"
                accept={accept}
                disabled={disabled}
                className="hidden"
                onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) handleFileSelect(file);
                }}
            />
        </div>
    );
}

