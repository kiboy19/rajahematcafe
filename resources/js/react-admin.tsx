import React from 'react';
import { createRoot } from 'react-dom/client';
import { CategoriesApp } from '@/react/features/categories/CategoriesApp';
import { MenuItemsApp } from '@/react/features/menu-items/MenuItemsApp';
import { TablesApp } from '@/react/features/tables/TablesApp';
import { BillingSettingsApp } from '@/react/features/billing/BillingSettingsApp';
import { InventoryApp } from '@/react/features/inventory/InventoryApp';
import { SuppliersApp } from '@/react/features/suppliers/SuppliersApp';
import { PurchaseOrdersApp } from '@/react/features/purchase-orders/PurchaseOrdersApp';

function parseMountData<T>(element: HTMLElement): T | undefined {
    const raw = element.dataset.props;
    if (!raw) return undefined;
    try {
        return JSON.parse(raw) as T;
    } catch (error) {
        console.error('Failed to parse React mount data:', error);
        return undefined;
    }
}

function renderApp(root: HTMLElement, node: React.ReactNode): void {
    createRoot(root).render(<React.StrictMode>{node}</React.StrictMode>);
}

function boot(): void {
    document.querySelectorAll<HTMLElement>('[data-app]').forEach((root) => {
        const app = root.dataset.app;
        const data = parseMountData<Record<string, unknown>>(root) ?? {};

        switch (app) {
            case 'categories':
                renderApp(root, <CategoriesApp initialCategories={(data.categories as never[]) ?? []} />);
                break;
            case 'menu-items':
                renderApp(root, <MenuItemsApp initialItems={(data.items as never[]) ?? []} initialCategories={(data.menuCategories as never[]) ?? []} />);
                break;
            case 'tables':
                renderApp(root, <TablesApp initialTables={(data.tables as never[]) ?? []} />);
                break;
            case 'billing':
                renderApp(root, <BillingSettingsApp initialSettings={data.settings as never} />);
                break;
            case 'inventory':
                renderApp(root, <InventoryApp initialIngredients={(data.ingredients as never[]) ?? []} initialLedgers={(data.ledgers as never[]) ?? []} movementTypes={(data.movementTypes as never[]) ?? []} />);
                break;
            case 'suppliers':
                renderApp(root, <SuppliersApp initialSuppliers={(data.suppliers as never[]) ?? []} initialIngredients={(data.ingredients as never[]) ?? []} />);
                break;
            case 'purchase-orders':
                renderApp(root, <PurchaseOrdersApp initialOrders={(data.orders as never[]) ?? []} initialSuppliers={(data.suppliers as never[]) ?? []} initialIngredients={(data.ingredients as never[]) ?? []} />);
                break;
            default:
                console.warn(`Unknown React app: ${app}`);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
