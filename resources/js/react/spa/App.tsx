import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { StaffShell } from './StaffShell';
import { CustomerShell } from './CustomerShell';
import { KdsShell } from './KdsShell';
import { DashboardPage } from './pages/DashboardPage';
import { PosPage } from './pages/PosPage';
import { CashierSessionPage } from './pages/CashierSessionPage';
import { OrdersPage } from './pages/OrdersPage';
import { OrderShowPage } from './pages/OrderShowPage';
import { KdsPage } from './pages/KdsPage';
import { WalletPage } from './pages/WalletPage';
import { VouchersPage } from './pages/VouchersPage';
import { ReportsPage } from './pages/ReportsPage';
import { ReceiptPage } from './pages/ReceiptPage';
import { AdminPages } from './pages/AdminPages';
import { TableQrPage } from './pages/TableQrPage';
import { TableQrPrintPage } from './pages/TableQrPrintPage';
import { CustomerAccountPage } from './pages/CustomerAccountPage';
import { CustomerMenuPage, CustomerOrderShowPage, CustomerPaymentPage } from './pages/CustomerOrderPages';
import { CustomerWalletTopUpPage } from './pages/CustomerWalletPages';
import { BlogListPage } from './pages/BlogListPage';
import { BlogPostPage } from './pages/BlogPostPage';
import { LandingPage, PublicMenuPage, AboutPage, ContactPage } from './pages/PublicPages';
import { StaffLoginPage, CustomerLoginPage, RegisterPage, ForgotPasswordPage, ResetPasswordPage, VerifyEmailPage } from './pages/AuthPages';
import { setMidtransConfig } from './midtrans';
import type { SpaBootData } from './session';

function StaffApp({ boot }: { boot: SpaBootData }) {
    const user = boot.user;

    if (!user) {
        return null;
    }

    const kds = user.role === 'dapur';

    if (window.location.pathname === '/meja/qr-cetak') {
        return <TableQrPrintPage />;
    }

    const content = (
        <Routes>
            <Route path="/dashboard" element={<DashboardPage user={user} />} />
            <Route path="/pos" element={<PosPage />} />
            <Route path="/sesi-kasir" element={<CashierSessionPage user={user} />} />
            <Route path="/pesanan" element={<OrdersPage />} />
            <Route path="/pesanan/:id" element={<OrderShowPage user={user} />} />
            <Route path="/pesanan/:id/struk" element={<ReceiptPage />} />
            <Route path="/kds" element={<KdsPage />} />
            <Route path="/saldo" element={<WalletPage />} />
            <Route path="/voucher" element={<VouchersPage />} />
            <Route path="/laporan/harian" element={<ReportsPage />} />
            <Route path="/kategori" element={<AdminPages key="categories" kind="categories" />} />
            <Route path="/menu" element={<AdminPages key="menu" kind="menu" />} />
            <Route path="/kelola-meja" element={<AdminPages key="tables" kind="tables" />} />
            <Route path="/meja/qr" element={<TableQrPage />} />
            <Route path="/pengaturan/tagihan" element={<AdminPages key="billing" kind="billing" />} />
            <Route path="/pengaturan/branding" element={<AdminPages key="branding" kind="branding" />} />
            <Route path="/kelola-blog" element={<AdminPages key="blog" kind="blog" />} />
            <Route path="/analitik-web" element={<AdminPages key="analytics" kind="analytics" />} />
            <Route path="/blog" element={<BlogListPage />} />
            <Route path="/blog/:slug" element={<BlogPostPage />} />
            <Route path="/daftar-menu" element={<PublicMenuPage />} />
            <Route path="/tentang-kami" element={<AboutPage />} />
            <Route path="/kontak" element={<ContactPage />} />
            <Route path="/inventory" element={<AdminPages key="inventory" kind="inventory" />} />
            <Route path="/supplier" element={<AdminPages key="suppliers" kind="suppliers" />} />
            <Route path="/purchase-order" element={<AdminPages key="purchase-orders" kind="purchase-orders" />} />
            <Route path="/purchase-order/buat" element={<Navigate to="/purchase-order?buat=1" replace />} />
            <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
    );

    if (kds) {
        return <KdsShell user={user}>{content}</KdsShell>;
    }

    return <StaffShell user={user}>{content}</StaffShell>;
}

function CustomerApp({ boot }: { boot: SpaBootData }) {
    const user = boot.customer;

    if (!user) {
        return null;
    }

    return (
        <CustomerShell user={user}>
            <Routes>
                <Route path="/akun" element={<CustomerAccountPage user={user} />} />
                <Route path="/akun/saldo" element={<CustomerWalletTopUpPage user={user} />} />
                <Route path="/akun/pesanan/:id" element={<CustomerOrderShowPage />} />
                <Route path="/akun/pesanan/:id/bayar" element={<CustomerPaymentPage user={user} />} />
                <Route path="/meja" element={<CustomerMenuPage user={user} />} />
                <Route path="/blog" element={<BlogListPage />} />
                <Route path="/blog/:slug" element={<BlogPostPage />} />
                <Route path="/daftar-menu" element={<PublicMenuPage />} />
                <Route path="/tentang-kami" element={<AboutPage />} />
                <Route path="/kontak" element={<ContactPage />} />
                <Route path="/verifikasi-email" element={<VerifyEmailPage />} />
                <Route path="*" element={<Navigate to="/akun" replace />} />
            </Routes>
        </CustomerShell>
    );
}

function PublicApp() {
    return (
        <Routes>
            <Route path="/" element={<LandingPage />} />
            <Route path="/daftar-menu" element={<PublicMenuPage />} />
            <Route path="/tentang-kami" element={<AboutPage />} />
            <Route path="/kontak" element={<ContactPage />} />
            <Route path="/login" element={<StaffLoginPage />} />
            <Route path="/masuk" element={<CustomerLoginPage />} />
            <Route path="/daftar" element={<RegisterPage />} />
            <Route path="/lupa-sandi" element={<ForgotPasswordPage />} />
            <Route path="/atur-ulang-sandi/:token" element={<ResetPasswordPage />} />
            <Route path="/blog" element={<BlogListPage />} />
            <Route path="/blog/:slug" element={<BlogPostPage />} />
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}

export function App({ boot }: { boot: SpaBootData }) {
    setMidtransConfig(boot.midtransClientKey, boot.isMidtransProduction);

    if (boot.user) {
        return <BrowserRouter><StaffApp boot={boot} /></BrowserRouter>;
    }

    if (boot.customer) {
        return <BrowserRouter><CustomerApp boot={boot} /></BrowserRouter>;
    }

    return <BrowserRouter><PublicApp /></BrowserRouter>;
}