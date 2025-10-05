import * as React from 'react';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type CardInputDropdownOption = {
    value: string;
    label: string;
};

export interface CardInputDropdownProps {
    label: string;
    value: string;
    onValueChange: (value: string) => void;
    options: CardInputDropdownOption[];
    placeholder?: string;
    error?: string;
    id?: string;
    className?: string;
}

export function CardInputDropdown({
    label,
    value,
    onValueChange,
    options,
    placeholder = 'Select an option',
    error,
    id,
    className,
}: CardInputDropdownProps) {
    const inputId = id || label.toLowerCase().replace(/\s+/g, '-');

    return (
        <div className={cn('flex items-center justify-between gap-4', className)}>
            <Label htmlFor={inputId} className="shrink-0">
                {label}
            </Label>
            <div className="flex-1 space-y-2 max-w-sm">
                <Select value={value} onValueChange={onValueChange}>
                    <SelectTrigger id={inputId} className="w-full">
                        <SelectValue placeholder={placeholder} />
                    </SelectTrigger>
                    <SelectContent>
                        {options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {error && <div className="text-sm text-red-600">{error}</div>}
            </div>
        </div>
    );
}
