import * as React from 'react';
import { cn } from '@/react/lib/utils';

const inputBase =
    'flex h-11 w-full rounded-input border border-line-strong bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-2/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50';

const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(
    ({ className, type, ...props }, ref) => (
        <input ref={ref} type={type} className={cn(inputBase, className)} {...props} />
    )
);
Input.displayName = 'Input';

const Textarea = React.forwardRef<HTMLTextAreaElement, React.TextareaHTMLAttributes<HTMLTextAreaElement>>(
    ({ className, ...props }, ref) => (
        <textarea ref={ref} className={cn(inputBase, 'h-auto min-h-20', className)} {...props} />
    )
);
Textarea.displayName = 'Textarea';

const Select = React.forwardRef<HTMLSelectElement, React.SelectHTMLAttributes<HTMLSelectElement>>(
    ({ className, children, ...props }, ref) => (
        <select ref={ref} className={cn(inputBase, 'pr-8', className)} {...props}>
            {children}
        </select>
    )
);
Select.displayName = 'Select';

export { Input, Textarea, Select, inputBase };