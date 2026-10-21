import type { ReactNode } from 'react';
import { cn } from '@/react/lib/utils';
import { money } from './api';

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: string; actions?: ReactNode }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 className="font-display text-2xl font-bold">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-ink-2">{subtitle}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export function Panel({ className, children }: { className?: string; children: ReactNode }) {
    return <div className={cn('rounded-card border border-line bg-surface shadow-1', className)}>{children}</div>;
}

export function StatCard({ label, value, tone }: { label: string; value: ReactNode; tone?: 'primary' | 'ok' | 'warn' | 'bad' }) {
    const toneClass: Record<string, string> = {
        primary: 'text-primary',
        ok: 'text-ok',
        warn: 'text-warn',
        bad: 'text-bad',
    };

    return (
        <Panel className="p-4">
            <p className="text-xs uppercase tracking-wider text-ink-2 font-semibold">{label}</p>
            <p className={cn('mt-2 font-display text-2xl font-bold tabular', tone ? toneClass[tone] : 'text-ink')}>{value}</p>
        </Panel>
    );
}

export function Badge({ children, className }: { children: ReactNode; className?: string }) {
    return <span className={cn('inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider', className)}>{children}</span>;
}

export function Money({ value, className }: { value: number | string | null | undefined; className?: string }) {
    return <span className={cn('tabular', className)}>{money(value)}</span>;
}

export function Button({
    children,
    variant = 'primary',
    className,
    ...props
}: {
    children: ReactNode;
    variant?: 'primary' | 'outline' | 'ghost' | 'danger';
    className?: string;
} & React.ButtonHTMLAttributes<HTMLButtonElement>) {
    const styles: Record<string, string> = {
        primary: 'bg-primary text-white hover:bg-primary-hover active:bg-primary-press',
        outline: 'border border-line bg-surface text-ink hover:bg-surface-2',
        ghost: 'text-ink-2 hover:bg-surface-2 hover:text-ink',
        danger: 'bg-bad text-white hover:bg-bad/90',
    };

    return (
        <button
            className={cn(
                'inline-flex h-10 items-center justify-center gap-2 rounded-input px-4 text-sm font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-40',
                styles[variant],
                className
            )}
            {...props}
        >
            {children}
        </button>
    );
}

export function Field({
    label,
    hint,
    error,
    children,
    className,
}: {
    label: string;
    hint?: string;
    error?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <label className={cn('flex flex-col gap-1 text-xs font-semibold', className)}>
            <span>{label}</span>
            {children}
            {hint && !error && <span className="text-[11px] font-normal text-ink-2">{hint}</span>}
            {error && <span className="text-[11px] font-normal text-bad">{error}</span>}
        </label>
    );
}

export const inputClass =
    'h-10 w-full rounded-input border border-line-strong bg-surface px-3 text-sm font-normal text-ink placeholder:text-ink-2/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';

export const selectClass = inputClass;

export function Loading({ label = 'Memuat data...' }: { label?: string }) {
    return (
        <Panel className="p-10 text-center">
            <p className="text-sm text-ink-2">{label}</p>
        </Panel>
    );
}

export function ErrorBox({ message }: { message: string }) {
    return (
        <div role="alert" className="rounded-card border border-bad/20 border-l-4 border-l-bad bg-bad-bg p-4 text-sm text-bad">
            {message}
        </div>
    );
}

export function EmptyState({ title, hint }: { title: string; hint?: string }) {
    return (
        <Panel className="p-10 text-center">
            <p className="font-semibold text-sm">{title}</p>
            {hint && <p className="mt-1 text-xs text-ink-2">{hint}</p>}
        </Panel>
    );
}

export function Toast({ message }: { message: { text: string; tone: 'success' | 'error' } | null }) {
    if (!message) {
        return null;
    }

    return (
        <div
            role="status"
            className={cn(
                'fixed bottom-5 right-5 z-50 max-w-sm rounded-input border border-l-4 bg-surface p-3 text-sm shadow-2',
                message.tone === 'success' ? 'border-ok/20 border-l-ok' : 'border-bad/20 border-l-bad'
            )}
        >
            {message.text}
        </div>
    );
}