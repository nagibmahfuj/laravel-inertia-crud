import React, { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    Bold,
    Check,
    ChevronsUpDown,
    Code,
    Download,
    Eye,
    FileCheck,
    Heading2,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Loader2,
    Plus,
    RefreshCw,
    Trash2,
    UploadCloud,
    X,
} from 'lucide-react';
import ChunkedFileInput from './ChunkedFileInput';
import type { FieldConfig, VisibleWhenRule } from './types';

export type { FieldConfig };

export function formatDateTimeLocal(val: any): string {
    if (!val) return '';
    try {
        const d = new Date(val);
        if (isNaN(d.getTime())) {
            if (typeof val === 'string') {
                return val.replace(' ', 'T').slice(0, 16);
            }
            return '';
        }
        const pad = (n: number) => n.toString().padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    } catch {
        return '';
    }
}

export function formatDateInput(val: any): string {
    if (!val) return '';
    if (typeof val === 'string') {
        const datePart = val.split(' ')[0].split('T')[0];
        if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
            return datePart;
        }
    }
    const d = new Date(val);
    if (isNaN(d.getTime())) return '';
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function slugifyText(input: string): string {
    return input
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export function evaluateVisibleWhen(
    rule?: VisibleWhenRule | null,
    allValues?: Record<string, any>,
): boolean {
    if (!rule || !rule.field) return true;
    const val = allValues?.[rule.field];

    if (rule.equals !== undefined) return String(val ?? '') === String(rule.equals);
    if (rule.not_equals !== undefined)
        return String(val ?? '') !== String(rule.not_equals);
    if (Array.isArray(rule.in))
        return rule.in.map(String).includes(String(val ?? ''));
    if (Array.isArray(rule.not_in))
        return !rule.not_in.map(String).includes(String(val ?? ''));
    if (rule.truthy) return Boolean(val && val !== '0' && val !== 'false');
    if (rule.falsy) return !val || val === '0' || val === 'false';
    if (rule.empty)
        return (
            val === null ||
            val === undefined ||
            val === '' ||
            (Array.isArray(val) && val.length === 0)
        );
    if (rule.not_empty)
        return !(
            val === null ||
            val === undefined ||
            val === '' ||
            (Array.isArray(val) && val.length === 0)
        );

    return true;
}

interface FormFieldProps {
    field: FieldConfig;
    value: any;
    error?: string;
    errors?: Record<string, string>;
    allValues?: any;
    onChange: (key: string, value: any) => void;
    onUploadingChange?: (key: string, isUploading: boolean) => void;
}

export function FormField({
    field,
    value,
    error,
    errors,
    allValues,
    onChange,
    onUploadingChange,
}: FormFieldProps) {
    if (field.type === 'hidden') return null;
    if (!evaluateVisibleWhen(field.visible_when, allValues)) return null;

    const id = `field-${field.key}`;

    const renderField = () => {
        switch (field.type) {
            case 'options-builder': {
                const opts = (() => {
                    try {
                        return Array.isArray(value)
                            ? value
                            : JSON.parse(value || '[]');
                    } catch {
                        return [];
                    }
                })();

                const correctIndex = allValues?.correct_option_index;

                return (
                    <div className="bg-muted/20 mt-2 space-y-2 rounded-md border p-3">
                        {opts.map((opt: string, i: number) => (
                            <div key={i} className="flex items-center gap-2">
                                <span className="text-muted-foreground w-6 text-center text-sm font-medium">
                                    {i + 1}.
                                </span>
                                <Input
                                    value={opt}
                                    onChange={(e) => {
                                        const newOpts = [...opts];
                                        newOpts[i] = e.target.value;
                                        onChange(field.key, newOpts);
                                    }}
                                    placeholder={`Option ${i + 1}`}
                                />
                                <div className="mr-2 ml-2 flex items-center space-x-2">
                                    <Checkbox
                                        id={`correct-${i}`}
                                        checked={
                                            String(correctIndex) === String(i)
                                        }
                                        onCheckedChange={() => {
                                            onChange('correct_option_index', i);
                                        }}
                                    />
                                    <Label
                                        htmlFor={`correct-${i}`}
                                        className="cursor-pointer text-xs font-normal"
                                    >
                                        Correct
                                    </Label>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => {
                                        const newOpts = opts.filter(
                                            (_: string, idx: number) =>
                                                idx !== i,
                                        );
                                        onChange(field.key, newOpts);
                                        if (
                                            String(correctIndex) === String(i)
                                        ) {
                                            onChange('correct_option_index', 0);
                                        } else if (Number(correctIndex) > i) {
                                            onChange(
                                                'correct_option_index',
                                                Number(correctIndex) - 1,
                                            );
                                        }
                                    }}
                                    type="button"
                                >
                                    <Trash2 className="text-destructive h-4 w-4" />
                                </Button>
                            </div>
                        ))}
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            onClick={() => {
                                onChange(field.key, [
                                    ...opts,
                                    `Option ${opts.length + 1}`,
                                ]);
                            }}
                        >
                            Add Option
                        </Button>
                    </div>
                );
            }

            case 'textarea':
                return (
                    <Textarea
                        id={id}
                        value={value ?? ''}
                        onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                            onChange(field.key, e.target.value)
                        }
                        placeholder={field.placeholder}
                        disabled={field.disabled}
                        className="mt-1"
                        rows={4}
                    />
                );

            case 'rich-text':
                return (
                    <RichTextMarkdownField
                        id={id}
                        value={String(value ?? '')}
                        placeholder={field.placeholder}
                        disabled={field.disabled}
                        onChange={(v) => onChange(field.key, v)}
                    />
                );

            case 'select':
                return (
                    <ComboboxField
                        field={field}
                        value={value}
                        onChange={onChange}
                        id={id}
                    />
                );

            case 'radio': {
                const options = field.options || [];
                const currentVal = value === null || value === undefined ? '' : String(value);
                return (
                    <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {options.map((opt) => {
                            const optVal = String(opt.value);
                            const isChecked = currentVal === optVal;
                            return (
                                <label
                                    key={optVal}
                                    className={cn(
                                        'flex cursor-pointer items-center gap-2.5 rounded-lg border p-3 text-sm transition-colors',
                                        isChecked
                                            ? 'border-primary bg-primary/5 font-medium'
                                            : 'border-border hover:bg-muted/30',
                                        field.disabled &&
                                            'pointer-events-none opacity-50',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name={field.key}
                                        value={optVal}
                                        checked={isChecked}
                                        disabled={field.disabled}
                                        onChange={() =>
                                            onChange(field.key, optVal)
                                        }
                                        className="accent-primary h-4 w-4"
                                    />
                                    <span>{opt.label}</span>
                                </label>
                            );
                        })}
                    </div>
                );
            }

            case 'checkbox':
            case 'toggle': {
                const isChecked = Boolean(
                    value && value !== '0' && value !== 'false',
                );
                return (
                    <div className="border-border/80 bg-muted/20 mt-1 flex items-center space-x-3 rounded-lg border p-3.5 transition-colors">
                        <Checkbox
                            id={id}
                            checked={isChecked}
                            onCheckedChange={(checked) =>
                                onChange(field.key, Boolean(checked))
                            }
                            disabled={field.disabled}
                        />
                        <div className="space-y-0.5">
                            <Label
                                htmlFor={id}
                                className="cursor-pointer text-sm font-medium"
                            >
                                {field.label}
                                {field.required && (
                                    <span className="text-destructive"> *</span>
                                )}
                            </Label>
                            {field.placeholder && (
                                <p className="text-muted-foreground text-xs">
                                    {field.placeholder}
                                </p>
                            )}
                        </div>
                    </div>
                );
            }

            case 'image':
                return (
                    <StandardFileField
                        id={id}
                        field={field}
                        value={value}
                        isImage
                        onChange={(v) => onChange(field.key, v)}
                    />
                );

            case 'file':
                return (
                    <StandardFileField
                        id={id}
                        field={field}
                        value={value}
                        isImage={false}
                        onChange={(v) => onChange(field.key, v)}
                    />
                );

            case 'chunked-file':
                return (
                    <ChunkedFileInput
                        id={id}
                        value={value}
                        existingFile={field.existing_file ?? undefined}
                        accept={field.accept}
                        endpoint={field.endpoint}
                        chunkSizeMB={field.chunk_size_mb}
                        maxSizeMB={field.max_size_mb}
                        maxRetries={field.max_retries}
                        uploadConfig={field.upload_config}
                        placeholder={field.placeholder}
                        disabled={field.disabled}
                        onChange={(val) => onChange(field.key, val)}
                        onUploadingChange={(isUp) =>
                            onUploadingChange?.(field.key, isUp)
                        }
                    />
                );

            case 'slug':
                return (
                    <SlugField
                        id={id}
                        field={field}
                        value={String(value ?? '')}
                        sourceValue={
                            field.slug_from
                                ? String(allValues?.[field.slug_from] ?? '')
                                : undefined
                        }
                        onChange={(v) => onChange(field.key, v)}
                    />
                );

            case 'tags':
                return (
                    <TagsField
                        id={id}
                        value={value}
                        placeholder={field.placeholder}
                        disabled={field.disabled}
                        onChange={(v) => onChange(field.key, v)}
                    />
                );

            case 'color':
                return (
                    <div className="mt-1 flex items-center gap-2">
                        <input
                            type="color"
                            value={
                                typeof value === 'string' &&
                                /^#[0-9a-fA-F]{6}$/.test(value)
                                    ? value
                                    : '#000000'
                            }
                            onChange={(e) =>
                                onChange(field.key, e.target.value)
                            }
                            disabled={field.disabled}
                            className="border-input h-9 w-12 cursor-pointer rounded-md border bg-transparent p-1"
                        />
                        <Input
                            id={id}
                            type="text"
                            value={value ?? ''}
                            onChange={(e) =>
                                onChange(field.key, e.target.value)
                            }
                            placeholder={field.placeholder ?? '#0ea5e9'}
                            disabled={field.disabled}
                            className="font-mono"
                        />
                    </div>
                );

            case 'key-value':
                return (
                    <KeyValueField
                        value={value}
                        disabled={field.disabled}
                        onChange={(v) => onChange(field.key, v)}
                    />
                );

            case 'datetime': {
                const formattedValue = formatDateTimeLocal(value);
                return (
                    <Input
                        id={id}
                        type="datetime-local"
                        value={formattedValue}
                        onChange={(e) => onChange(field.key, e.target.value)}
                        disabled={field.disabled}
                        className="mt-1"
                    />
                );
            }

            case 'date': {
                const formattedValue = formatDateInput(value);
                return (
                    <Input
                        id={id}
                        type="date"
                        value={formattedValue}
                        onChange={(e) => onChange(field.key, e.target.value)}
                        disabled={field.disabled}
                        className="mt-1"
                    />
                );
            }

            case 'repeater': {
                const items = Array.isArray(value) ? value : [];
                return (
                    <div className="bg-muted/10 mt-2 space-y-4 rounded-md border p-4">
                        {items.map((item: any, index: number) => (
                            <div
                                key={index}
                                className="flex items-end gap-3 border-b pb-4 last:border-0 last:pb-0"
                            >
                                <div className="grid flex-1 grid-cols-2 gap-3">
                                    {field.fields?.map((f: FieldConfig) => {
                                        const nestedKey = `${field.key}.${index}.${f.key}`;
                                        const nestedError = errors
                                            ? errors[nestedKey]
                                            : undefined;

                                        return (
                                            <div key={f.key}>
                                                <FormField
                                                    field={f}
                                                    value={item[f.key]}
                                                    error={nestedError}
                                                    errors={errors}
                                                    allValues={item}
                                                    onChange={(k, v) => {
                                                        const newItems = [
                                                            ...items,
                                                        ];
                                                        newItems[index] = {
                                                            ...newItems[index],
                                                            [k]: v,
                                                        };
                                                        onChange(
                                                            field.key,
                                                            newItems,
                                                        );
                                                    }}
                                                    onUploadingChange={
                                                        onUploadingChange
                                                            ? (k, isUp) =>
                                                                  onUploadingChange(
                                                                      `${field.key}.${index}.${k}`,
                                                                      isUp,
                                                                  )
                                                            : undefined
                                                    }
                                                />
                                            </div>
                                        );
                                    })}
                                </div>
                                {!field.hide_remove_button && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => {
                                            const newItems = items.filter(
                                                (_, i) => i !== index,
                                            );
                                            onChange(field.key, newItems);
                                        }}
                                    >
                                        <Trash2 className="text-destructive h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                        ))}
                        {!field.hide_add_button && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    const newItem: Record<string, any> = {};
                                    field.fields?.forEach((f: FieldConfig) => {
                                        newItem[f.key] = f.default ?? '';
                                    });
                                    onChange(field.key, [...items, newItem]);
                                }}
                            >
                                Add Item
                            </Button>
                        )}
                    </div>
                );
            }

            case 'number':
                return (
                    <Input
                        id={id}
                        type="number"
                        value={value ?? ''}
                        onChange={(e) => onChange(field.key, e.target.value)}
                        placeholder={field.placeholder}
                        disabled={field.disabled}
                        className="mt-1"
                    />
                );

            default: // text, email, password
                return (
                    <Input
                        id={id}
                        type={field.type}
                        value={value ?? ''}
                        onChange={(e) => onChange(field.key, e.target.value)}
                        placeholder={field.placeholder}
                        disabled={field.disabled}
                        className="mt-1"
                    />
                );
        }
    };

    return (
        <div>
            {field.type !== 'toggle' && field.type !== 'checkbox' && (
                <Label htmlFor={id}>
                    {field.label}
                    {field.required && (
                        <span className="text-destructive"> *</span>
                    )}
                </Label>
            )}
            {renderField()}
            {field.help && (
                <p className="text-muted-foreground mt-1 text-xs">
                    {field.help}
                </p>
            )}
            {error && <p className="text-destructive mt-1 text-sm">{error}</p>}
        </div>
    );
}

// ── Standard File & Image Upload Field (with Preview & Remove) ───────

function StandardFileField({
    id,
    field,
    value,
    isImage,
    onChange,
}: {
    id: string;
    field: FieldConfig;
    value: any;
    isImage: boolean;
    onChange: (value: any) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [removed, setRemoved] = useState(false);

    useEffect(() => {
        if (value instanceof File && isImage) {
            const url = URL.createObjectURL(value);
            setPreviewUrl(url);
            return () => URL.revokeObjectURL(url);
        }
        setPreviewUrl(null);
        return undefined;
    }, [value, isImage]);

    const existing = !removed ? field.existing_file : null;
    const selectedFile = value instanceof File ? value : null;
    const displayImageUrl = previewUrl || (isImage ? existing?.url : null);

    const handleSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setRemoved(false);
            onChange(file);
        }
    };

    const handleRemove = () => {
        setRemoved(true);
        if (inputRef.current) inputRef.current.value = '';
        onChange({ removed: true });
    };

    return (
        <div className="mt-1 space-y-2">
            {isImage && displayImageUrl && (
                <div className="bg-muted/20 flex items-center justify-between rounded-lg border p-3">
                    <div className="flex items-center gap-3">
                        <img
                            src={displayImageUrl}
                            alt={field.label}
                            className="h-16 w-16 rounded-md border object-cover"
                        />
                        <div className="text-xs">
                            <p className="font-medium">
                                {selectedFile?.name ||
                                    existing?.name ||
                                    'Current Image'}
                            </p>
                            {selectedFile && (
                                <p className="text-muted-foreground">
                                    {(selectedFile.size / 1024).toFixed(1)} KB
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs"
                            disabled={field.disabled}
                            onClick={() => inputRef.current?.click()}
                        >
                            Replace
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground hover:text-destructive h-8 w-8"
                            disabled={field.disabled}
                            onClick={handleRemove}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}

            {!isImage && (selectedFile || existing) && (
                <div className="bg-muted/20 flex items-center justify-between rounded-lg border p-3">
                    <div className="flex items-center gap-2.5 text-sm">
                        <FileCheck className="text-primary h-4 w-4 shrink-0" />
                        <span className="truncate font-medium">
                            {selectedFile?.name || existing?.name}
                        </span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        {!selectedFile && existing?.url && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                asChild
                                className="h-7 text-xs"
                            >
                                <a
                                    href={existing.url}
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
                            className="h-7 text-xs"
                            disabled={field.disabled}
                            onClick={() => inputRef.current?.click()}
                        >
                            Replace
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground hover:text-destructive h-7 w-7"
                            disabled={field.disabled}
                            onClick={handleRemove}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}

            <input
                ref={inputRef}
                id={id}
                type="file"
                accept={field.accept ?? (isImage ? 'image/*' : undefined)}
                onChange={handleSelect}
                disabled={field.disabled}
                className={
                    (isImage && displayImageUrl) ||
                    (!isImage && (selectedFile || existing))
                        ? 'hidden'
                        : 'border-input file:text-foreground placeholder:text-muted-foreground flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:cursor-not-allowed disabled:opacity-50'
                }
            />

            {!displayImageUrl && !selectedFile && !existing && isImage && (
                <button
                    type="button"
                    disabled={field.disabled}
                    onClick={() => inputRef.current?.click()}
                    className="border-border/80 hover:border-primary/50 hover:bg-muted/30 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed py-4 text-xs font-medium transition-colors"
                >
                    <UploadCloud className="text-muted-foreground h-4 w-4" />
                    <span>
                        {field.placeholder || 'Click to choose an image'}
                    </span>
                </button>
            )}
        </div>
    );
}

// ── Slug Auto-Sync Field ─────────────────────────────────────────────

function SlugField({
    id,
    field,
    value,
    sourceValue,
    onChange,
}: {
    id: string;
    field: FieldConfig;
    value: string;
    sourceValue?: string;
    onChange: (val: string) => void;
}) {
    const [manuallyEdited, setManuallyEdited] = useState(Boolean(value));

    useEffect(() => {
        if (!manuallyEdited && sourceValue !== undefined) {
            const next = slugifyText(sourceValue);
            if (next !== value) {
                onChange(next);
            }
        }
    }, [sourceValue, manuallyEdited]);

    return (
        <div className="mt-1 flex items-center gap-2">
            <Input
                id={id}
                type="text"
                value={value}
                onChange={(e) => {
                    setManuallyEdited(true);
                    onChange(e.target.value);
                }}
                placeholder={field.placeholder ?? 'auto-generated-slug'}
                disabled={field.disabled}
                className="font-mono text-sm"
            />
            {sourceValue !== undefined && (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    disabled={field.disabled}
                    title="Regenerate slug"
                    onClick={() => {
                        setManuallyEdited(false);
                        onChange(slugifyText(sourceValue));
                    }}
                >
                    <RefreshCw className="h-4 w-4" />
                </Button>
            )}
        </div>
    );
}

// ── Tags Chip Input ──────────────────────────────────────────────────

function TagsField({
    id,
    value,
    placeholder,
    disabled,
    onChange,
}: {
    id: string;
    value: any;
    placeholder?: string;
    disabled?: boolean;
    onChange: (tags: string[]) => void;
}) {
    const [inputVal, setInputVal] = useState('');
    const tags: string[] = Array.isArray(value)
        ? value.map(String)
        : typeof value === 'string' && value.trim() !== ''
          ? value
                .split(',')
                .map((s) => s.trim())
                .filter(Boolean)
          : [];

    const addTag = (raw: string) => {
        const clean = raw.trim();
        if (!clean || tags.includes(clean)) {
            setInputVal('');
            return;
        }
        onChange([...tags, clean]);
        setInputVal('');
    };

    return (
        <div className="border-input bg-background mt-1 rounded-md border p-2">
            <div className="flex flex-wrap items-center gap-1.5">
                {tags.map((tag) => (
                    <Badge key={tag} variant="secondary" className="pr-1">
                        <span>{tag}</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            disabled={disabled}
                            className="ml-1 h-4 w-4 rounded-full p-0"
                            onClick={() =>
                                onChange(tags.filter((t) => t !== tag))
                            }
                        >
                            <X className="h-3 w-3" />
                        </Button>
                    </Badge>
                ))}
                <input
                    id={id}
                    type="text"
                    value={inputVal}
                    disabled={disabled}
                    placeholder={
                        tags.length === 0
                            ? (placeholder ?? 'Type and press Enter...')
                            : ''
                    }
                    onChange={(e) => setInputVal(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ',') {
                            e.preventDefault();
                            addTag(inputVal);
                        } else if (
                            e.key === 'Backspace' &&
                            inputVal === '' &&
                            tags.length > 0
                        ) {
                            onChange(tags.slice(0, -1));
                        }
                    }}
                    onBlur={() => {
                        if (inputVal.trim() !== '') addTag(inputVal);
                    }}
                    className="placeholder:text-muted-foreground min-w-[120px] flex-1 bg-transparent px-1 py-0.5 text-sm outline-none"
                />
            </div>
        </div>
    );
}

// ── Key-Value JSON Editor ────────────────────────────────────────────

function KeyValueField({
    value,
    disabled,
    onChange,
}: {
    value: any;
    disabled?: boolean;
    onChange: (val: Record<string, string>) => void;
}) {
    const obj: Record<string, string> =
        value && typeof value === 'object' && !Array.isArray(value)
            ? value
            : {};
    const [pairs, setPairs] = useState<{ k: string; v: string }[]>(() =>
        Object.entries(obj).map(([k, v]) => ({ k, v: String(v ?? '') })),
    );

    const emit = (next: { k: string; v: string }[]) => {
        setPairs(next);
        const record: Record<string, string> = {};
        for (const row of next) {
            if (row.k.trim() !== '') {
                record[row.k.trim()] = row.v;
            }
        }
        onChange(record);
    };

    return (
        <div className="bg-muted/10 mt-1 space-y-2 rounded-md border p-3">
            {pairs.map((row, idx) => (
                <div key={idx} className="flex items-center gap-2">
                    <Input
                        value={row.k}
                        placeholder="Key"
                        disabled={disabled}
                        onChange={(e) => {
                            const next = [...pairs];
                            next[idx] = { ...next[idx], k: e.target.value };
                            emit(next);
                        }}
                    />
                    <Input
                        value={row.v}
                        placeholder="Value"
                        disabled={disabled}
                        onChange={(e) => {
                            const next = [...pairs];
                            next[idx] = { ...next[idx], v: e.target.value };
                            emit(next);
                        }}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={disabled}
                        onClick={() => emit(pairs.filter((_, i) => i !== idx))}
                    >
                        <Trash2 className="text-destructive h-4 w-4" />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={disabled}
                onClick={() => emit([...pairs, { k: '', v: '' }])}
            >
                <Plus className="mr-1.5 h-3.5 w-3.5" />
                Add Pair
            </Button>
        </div>
    );
}

// ── Zero-Dependency Markdown Rich-Text Field with Live Preview ───────

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderMarkdownSafe(md: string): string {
    const escaped = escapeHtml(md);
    return escaped
        .replace(/^### (.+)$/gm, '<h3 class="text-base font-semibold mt-3 mb-1">$1</h3>')
        .replace(/^## (.+)$/gm, '<h2 class="text-lg font-semibold mt-4 mb-1.5">$1</h2>')
        .replace(/^# (.+)$/gm, '<h1 class="text-xl font-bold mt-4 mb-2">$1</h1>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code class="rounded bg-muted px-1 py-0.5 font-mono text-xs">$1</code>')
        .replace(
            /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,
            '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-primary underline">$1</a>',
        )
        .replace(/^- (.+)$/gm, '<li class="ml-4 list-disc">$1</li>')
        .replace(/^\d+\. (.+)$/gm, '<li class="ml-4 list-decimal">$1</li>')
        .replace(/\n/g, '<br />');
}

function RichTextMarkdownField({
    id,
    value,
    placeholder,
    disabled,
    onChange,
}: {
    id: string;
    value: string;
    placeholder?: string;
    disabled?: boolean;
    onChange: (value: string) => void;
}) {
    const [preview, setPreview] = useState(false);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const wrapSelection = (before: string, after = '', fallback = 'text') => {
        const el = textareaRef.current;
        if (!el) {
            onChange(`${value}${before}${fallback}${after}`);
            return;
        }
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const selected = value.slice(start, end) || fallback;
        const next =
            value.slice(0, start) + before + selected + after + value.slice(end);
        onChange(next);
        setTimeout(() => {
            el.focus();
            el.setSelectionRange(
                start + before.length,
                start + before.length + selected.length,
            );
        }, 0);
    };

    return (
        <div className="border-input mt-1 overflow-hidden rounded-md border">
            <div className="bg-muted/30 flex flex-wrap items-center justify-between gap-1 border-b px-2 py-1.5">
                <div className="flex flex-wrap items-center gap-0.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        disabled={disabled || preview}
                        title="Bold"
                        onClick={() => wrapSelection('**', '**', 'bold text')}
                    >
                        <Bold className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        disabled={disabled || preview}
                        title="Italic"
                        onClick={() => wrapSelection('*', '*', 'italic text')}
                    >
                        <Italic className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        disabled={disabled || preview}
                        title="Heading"
                        onClick={() => wrapSelection('\n## ', '', 'Heading')}
                    >
                        <Heading2 className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        disabled={disabled || preview}
                        title="Bullet List"
                        onClick={() => wrapSelection('\n- ', '', 'List item')}
                    >
                        <List className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        disabled={disabled || preview}
                        title="Numbered List"
                        onClick={() => wrapSelection('\n1. ', '', 'List item')}
                    >
                        <ListOrdered className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        disabled={disabled || preview}
                        title="Link"
                        onClick={() =>
                            wrapSelection('[', '](https://example.com)', 'link')
                        }
                    >
                        <LinkIcon className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        disabled={disabled || preview}
                        title="Inline Code"
                        onClick={() => wrapSelection('`', '`', 'code')}
                    >
                        <Code className="h-3.5 w-3.5" />
                    </Button>
                </div>

                <Button
                    type="button"
                    variant={preview ? 'secondary' : 'ghost'}
                    size="sm"
                    className="h-7 gap-1 px-2 text-xs"
                    onClick={() => setPreview((p) => !p)}
                >
                    <Eye className="h-3.5 w-3.5" />
                    {preview ? 'Write' : 'Preview'}
                </Button>
            </div>

            {preview ? (
                <div
                    className="min-h-[140px] p-3 text-sm leading-relaxed"
                    dangerouslySetInnerHTML={{
                        __html: value.trim()
                            ? renderMarkdownSafe(value)
                            : '<span class="text-muted-foreground italic">Nothing to preview.</span>',
                    }}
                />
            ) : (
                <Textarea
                    ref={textareaRef}
                    id={id}
                    value={value}
                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                        onChange(e.target.value)
                    }
                    placeholder={
                        placeholder ?? 'Write content using Markdown...'
                    }
                    disabled={disabled}
                    rows={6}
                    className="rounded-none border-0 shadow-none focus-visible:ring-0"
                />
            )}
        </div>
    );
}

// ── Searchable & Async Combobox Select ───────────────────────────────

function ComboboxField({
    field,
    value,
    onChange,
    id,
}: {
    field: FieldConfig;
    value: any;
    onChange: (key: string, value: any) => void;
    id: string;
}) {
    const [open, setOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [asyncOptions, setAsyncOptions] = useState<
        { label: string; value: string }[]
    >(field.options || []);
    const [loading, setLoading] = useState(false);

    const isAsync = Boolean(field.options_endpoint && field.options_config);
    const isMultiple = Boolean(field.multiple);

    // Keep local options in sync when static options change
    useEffect(() => {
        if (!isAsync) {
            setAsyncOptions(field.options || []);
        }
    }, [field.options, isAsync]);

    // Fetch async options with debounce when popover is open
    useEffect(() => {
        if (!isAsync || !open) return;

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setLoading(true);
            try {
                const url = new URL(
                    field.options_endpoint!,
                    window.location.origin,
                );
                url.searchParams.set('config', field.options_config!);
                if (searchQuery.trim() !== '') {
                    url.searchParams.set('search', searchQuery.trim());
                }
                const selectedParam = Array.isArray(value)
                    ? value.join(',')
                    : value
                      ? String(value)
                      : '';
                if (selectedParam) {
                    url.searchParams.set('selected', selectedParam);
                }

                const res = await fetch(url.toString(), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (res.ok) {
                    const json = await res.json();
                    if (Array.isArray(json.options)) {
                        setAsyncOptions(json.options);
                    }
                }
            } catch {
                // Ignore abort / network errors
            } finally {
                setLoading(false);
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [
        isAsync,
        open,
        searchQuery,
        field.options_endpoint,
        field.options_config,
    ]);

    const options = isAsync ? asyncOptions : field.options || [];

    if (isMultiple) {
        const selectedValues = Array.isArray(value) ? value.map(String) : [];

        return (
            <div className="mt-1 flex flex-col gap-2">
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            id={id}
                            type="button"
                            variant="outline"
                            role="combobox"
                            aria-expanded={open}
                            className="w-full justify-between font-normal"
                            disabled={field.disabled}
                        >
                            <span className="truncate">
                                {selectedValues.length > 0
                                    ? `${selectedValues.length} selected`
                                    : (field.placeholder ??
                                      `Select ${field.label}`)}
                            </span>
                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent
                        className="w-[--radix-popover-trigger-width] p-0"
                        align="start"
                    >
                        <Command shouldFilter={!isAsync}>
                            <CommandInput
                                placeholder={`Search ${field.label}...`}
                                value={isAsync ? searchQuery : undefined}
                                onValueChange={
                                    isAsync ? setSearchQuery : undefined
                                }
                            />
                            <CommandList>
                                {loading ? (
                                    <div className="text-muted-foreground flex items-center justify-center gap-2 py-4 text-xs">
                                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                        Loading...
                                    </div>
                                ) : (
                                    <>
                                        <CommandEmpty>
                                            No results found.
                                        </CommandEmpty>
                                        <CommandGroup>
                                            {options.map((opt) => {
                                                const optVal = String(opt.value);
                                                const isSelected =
                                                    selectedValues.includes(
                                                        optVal,
                                                    );
                                                return (
                                                    <CommandItem
                                                        key={optVal}
                                                        value={opt.label}
                                                        onSelect={() => {
                                                            if (isSelected) {
                                                                onChange(
                                                                    field.key,
                                                                    selectedValues.filter(
                                                                        (v) =>
                                                                            v !==
                                                                            optVal,
                                                                    ),
                                                                );
                                                            } else {
                                                                onChange(
                                                                    field.key,
                                                                    [
                                                                        ...selectedValues,
                                                                        optVal,
                                                                    ],
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <Check
                                                            className={cn(
                                                                'mr-2 h-4 w-4',
                                                                isSelected
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0',
                                                            )}
                                                        />
                                                        {opt.label}
                                                    </CommandItem>
                                                );
                                            })}
                                        </CommandGroup>
                                    </>
                                )}
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>

                {selectedValues.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {selectedValues.map((val) => {
                            const opt = options.find(
                                (o) => String(o.value) === val,
                            );
                            return (
                                <Badge
                                    key={val}
                                    variant="secondary"
                                    className="pr-1"
                                >
                                    <span>{opt?.label ?? val}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="ml-1 h-4 w-4 rounded-full p-0"
                                        onClick={() => {
                                            onChange(
                                                field.key,
                                                selectedValues.filter(
                                                    (v) => v !== val,
                                                ),
                                            );
                                        }}
                                        disabled={field.disabled}
                                    >
                                        <X className="h-3 w-3" />
                                    </Button>
                                </Badge>
                            );
                        })}
                    </div>
                )}
            </div>
        );
    }

    // Single Select
    const selectValue =
        value === null || value === undefined || value === ''
            ? ''
            : String(value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="mt-1 w-full justify-between font-normal"
                    disabled={field.disabled}
                >
                    <span className="truncate">
                        {selectValue
                            ? (options.find(
                                  (opt) => String(opt.value) === selectValue,
                              )?.label ?? selectValue)
                            : (field.placeholder ?? `Select ${field.label}`)}
                    </span>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[--radix-popover-trigger-width] p-0"
                align="start"
            >
                <Command shouldFilter={!isAsync}>
                    <CommandInput
                        placeholder={`Search ${field.label}...`}
                        value={isAsync ? searchQuery : undefined}
                        onValueChange={isAsync ? setSearchQuery : undefined}
                    />
                    <CommandList>
                        {loading ? (
                            <div className="text-muted-foreground flex items-center justify-center gap-2 py-4 text-xs">
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                Loading...
                            </div>
                        ) : (
                            <>
                                <CommandEmpty>No results found.</CommandEmpty>
                                <CommandGroup>
                                    <CommandItem
                                        value="__empty__"
                                        onSelect={() => {
                                            onChange(field.key, '');
                                            setOpen(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                'mr-2 h-4 w-4',
                                                selectValue === ''
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        <span className="text-muted-foreground italic">
                                            None
                                        </span>
                                    </CommandItem>
                                    {options.map((opt) => {
                                        const optVal = String(opt.value);
                                        return (
                                            <CommandItem
                                                key={optVal}
                                                value={opt.label}
                                                onSelect={() => {
                                                    onChange(field.key, optVal);
                                                    setOpen(false);
                                                }}
                                            >
                                                <Check
                                                    className={cn(
                                                        'mr-2 h-4 w-4',
                                                        selectValue === optVal
                                                            ? 'opacity-100'
                                                            : 'opacity-0',
                                                    )}
                                                />
                                                {opt.label}
                                            </CommandItem>
                                        );
                                    })}
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
