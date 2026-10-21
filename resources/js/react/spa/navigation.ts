/**
 * Pemetaan route-name (laravel) → path (browser) untuk navigasi SPA.
 * Digunakan oleh StaffShell (sidebar) & DashboardPage (kartu modul).
 */
export function routeToPath(route: string): string {
    const map: Record<string, string> = {
        dashboard: '/dashboard',
        'pos.index': '/pos',
        'wallet.index': '/saldo',
        'cashier.session': '/sesi-kasir',
        'orders.index': '/pesanan',
        'kds.index': '/kds',
        'reports.daily': '/laporan/harian',
        'categories.index': '/kategori',
        'menu-items.index': '/menu',
        'inventory.index': '/inventory',
        'suppliers.index': '/supplier',
        'purchase-orders.index': '/purchase-order',
        'settings.billing.edit': '/pengaturan/tagihan',
        'settings.branding.edit': '/pengaturan/branding',
        'posts.index': '/kelola-blog',
        'analytics.index': '/analitik-web',
        'tables.index': '/kelola-meja',
        'tables.qr': '/meja/qr',
        'vouchers.index': '/voucher',
    };

    return map[route] ?? '/dashboard';
}

/**
 * Path dikatakan aktif bila sama persis, atau merupakan sub-halaman
 * (mis. /kelola-meja/12 tetap menyalakan menu Kelola Meja).
 * Root dashboard tidak boleh menyalakan menu lain lewat prefix.
 */
export function isActivePath(currentPath: string, route: string): boolean {
    const path = routeToPath(route);

    if (path === '/dashboard') {
        return currentPath === '/dashboard';
    }

    return currentPath === path || currentPath.startsWith(`${path}/`);
}