import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Search, X } from 'lucide-react';
import { DataTableFacetedFilter } from './DataTableFacetedFilter';
import { DatePicker } from './DatePicker';
import { DatePickerWithRange } from './DatePickerWithRange';

export interface FilterConfig {
    key: string;
    type: 'select' | 'boolean' | 'date_range' | 'date';
    label: string;
    options?: { label: string; value: string }[];
    column?: string;
    multiple?: boolean;
    separator?: string;
}

interface FilterBarProps {
    filters: FilterConfig[];
    values: Record<string, string>;
    onChange: (key: string, value: string) => void;
    onReset: () => void;
    // Search support
    search: string;
    onSearch: (value: string) => void;
}

const DEFAULT_SEPARATOR = '--';

export function FilterBar({
    filters,
    values,
    onChange,
    onReset,
    search,
    onSearch,
}: FilterBarProps) {
    const hasActiveFilters =
        Object.values(values).some((v) => v && v.length > 0) ||
        search.length > 0;

    const handleFilterToggle = (key: string, value: string) => {
        const filter = filters.find((f) => `filter_${f.key}` === key);
        const separator = filter?.separator ?? DEFAULT_SEPARATOR;
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
                <Search className="absolute top-2 left-2 h-4 w-4 text-muted-foreground" />
                <Input
                    placeholder="Search..."
                    value={search}
                    onChange={(e) => onSearch(e.target.value)}
                    className="h-8 pl-8 text-sm"
                />
            </div>

            {filters.map((filter) => {
                const filterKey = `filter_${filter.key}`;
                const currentValue = values[filterKey] ?? '';

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
                        const separator = filter.separator ?? DEFAULT_SEPARATOR;
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
                                    onChange(`filter_${filter.key}_from`, '');
                                    onChange(`filter_${filter.key}_to`, '');
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
