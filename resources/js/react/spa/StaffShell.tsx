import { useState, type ReactNode } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { apiRequest } from '@/react/lib/api';
import { routeToPath, isActivePath } from './navigation';
import { Button, Toast } from './ui';
import type { StaffUser } from './session';

function NavIcon({ name }: { name: string }) {
    switch (name) {
        case 'chart':
            return (
                <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
            );
        case 'cart':
            return (
                <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.7 2.84-7.242a.75.75 0 0 0-.726-.958H5.106m2.394 8.2l-1.4-5.242M9 20.25a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm10.5 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                </svg>
            );
        case 'credit-card':
            return (
                <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-6.75 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z" />
                </svg>
            );
        case 'fire':
            return (
                <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                </svg>
            );
        default:
            return (
                <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            );
    }
}

export function StaffShell({
    user,
    children,
    toast,
}: {
    user: StaffUser;
    children: ReactNode;
    toast?: { text: string; tone: 'success' | 'error' } | null;
}) {
    const location = useLocation();
    const navigate = useNavigate();
    const [navOpen, setNavOpen] = useState(false);

    const handleLogout = async () => {
        await apiRequest('POST', '/logout');
        window.location.href = '/login';
    };

    return (
        <div className="min-h-screen flex flex-col bg-bg text-ink">
            {/* Header Utama */}
            <header className="min-h-14 bg-surface border-b border-line px-4 sm:px-5 py-2 flex flex-wrap items-center justify-between gap-3 sticky top-0 z-30">
                <div className="flex items-center gap-3">
                    <span className="w-8 h-8 rounded-full bg-accent text-ink-on-dark flex items-center justify-center font-display font-bold text-sm">
                        R
                    </span>
                    <div>
                        <Link to="/dashboard" className="font-display font-bold text-base leading-none block hover:text-primary">
                            Rajahemat
                        </Link>
                        <span className="text-[10px] uppercase tracking-wider text-ink-2">Coffee &amp; Eatery</span>
                    </div>
                </div>

                <div className="flex items-center gap-2 sm:gap-4">
                    {user.navigation.length > 0 && (
                        <button
                            type="button"
                            onClick={() => setNavOpen(!navOpen)}
                            className="lg:hidden h-9 w-9 rounded-input border border-line flex items-center justify-center text-ink-2 hover:bg-surface-2"
                            aria-label="Buka menu navigasi"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
                                <path strokeLinecap="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    )}

                    <div className="hidden md:block text-right">
                        <span className="font-semibold block text-sm leading-tight">{user.name}</span>
                        <span className="text-[11px] text-ink-2">{user.role_label}</span>
                    </div>

                    <Button variant="outline" className="h-9 px-3 text-xs" onClick={handleLogout}>
                        Keluar
                    </Button>
                </div>
            </header>

            <div className="flex-1 flex min-w-0">
                {/* Sidebar Navigasi Desktop & Mobile Drawer */}
                {user.navigation.length > 0 && (
                    <>
                        <aside
                            className={`fixed inset-y-0 left-0 z-40 w-64 bg-surface border-r border-line p-4 flex flex-col gap-1 pt-20 shadow-2 transition-transform duration-150 lg:static lg:z-auto lg:w-56 lg:shrink-0 lg:pt-4 lg:shadow-none lg:translate-x-0 ${
                                navOpen ? 'translate-x-0' : '-translate-x-full'
                            }`}
                        >
                            <div className="md:hidden pb-3 border-b border-line mb-2">
                                <p className="font-bold text-sm">{user.name}</p>
                                <p className="text-xs text-ink-2">{user.role_label}</p>
                            </div>

                            {user.navigation.map((item) => {
                                const path = routeToPath(item.route);
                                const isActive = isActivePath(location.pathname, item.route);

                                return (
                                    <Link
                                        key={item.route}
                                        to={path}
                                        onClick={() => setNavOpen(false)}
                                        aria-current={isActive ? 'page' : undefined}
                                        className={`px-3 py-2.5 rounded-input text-sm font-semibold flex items-center gap-2.5 transition-colors ${
                                            isActive
                                                ? 'bg-primary text-white shadow-1'
                                                : 'text-ink-2 hover:bg-surface-2 hover:text-ink'
                                        }`}
                                    >
                                        <NavIcon name={item.icon} />
                                        <span>{item.label}</span>
                                    </Link>
                                );
                            })}
                        </aside>

                        {navOpen && (
                            <div
                                className="fixed inset-0 z-30 bg-ink/40 lg:hidden"
                                onClick={() => setNavOpen(false)}
                            />
                        )}
                    </>
                )}

                {/* Konten Halaman */}
                <main className="flex-1 min-w-0 p-4 sm:p-6">{children}</main>
            </div>

            <Toast message={toast ?? null} />
        </div>
    );
}
