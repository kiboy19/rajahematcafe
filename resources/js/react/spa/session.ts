export interface NavigationItem {
    label: string;
    route: string;
    icon: string;
}

export interface StaffUser {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'manager' | 'kasir' | 'dapur';
    role_label: string;
    navigation: NavigationItem[];
    landing_route?: string;
}

export interface CustomerUser {
    id: number;
    name: string;
    email: string;
    phone: string;
    is_active: boolean;
    balance: number;
    email_verified_at: string | null;
}

export type SessionUser = StaffUser | CustomerUser;

export function isStaffUser(user: SessionUser | null | undefined): user is StaffUser {
    return Boolean(user && 'role' in user);
}

export interface SpaBootData {
    user?: StaffUser | null;
    customer?: CustomerUser | null;
    branding?: {
        brand_name: string;
        brand_tagline: string | null;
        logo_url: string | null;
        favicon_url: string | null;
    } | null;
}