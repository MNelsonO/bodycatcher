import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
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
import type { Option } from '@/types/crm';

export function TextField({
    name,
    label,
    defaultValue,
    error,
    type = 'text',
    required = false,
    hint,
}: {
    name: string;
    label: string;
    defaultValue?: string | number | null;
    error?: string;
    type?: string;
    required?: boolean;
    hint?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                required={required}
                defaultValue={defaultValue ?? ''}
                aria-invalid={error ? true : undefined}
            />
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}

export function AreaField({
    name,
    label,
    defaultValue,
    error,
    required = false,
    hint,
}: {
    name: string;
    label: string;
    defaultValue?: string | null;
    error?: string;
    required?: boolean;
    hint?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Textarea
                id={name}
                name={name}
                required={required}
                defaultValue={defaultValue ?? ''}
                aria-invalid={error ? true : undefined}
            />
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}

export function SelectField({
    name,
    label,
    options,
    defaultValue = '',
    error,
    placeholder = 'Select',
    required = false,
    hint,
    onValueChange,
}: {
    name: string;
    label: string;
    options: Option[];
    defaultValue?: string;
    error?: string;
    placeholder?: string;
    required?: boolean;
    hint?: string;
    onValueChange?: (value: string) => void;
}) {
    const [value, setValue] = useState(defaultValue);

    useEffect(() => {
        setValue(defaultValue);
    }, [defaultValue]);

    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Select
                required={required}
                value={value === '' ? undefined : value}
                onValueChange={(next) => {
                    setValue(next);
                    onValueChange?.(next);
                }}
            >
                <SelectTrigger id={name} className="w-full">
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
            <input type="hidden" name={name} value={value} />
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}
