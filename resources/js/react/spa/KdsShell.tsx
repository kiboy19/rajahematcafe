import { useEffect, useState } from 'react';
import { LogOut } from 'lucide-react';
import { apiRequest } from '@/react/lib/api';

export function KdsShell({
    user,
    children,
}: {
    user: { id: number; name: string; role: string; role_label: string };
    children: React.ReactNode;
}) {
    const [clock, setClock] = useState(() => new Date().toLocaleTimeString('id-ID'));
    const [loggingOut, setLoggingOut] = useState(false);

    useEffect(() => {
        const id = window.setInterval(() => setClock(new Date().toLocaleTimeString('id-ID')), 1000);

        return () => window.clearInterval(id);
    }, []);

    const handleLogout = async () => {
        setLoggingOut(true);
        await apiRequest('POST', '/logout');
        window.location.href = '/login';
    };

    return (
        <div className="min-h-screen bg-kds-bg text-kds-ink font-sans antialiased">
            <header className="min-h-14 border-b border-kds-line px-3 sm:px-5 py-2 flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2 sm:gap-3">
                    <img src="/images/logo.svg" alt="" className="h-8 w-8 shrink-0 rounded-full" />
                    <div>
                        <h1 className="font-bold text-sm sm:text-base leading-none">Dapur · KDS</h1>
                        <span className="text-[9px] sm:text-[10px] uppercase tracking-widest text-kds-muted block mt-0.5">
                            Rajahemat Coffee
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-2 sm:gap-4 text-xs text-kds-muted">
                    <span className="hidden sm:block">{user.name} · {user.role_label}</span>
                    <span className="font-mono text-base">{clock}</span>
                    <button
                        type="button"
                        onClick={handleLogout}
                        disabled={loggingOut}
                        aria-label="Keluar dari sesi dapur"
                        className="flex items-center gap-1.5 rounded-input border border-kds-line bg-kds-card px-2.5 py-1.5 text-[11px] font-semibold text-kds-ink transition hover:border-bad hover:text-bad focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-50"
                    >
                        <LogOut className="h-3.5 w-3.5" aria-hidden="true" />
                        <span className="hidden sm:inline">{loggingOut ? 'Keluar…' : 'Keluar'}</span>
                    </button>
                </div>
            </header>

            <main id="main-content" className="p-3 sm:p-4">{children}</main>
        </div>
    );
}
