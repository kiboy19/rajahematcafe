import { CategoriesApp } from '@/react/features/categories/CategoriesApp';
import { MenuItemsApp } from '@/react/features/menu-items/MenuItemsApp';
import { TablesApp } from '@/react/features/tables/TablesApp';
import { BillingSettingsApp } from '@/react/features/billing/BillingSettingsApp';
import { InventoryApp } from '@/react/features/inventory/InventoryApp';
import { SuppliersApp } from '@/react/features/suppliers/SuppliersApp';
import { PurchaseOrdersApp } from '@/react/features/purchase-orders/PurchaseOrdersApp';
import { BrandingApp } from '@/react/features/branding/BrandingApp';
import { PostsApp } from '@/react/features/blog/PostsApp';
import { AnalyticsApp } from '@/react/features/analytics/AnalyticsApp';
import { useResource } from '../hooks';
import { Loading, PageHeader } from '../ui';
import { useSearchParams } from 'react-router-dom';

type AdminKind = 'categories' | 'menu' | 'tables' | 'billing' | 'branding' | 'blog' | 'analytics' | 'inventory' | 'suppliers' | 'purchase-orders';

const endpoints: Record<AdminKind, string> = {
    categories: '/api/kategori',
    menu: '/api/menu',
    tables: '/api/kelola-meja',
    billing: '/api/pengaturan/tagihan',
    branding: '/api/pengaturan/branding',
    blog: '/api/kelola-blog',
    analytics: '/api/analitik-web',
    inventory: '/api/inventory',
    suppliers: '/api/supplier',
    'purchase-orders': '/api/purchase-order',
};

const titles: Record<AdminKind, string> = {
    categories: 'Kategori Menu',
    menu: 'Daftar Menu',
    tables: 'Kelola Meja',
    billing: 'Pengaturan Tagihan',
    branding: 'Branding Website',
    blog: 'Kelola Blog',
    analytics: 'Analitik Website',
    inventory: 'Inventory & Stok',
    suppliers: 'Supplier',
    'purchase-orders': 'Purchase Order',
};

export function AdminPages({ kind }: { kind: AdminKind }) {
    const { data, raw, loading, error } = useResource<any>(endpoints[kind]);
    const [params] = useSearchParams();

    if (loading) return <Loading label={`Memuat ${titles[kind]}...`} />;

    if (error) return <PageHeader title={titles[kind]} subtitle="Gagal memuat data." />;

    const value = data ?? {};
    const root = raw ?? {};

    switch (kind) {
        case 'categories':
            return <CategoriesApp initialCategories={Array.isArray(data) ? data : (value.data ?? [])} />;
        case 'menu':
            return <MenuItemsApp
                initialItems={Array.isArray(data) ? data : (value.data ?? [])}
                initialCategories={(root.categories as any) ?? value.categories ?? []}
            />;
        case 'tables':
            return <TablesApp initialTables={Array.isArray(data) ? data : (value.data ?? [])} />;
        case 'billing':
            return <BillingSettingsApp initialSettings={data ?? {}} />;
        case 'branding':
            return <BrandingApp initialSettings={data ?? ({} as never)} />;
        case 'blog':
            return <PostsApp initialData={Array.isArray(data) ? data : (value.data ?? [])} />;
        case 'analytics':
            return <AnalyticsApp initialData={(data ?? root.data) as never} />;
        case 'inventory':
            return <InventoryApp
                initialIngredients={value.ingredients ?? []}
                initialLedgers={value.ledgers ?? []}
                movementTypes={value.movementTypes ?? []}
            />;
        case 'suppliers':
            return <SuppliersApp
                initialSuppliers={Array.isArray(data) ? data : (value.data ?? [])}
                initialIngredients={(root.ingredients as any) ?? value.ingredients ?? []}
            />;
        case 'purchase-orders':
            return <PurchaseOrdersApp
                initialOrders={value.orders ?? []}
                initialSuppliers={value.suppliers ?? []}
                initialIngredients={value.ingredients ?? []}
                initialCreateOpen={params.get('buat') === '1'}
            />;
    }
}