import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Trash2 } from 'lucide-react';

export interface FieldConfig {
    key: string;
    label: string;
    type:
        | 'text'
        | 'email'
        | 'password'
        | 'number'
        | 'textarea'
        | 'select'
        | 'datetime'
        | 'date'
        | 'toggle'
        | 'file'
        | 'options-builder'
        | 'hidden'
        | 'repeater';

    placeholder?: string;
    options?: { label: string; value: string }[];
    required?: boolean;
    disabled?: boolean;
    rules?: string;
    default?: any;
}

interface FormFieldProps {
    field: FieldConfig;
    value: any;
    error?: string;
    errors?: Record<string, string>;
    allValues?: any;
    onChange: (key: string, value: any) => void;
}

export function FormField({
    field,
    value,
    error,
    errors,
    allValues,
    onChange,
}: FormFieldProps) {
    if (field.type === 'hidden') return null;

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
                                        onChange(
                                            field.key,
                                            newOpts,
                                        );
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
                                        onChange(
                                            field.key,
                                            newOpts,
                                        );
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
                                onChange(
                                    field.key,
                                    [
                                        ...opts,
                                        `Option ${opts.length + 1}`,
                                    ],
                                );
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
                        onChange={(e) => onChange(field.key, e.target.value)}
                        placeholder={field.placeholder}
                        disabled={field.disabled}
                        className="mt-1"
                        rows={4}
                    />
                );

            case 'select': {
                let selectValue =
                    value === null || value === undefined || value === ''
                        ? 'none'
                        : String(value);
                if (
                    value === '' &&
                    field.options?.some((o) => String(o.value) === '')
                ) {
                    selectValue = '__empty__';
                }

                return (
                    <Select
                        value={selectValue}
                        onValueChange={(v) => {
                            if (v === 'none' || v === '__empty__') {
                                onChange(field.key, '');
                            } else {
                                onChange(field.key, v);
                            }
                        }}
                        disabled={field.disabled}
                    >
                        <SelectTrigger className="mt-1" id={id}>
                            <SelectValue
                                placeholder={
                                    field.placeholder ?? `Select ${field.label}`
                                }
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                value="none"
                                className="hidden"
                                disabled
                            >
                                {field.placeholder ?? `Select ${field.label}`}
                            </SelectItem>
                            {field.options?.map((opt) => {
                                const optVal =
                                    String(opt.value) === ''
                                        ? '__empty__'
                                        : String(opt.value);
                                return (
                                    <SelectItem key={optVal} value={optVal}>
                                        {opt.label}
                                    </SelectItem>
                                );
                            })}
                        </SelectContent>
                    </Select>
                );
            }

            case 'toggle':
                return (
                    <div className="mt-1 flex items-center space-x-2">
                        <Checkbox
                            id={id}
                            checked={!!value}
                            onCheckedChange={(checked) =>
                                onChange(field.key, checked)
                            }
                            disabled={field.disabled}
                        />
                        <Label htmlFor={id} className="text-sm font-normal">
                            {field.placeholder ?? 'Enable'}
                        </Label>
                    </div>
                );

            case 'file':
                return (
                    <Input
                        id={id}
                        type="file"
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) onChange(field.key, file);
                        }}
                        disabled={field.disabled}
                        className="mt-1"
                    />
                );

            case 'datetime': {
                return (
                    <Input
                        id={id}
                        type="datetime-local"
                        value={value}
                        onChange={(e) => onChange(field.key, e.target.value)}
                        disabled={field.disabled}
                        className="mt-1"
                    />
                );
            }

            case 'date': {
                let formattedValue = value ?? '';
                if (typeof formattedValue === 'string') {
                    formattedValue = formattedValue.split(' ')[0];
                }
                if (formattedValue && !isNaN(Date.parse(formattedValue))) {
                    const d = new Date(formattedValue);
                    const pad = (n: number) => n.toString().padStart(2, '0');
                    formattedValue = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                }
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
                // field.options would contain schema for repeater items if needed,
                // but since it's generic, we assume parent passes field.fields etc?
                // Actually, let's look at how we'll use it in StockTransferController.
                return (
                    <div className="bg-muted/10 mt-2 space-y-4 rounded-md border p-4">
                        {items.map((item: any, index: number) => (
                            <div
                                key={index}
                                className="flex items-end gap-3 border-b pb-4 last:border-0 last:pb-0"
                            >
                                <div className="grid flex-1 grid-cols-2 gap-3">
                                    {(field as any).fields?.map(
                                        (f: FieldConfig) => {
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
                                                        onChange={(k, v) => {
                                                            const newItems = [
                                                                ...items,
                                                            ];
                                                            newItems[index] = {
                                                                ...newItems[
                                                                    index
                                                                ],
                                                                [k]: v,
                                                            };
                                                            onChange(
                                                                field.key,
                                                                newItems,
                                                            );
                                                        }}
                                                    />
                                                </div>
                                            );
                                        },
                                    )}
                                </div>
                                {!(field as any).hide_remove_button && (
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
                        {!(field as any).hide_add_button && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    const newItem = {};
                                    (field as any).fields?.forEach(
                                        (f: FieldConfig) => {
                                            (newItem as any)[f.key] = '';
                                        },
                                    );
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
            {field.type !== 'toggle' && (
                <Label htmlFor={id}>
                    {field.label}
                    {field.required && (
                        <span className="text-destructive"> *</span>
                    )}
                </Label>
            )}
            {renderField()}
            {error && <p className="text-destructive mt-1 text-sm">{error}</p>}
        </div>
    );
}
