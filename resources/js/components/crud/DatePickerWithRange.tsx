import * as React from 'react';
import { format } from 'date-fns';
import { Calendar as CalendarIcon } from 'lucide-react';
import { DateRange } from 'react-day-picker';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

interface DatePickerWithRangeProps {
    className?: string;
    value?: { from: string; to: string };
    onChange: (range: { from: string; to: string } | null) => void;
    placeholder?: string;
}

export function DatePickerWithRange({
    className,
    value,
    onChange,
    placeholder = 'Pick a date',
}: DatePickerWithRangeProps) {
    const [date, setDate] = React.useState<DateRange | undefined>(() => {
        if (value?.from && value?.to) {
            const fromDate = new Date(value.from);
            const toDate = new Date(value.to);
            // Check for valid dates
            if (!isNaN(fromDate.getTime()) && !isNaN(toDate.getTime())) {
                return { from: fromDate, to: toDate };
            }
        }
        return undefined;
    });

    // Sync internal state with external value
    React.useEffect(() => {
        if (value?.from && value?.to) {
            const fromDate = new Date(value.from);
            const toDate = new Date(value.to);
            if (!isNaN(fromDate.getTime()) && !isNaN(toDate.getTime())) {
                setDate({ from: fromDate, to: toDate });
            }
        } else {
            setDate(undefined);
        }
    }, [value?.from, value?.to]);

    const handleSelect = (newRange: DateRange | undefined) => {
        setDate(newRange);
        if (newRange?.from && newRange?.to) {
            onChange({
                from: format(newRange.from, 'yyyy-MM-dd'),
                to: format(newRange.to, 'yyyy-MM-dd'),
            });
        } else if (!newRange?.from) {
            onChange(null);
        }
    };

    return (
        <div className={cn('grid gap-2', className)}>
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        id="date"
                        variant={'outline'}
                        size="sm"
                        className={cn(
                            'h-8 w-fit justify-start text-left font-normal',
                            !date && 'text-muted-foreground',
                        )}
                    >
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        {date?.from ? (
                            date.to ? (
                                <>
                                    {format(date.from, 'LLL dd, y')} -{' '}
                                    {format(date.to, 'LLL dd, y')}
                                </>
                            ) : (
                                format(date.from, 'LLL dd, y')
                            )
                        ) : (
                            <span>{placeholder}</span>
                        )}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        initialFocus
                        mode="range"
                        defaultMonth={date?.from}
                        selected={date}
                        onSelect={handleSelect}
                        numberOfMonths={2}
                    />
                </PopoverContent>
            </Popover>
        </div>
    );
}
