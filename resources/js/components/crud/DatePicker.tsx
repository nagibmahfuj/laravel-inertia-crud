import * as React from 'react';
import { format } from 'date-fns';
import { Calendar as CalendarIcon } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

interface DatePickerProps {
    className?: string;
    value?: string;
    onChange: (date: string | null) => void;
    placeholder?: string;
}

export function DatePicker({
    className,
    value,
    onChange,
    placeholder = 'Pick a date',
}: DatePickerProps) {
    const [date, setDate] = React.useState<Date | undefined>(() => {
        if (value) {
            const d = new Date(value);
            if (!isNaN(d.getTime())) {
                return d;
            }
        }
        return undefined;
    });

    // Sync internal state with external value
    React.useEffect(() => {
        if (value) {
            const d = new Date(value);
            if (!isNaN(d.getTime())) {
                setDate(d);
            } else {
                setDate(undefined);
            }
        } else {
            setDate(undefined);
        }
    }, [value]);

    const handleSelect = (newDate: Date | undefined) => {
        setDate(newDate);
        if (newDate) {
            onChange(format(newDate, 'yyyy-MM-dd'));
        } else {
            onChange(null);
        }
    };

    return (
        <div className={cn('grid gap-2', className)}>
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        variant={'outline'}
                        size="sm"
                        className={cn(
                            'h-8 w-fit justify-start text-left font-normal',
                            !date && 'text-muted-foreground',
                        )}
                    >
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        {date ? (
                            format(date, 'PPP')
                        ) : (
                            <span>{placeholder}</span>
                        )}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="single"
                        selected={date}
                        onSelect={handleSelect}
                        initialFocus
                    />
                </PopoverContent>
            </Popover>
        </div>
    );
}
