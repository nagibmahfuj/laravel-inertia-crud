import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Search, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataTableFacetedFilter } from './DataTableFacetedFilter';
import { DatePicker } from './DatePicker';
import { DatePickerWithRange } from './DatePickerWithRange';
import type { FilterConfig } from './types';

export type { FilterConfig };

interface FilterBarProps {
    filters: FilterConfig[];
    values: Record<string, string>;
    onChange: (key: string, value: string) => void;
    onChangeMany?: (updates: Record<string, string>) => void;
    onReset: () => void;
    search: string;
    onSearch: (value: string) => void;
    defaultSeparator?: string;
    softDeletes?: boolean;
}

const DEFAULT_SEPARATOR = '--';

export function FilterBar({
    filters,
    values,
    onChange,
    onChangeMany,
    onReset,
    search,
    onSearch,
    defaultSeparator = DEFAULT_SEPARATOR,
    softDeletes = false,
}: FilterBarProps) {
    const [localSearch, setLocalSearch] = useState(search);

    useEffect(() => {
        setLocalSearch(search);
    }, [search]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (localSearch !== search) {
                onSearch(localSearch);
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [localSearch, search, onSearch]);

    // Only count active search, trashed, and filter_* params (not sort/direction/per_page)
    const hasActiveFilters =
        search.length > 0 ||
        Boolean(values.trashed) ||
        Object.entries(values).some(
            ([k, v]) => k.startsWith('filter_') && Boolean(v && v.length > 0),
        );

    const handleFilterToggle = (key: string, value: string) => {
        const filter = filters.find((f) => `filter_${f.key}` === key);
        const separator = filter?.separator ?? defaultSeparator;
        const currentVal = values[key] ?? '';
        const currentArray = currentVal ? currentVal.split(separator) : [];

        const nextArray = currentArray.includes(value)
            ? currentArray.filter((v) => v !== value)
            : [...currentArray, value];

        onChange(key, nextArray.join(separator));
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className="relative w-full lg:max-w-sm lg:flex-1">
                <Search className="text-muted-foreground absolute top-2 left-2 h-4 w-4" />
                <Input
                    placeholder="Search..."
                    value={localSearch}
                    onChange={(e) => setLocalSearch(e.target.value)}
                    className="h-8 pl-8 text-sm"
                />
            </div>

            {softDeletes && (
                <Select
                    value={values.trashed ?? 'active'}
                    onValueChange={(v) =>
                        onChange('trashed', v === 'active' ? '' : v)
                    }
                >
                    <SelectTrigger className="h-8 w-[145px] truncate whitespace-nowrap">
                        <Trash2 className="text-muted-foreground mr-1.5 h-3.5 w-3.5 shrink-0" />
                        <SelectValue placeholder="Records" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="active">Active Only</SelectItem>
                        <SelectItem value="with">With Trashed</SelectItem>
                        <SelectItem value="only">Only Trashed</SelectItem>
                    </SelectContent>
                </Select>
            )}

            {filters.map((filter) => {
                const filterKey = `filter_${filter.key}`;
                const currentValue = values[filterKey] ?? '';

                if (filter.type === 'text') {
                    return (
                        <TextFilterInput
                            key={filter.key}
                            placeholder={filter.placeholder ?? filter.label}
                            value={currentValue}
                            onChange={(v) => onChange(filterKey, v)}
                        />
                    );
                }

                if (filter.type === 'boolean') {
                    return (
                        <Select
                            key={filter.key}
                            value={currentValue}
                            onValueChange={(v) => onChange(filterKey, v)}
                        >
                            <SelectTrigger className="h-8 w-[130px] truncate whitespace-nowrap">
                                <SelectValue placeholder={filter.label} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="true">Yes</SelectItem>
                                <SelectItem value="false">No</SelectItem>
                            </SelectContent>
                        </Select>
                    );
                }

                if (filter.type === 'select' && filter.options) {
                    if (filter.multiple) {
                        const separator = filter.separator ?? defaultSeparator;
                        const selectedValues = new Set(
                            currentValue ? currentValue.split(separator) : [],
                        );
                        return (
                            <DataTableFacetedFilter
                                key={filter.key}
                                title={filter.label}
                                options={filter.options}
                                selectedValues={selectedValues}
                                onSelect={(v) =>
                                    handleFilterToggle(filterKey, v)
                                }
                                onClear={() => onChange(filterKey, '')}
                            />
                        );
                    }

                    return (
                        <Select
                            key={filter.key}
                            value={currentValue}
                            onValueChange={(v) => onChange(filterKey, v)}
                        >
                            <SelectTrigger className="h-8 w-[150px] truncate whitespace-nowrap">
                                <SelectValue placeholder={filter.label} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All {filter.label}
                                </SelectItem>
                                {filter.options.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value.toString()}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    );
                }

                if (filter.type === 'date_range') {
                    const from = values[`filter_${filter.key}_from`] ?? '';
                    const to = values[`filter_${filter.key}_to`] ?? '';

                    return (
                        <DatePickerWithRange
                            key={filter.key}
                            placeholder={filter.label}
                            value={from && to ? { from, to } : undefined}
                            onChange={(range) => {
                                if (onChangeMany) {
                                    if (range) {
                                        onChangeMany({
                                            [`filter_${filter.key}_from`]:
                                                range.from,
                                            [`filter_${filter.key}_to`]:
                                                range.to,
                                        });
                                    } else {
                                        onChangeMany({
                                            [`filter_${filter.key}_from`]: '',
                                            [`filter_${filter.key}_to`]: '',
                                        });
                                    }
                                } else {
                                    if (range) {
                                        onChange(
                                            `filter_${filter.key}_from`,
                                            range.from,
                                        );
                                        onChange(
                                            `filter_${filter.key}_to`,
                                            range.to,
                                        );
                                    } else {
                                        onChange(
                                            `filter_${filter.key}_from`,
                                            '',
                                        );
                                        onChange(`filter_${filter.key}_to`, '');
                                    }
                                }
                            }}
                        />
                    );
                }

                if (filter.type === 'date') {
                    const value = values[`filter_${filter.key}`] ?? '';
                    return (
                        <DatePicker
                            key={filter.key}
                            placeholder={filter.label}
                            value={value}
                            onChange={(date) =>
                                onChange(`filter_${filter.key}`, date || '')
                            }
                        />
                    );
                }

                return null;
            })}

            {hasActiveFilters && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onReset}
                    className="h-8 px-2 lg:px-3"
                >
                    Reset
                    <X className="ml-2 h-4 w-4" />
                </Button>
            )}
        </div>
    );
}

function TextFilterInput({
    placeholder,
    value,
    onChange,
}: {
    placeholder: string;
    value: string;
    onChange: (value: string) => void;
}) {
    const [local, setLocal] = useState(value);

    useEffect(() => {
        setLocal(value);
    }, [value]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (local !== value) {
                onChange(local);
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [local, value, onChange]);

    return (
        <Input
            placeholder={placeholder}
            value={local}
            onChange={(e) => setLocal(e.target.value)}
            className="h-8 w-[150px] text-sm"
        />
    );
}
