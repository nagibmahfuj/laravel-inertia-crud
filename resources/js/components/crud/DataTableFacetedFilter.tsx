import * as React from 'react';
import { Check, PlusCircle, Search, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';

interface DataTableFacetedFilterProps {
    title?: string;
    options: {
        label: string;
        value: string;
        icon?: React.ComponentType<{ className?: string }>;
    }[];
    selectedValues: Set<string>;
    onSelect: (value: string) => void;
    onClear: () => void;
}

export function DataTableFacetedFilter({
    title,
    options,
    selectedValues,
    onSelect,
    onClear,
}: DataTableFacetedFilterProps) {
    const [search, setSearch] = React.useState('');
    const [open, setOpen] = React.useState(false);
    const inputRef = React.useRef<HTMLInputElement>(null);
    const itemsContainerRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        if (open) {
            const timer = setTimeout(() => {
                inputRef.current?.focus();
            }, 0);
            return () => clearTimeout(timer);
        } else {
            setSearch('');
        }
    }, [open]);

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const firstItem = itemsContainerRef.current?.querySelector(
                '[role="menuitem"]',
            ) as HTMLElement;
            firstItem?.focus();
        }
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Enter') {
            e.stopPropagation();
        }
    };

    const filteredOptions = options.filter((option) =>
        option.label.toLowerCase().includes(search.toLowerCase()),
    );

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="h-8 border">
                    <PlusCircle className="mr-2 size-4 opacity-50" />
                    <span className="text-muted-foreground">{title}</span>
                    {selectedValues?.size > 0 && (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-2 h-4"
                            />
                            <Badge
                                variant="secondary"
                                className="rounded-sm px-1 font-normal lg:hidden"
                            >
                                {selectedValues.size}
                            </Badge>
                            <div className="hidden space-x-1 lg:flex">
                                {selectedValues.size > 2 ? (
                                    <Badge
                                        variant="secondary"
                                        className="rounded-sm px-1 font-normal"
                                    >
                                        {selectedValues.size} selected
                                    </Badge>
                                ) : (
                                    options
                                        .filter((option) =>
                                            selectedValues.has(option.value),
                                        )
                                        .map((option) => (
                                            <Badge
                                                variant="secondary"
                                                key={option.value}
                                                className="rounded-sm px-1 font-normal"
                                            >
                                                {option.label}
                                            </Badge>
                                        ))
                                )}
                            </div>
                        </>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-[200px]"
                onKeyDown={(e) => {
                    if (e.key === 'ArrowUp') {
                        const items =
                            itemsContainerRef.current?.querySelectorAll(
                                '[role="menuitem"]',
                            );
                        if (items && items[0] === document.activeElement) {
                            e.preventDefault();
                            inputRef.current?.focus();
                        }
                    }
                }}
            >
                <div className="flex items-center px-2 py-0">
                    <Search className="mr-2 h-4 w-4 shrink-0 opacity-50" />
                    <Input
                        ref={inputRef}
                        placeholder={title}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={handleKeyDown}
                        className="h-8 w-full border-none p-0 shadow-none focus-visible:ring-0"
                    />
                </div>
                <DropdownMenuSeparator />
                <div
                    ref={itemsContainerRef}
                    className="max-h-[300px] overflow-y-auto"
                >
                    {filteredOptions.length === 0 ? (
                        <div className="py-6 text-center text-sm">
                            No results found.
                        </div>
                    ) : (
                        filteredOptions.map((option) => {
                            const isSelected = selectedValues.has(
                                option.value.toString(),
                            );
                            return (
                                <DropdownMenuItem
                                    key={option.value}
                                    onSelect={(e) => {
                                        e.preventDefault();
                                        onSelect(option.value.toString());
                                    }}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox checked={isSelected} />
                                    {option.icon && (
                                        <option.icon className="h-4 w-4 text-muted-foreground" />
                                    )}
                                    <span>{option.label}</span>
                                </DropdownMenuItem>
                            );
                        })
                    )}
                </div>
                {selectedValues.size > 0 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={(e) => {
                                e.preventDefault();
                                onClear();
                            }}
                            className="justify-center text-center font-medium"
                        >
                            Clear filters
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
