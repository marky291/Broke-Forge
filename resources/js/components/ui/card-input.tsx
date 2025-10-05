import * as React from 'react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export interface CardInputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'id'> {
    label: string;
    error?: string;
    id?: string;
    className?: string;
}

export function CardInput({
    label,
    error,
    id,
    className,
    ...props
}: CardInputProps) {
    const inputId = id || label.toLowerCase().replace(/\s+/g, '-');

    return (
        <div className={cn('flex items-center justify-between gap-4', className)}>
            <Label htmlFor={inputId} className="shrink-0">
                {label}
            </Label>
            <div className="flex-1 space-y-2 max-w-sm">
                <Input id={inputId} {...props} />
                {error && <div className="text-sm text-red-600">{error}</div>}
            </div>
        </div>
    );
}
