import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

export function formatRupiah(value: number): string {
    return 'Rp' + new Intl.NumberFormat('id-ID').format(value);
}
