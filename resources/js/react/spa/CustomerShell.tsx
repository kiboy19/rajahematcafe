import { Link, useLocation } from 'react-router-dom';
import { Home, Menu as MenuIcon, Wallet, LogOut } from 'lucide-react';
import { apiRequest } from '@/react/lib/api';
import { Button, Toast } from './ui-primitives';
import { money } from './api';

const bottomNav = [
    { to: '/akun', label: 'Akun', icon: Home },
    { to: '/meja', label: 'Pesan', icon: MenuIcon },
    { to: '/akun/saldo', label: 'Saldo', icon: Wallet },
];

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
    const location = useLocation();

    const handleLogout = async () => {
        await apiRequest('POST', '/keluar');
        window.location.href = '/masuk';
    };

    return (
        <div className="min-h-screen flex flex-col bg-bg text-ink">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-input focus:bg-primary focus:px-4 focus:py-2 focus:text-white"
            >
                Lewati ke konten utama
            </a>
            <header className="sticky top-0 z-10 min-h-14 bg-surface border-b border-line px-4 py-2 flex items-center justify-between gap-3">
                <Link to="/akun" className="flex items-center gap-3 min-w-0">
                    <img src="/images/logo.svg" alt="" className="h-9 w-9 shrink-0 rounded-full" />
                    <span className="leading-none min-w-0">
                        <span className="font-display font-bold text-base block truncate">{user.name}</span>
                        <span className="text-[10px] uppercase tracking-wider text-ink-2">Member Rajahemat</span>
                    </span>
                </Link>

                <div className="flex items-center gap-2">
                    <Link
                        to="/akun/saldo"
                        className="rounded-input bg-surface-2 px-3 py-2 text-xs font-bold text-primary tabular hover:bg-surface-strong"
                        aria-label={`Saldo ${money(user.balance)}, isi saldo`}
                    >
                        {money(user.balance)}
                    </Link>
                    <Button variant="ghost" className="h-11 w-11 p-0" onClick={handleLogout} aria-label="Keluar dari akun">
                        <LogOut className="h-4 w-4" aria-hidden="true" />
                    </Button>
                </div>
            </header>

            <main id="main-content" className="flex-1 p-4 pb-24 md:pb-8 max-w-4xl w-full mx-auto">{children}</main>

            {/* Bottom nav mobile: akses cepat akun, pesan, saldo. */}
            <nav
                aria-label="Navigasi member"
                className="fixed inset-x-0 bottom-0 z-20 grid grid-cols-3 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] md:hidden"
            >
                {bottomNav.slice(0, 3).map((item) => {
                    const isActive = location.pathname === item.to;
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.to}
                            to={item.to}
                            aria-current={isActive ? 'page' : undefined}
                            className={`flex min-h-14 flex-col items-center justify-center gap-1 text-[11px] font-semibold ${
                                isActive ? 'text-primary' : 'text-ink-2'
                            }`}
                        >
                            <Icon className="h-5 w-5" aria-hidden="true" />
                            {item.label}
                        </Link>
                    );
                })}
            </nav>

            <Toast message={toast ?? null} />
        </div>
    );
}
