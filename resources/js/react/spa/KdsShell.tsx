import { useEffect, useState } from 'react';

export function KdsShell({
    user,
    children,
}: {
    user: { id: number; name: string; role: string; role_label: string };
    children: React.ReactNode;
}) {
    const [clock, setClock] = useState(() => new Date().toLocaleTimeString('id-ID'));

    useEffect(() => {
        const id = window.setInterval(() => setClock(new Date().toLocaleTimeString('id-ID')), 1000);

        return () => window.clearInterval(id);
    }, []);

    return (
        <div className="min-h-screen bg-kds-bg text-kds-ink font-sans antialiased">
            <header className="min-h-14 border-b border-kds-line px-3 sm:px-5 py-2 flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2 sm:gap-3">
                    <span className="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-display font-bold text-sm shrink-0">
                        R
                    </span>
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
                </div>
            </header>

            <main className="p-3 sm:p-4">{children}</main>
        </div>
    );
}