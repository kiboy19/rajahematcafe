import { Link } from 'react-router-dom';
import { apiRequest } from '@/react/lib/api';
import { Button, Toast } from './ui';

export function CustomerShell({
    user,
    children,
    toast,
}: {
    user: {
        id: number;
        name: string;
        email: string;
        phone: string;
        balance: number;
        email_verified_at: string | null;
    };
    children: React.ReactNode;
    toast?: { text: string; tone: 'success' | 'error' } | null;
}) {
    const handleLogout = async () => {
        await apiRequest('POST', '/keluar');
        window.location.href = '/masuk';
    };

    return (
        <div className="min-h-screen flex flex-col bg-bg text-ink">
            <header className="sticky top-0 z-10 h-14 bg-surface border-b border-line px-4 flex items-center justify-between">
                <Link to="/akun" className="flex items-center gap-3">
                    <span className="w-8 h-8 rounded-full bg-accent text-ink-on-dark flex items-center justify-center font-display font-bold text-sm">
                        R
                    </span>
                    <span className="leading-none">
                        <span className="font-display font-bold text-base block">Rajahemat</span>
                        <span className="text-[10px] uppercase tracking-wider text-ink-2">Coffee &amp; Eatery</span>
                    </span>
                </Link>

                <div className="flex items-center gap-3">
                    <span className="hidden sm:inline text-sm font-semibold text-ink">{user.name}</span>
                    <span className="text-xs text-ink-2 tabular">Rp {user.balance.toLocaleString('id-ID')}</span>
                    <Button variant="outline" className="h-9 px-3 text-xs" onClick={handleLogout}>
                        Keluar
                    </Button>
                </div>
            </header>

            <main className="flex-1 p-4">{children}</main>

            <Toast message={toast ?? null} />
        </div>
    );
}
