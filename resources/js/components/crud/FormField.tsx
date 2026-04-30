import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Trash2, Check, ChevronsUpDown, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

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
                        onChange={(e) => onChange(field.key, e.target.value)}
                        placeholder={field.placeholder}
                        disabled={field.disabled}
                        className="mt-1"
                        rows={4}
                    />
                );

            case 'select': {
                return (
                    <ComboboxField
                        field={field}
                        value={value}
                        onChange={onChange}
                        id={id}
                    />
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
    const isMultiple = !!(field as any).multiple;

    const options = field.options || [];

    if (isMultiple) {
        const selectedValues = Array.isArray(value) ? value.map(String) : [];

        return (
            <div className="mt-1 flex flex-col gap-2">
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            id={id}
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
                        <Command>
                            <CommandInput
                                placeholder={`Search ${field.label}...`}
                            />
                            <CommandList>
                                <CommandEmpty>No results found.</CommandEmpty>
                                <CommandGroup>
                                    {options.map((opt) => {
                                        const optVal = String(opt.value);
                                        const isSelected =
                                            selectedValues.includes(optVal);
                                        return (
                                            <CommandItem
                                                key={optVal}
                                                value={optVal}
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
                                                        onChange(field.key, [
                                                            ...selectedValues,
                                                            optVal,
                                                        ]);
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
                                    <span className="max-w-[150px] truncate">
                                        {opt?.label ?? val}
                                    </span>
                                    <Button
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
                <Command>
                    <CommandInput placeholder={`Search ${field.label}...`} />
                    <CommandList>
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
                                        value={optVal}
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
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
