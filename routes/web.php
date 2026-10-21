<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\BillingSettingsController;
use App\Http\Controllers\CafeTableController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CashierSessionApprovalController;
use App\Http\Controllers\CashierSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Customer\CustomerAccountController;
use App\Http\Controllers\Customer\CustomerAuthController;
use App\Http\Controllers\Customer\CustomerOrderController;
use App\Http\Controllers\Customer\CustomerOrderingController;
use App\Http\Controllers\Customer\CustomerPasswordResetController;
use App\Http\Controllers\Customer\CustomerVerificationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KdsController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentVoidController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostMediaController;
use App\Http\Controllers\PublicBlogController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SiteBrandingController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WalletController;
use App\Models\Order;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/spa', 'spa')->name('spa');

// Helper: halaman web dilayani sebagai cangkang SPA; permintaan JSON tetap dilayani
// controller yang sama agar kontrak API lama (mis. tes getJson) tidak berubah.
$spa = static fn () => view('spa');

// Beranda: pengunjung melihat landing page publik, staf dialihkan ke dasbornya.
Route::get('/', function () {
    $staff = auth()->user();

    if ($staff?->role && $staff->is_active) {
        return redirect()->route($staff->role->navigation()[0]['route'] ?? 'dashboard');
    }

    if (auth('customer')->check()) {
        return redirect()->route('customer.home');
    }

    return view('spa');
})->name('home');

$page = static function (string $controller, string $method = 'index', array $parameters = []) {
    return request()->expectsJson()
        ? app()->call([app($controller), $method], $parameters)
        : view('spa');
};

// Halaman publik lainnya: katalog menu, tentang, kontak.
Route::get('/daftar-menu', fn () => $page(PublicSiteController::class, 'menu'))->name('public.menu');
Route::view('/tentang-kami', 'spa')->name('public.about');
Route::view('/kontak', 'spa')->name('public.contact');

// SEO: peta situs dan robots.txt.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Blog publik: halaman SPA, dan JSON bila diminta.
Route::get('/blog', fn () => $page(PublicBlogController::class))->name('blog.index');
Route::get('/blog/{slug}', fn (string $slug) => $page(PublicBlogController::class, 'show', ['slug' => $slug]))->name('blog.show');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'spa')->name('login');
    Route::post('/login', [StaffLoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'staff.active'])->group(function () use ($page) {
    Route::post('/logout', [StaffLoginController::class, 'destroy'])->name('logout');

    Route::middleware('role:owner,manager,kasir')->group(function () use ($page) {
        Route::view('/dashboard', 'spa')->name('dashboard');

        Route::get('/pos', fn () => $page(PosController::class))->name('pos.index');
        Route::post('/pos/keranjang', [CartController::class, 'store'])->name('pos.cart.store');
        Route::patch('/pos/keranjang', [CartController::class, 'update'])->name('pos.cart.update');
        Route::delete('/pos/keranjang', [CartController::class, 'destroy'])->name('pos.cart.destroy');
        Route::post('/pos/checkout', [CartController::class, 'checkout'])
            ->middleware('throttle:60,1')->name('pos.checkout');

        Route::get('/saldo', fn () => $page(WalletController::class))->name('wallet.index');
        Route::post('/saldo/topup', [WalletController::class, 'store'])
            ->middleware('throttle:30,1')->name('wallet.topup');

        Route::get('/sesi-kasir', fn () => $page(CashierSessionController::class, 'show'))->name('cashier.session');
        Route::post('/sesi-kasir/buka', [CashierSessionController::class, 'open'])->name('cashier.session.open');
        Route::post('/sesi-kasir/tutup', [CashierSessionController::class, 'close'])->name('cashier.session.close');

        Route::get('/pesanan', fn () => $page(OrderController::class))->name('orders.index');
        Route::get('/pesanan/{order}', fn (Order $order) => $page(OrderController::class, 'show', ['order' => $order]))->name('orders.show');
        Route::get('/pesanan/{order}/struk', fn (Order $order) => $page(OrderController::class, 'receipt', ['order' => $order]))->name('orders.receipt');
        Route::post('/pesanan/{order}/bayar', [PaymentController::class, 'store'])
            ->middleware('throttle:60,1')->name('orders.pay');
        Route::delete('/pesanan/{order}/bayar/{payment}', [PaymentVoidController::class, 'store'])
            ->middleware('role:owner,manager')->name('orders.pay.void');
    });

    Route::middleware('role:owner,manager')->group(function () use ($page) {
        Route::get('/laporan/harian', fn () => $page(ReportController::class, 'daily'))->name('reports.daily');
        Route::get('/pengaturan/tagihan', fn () => $page(BillingSettingsController::class, 'edit'))->name('settings.billing.edit');
        Route::put('/pengaturan/tagihan', [BillingSettingsController::class, 'update'])->name('settings.billing.update');

        // Branding website (logo, identitas, kontak, media sosial).
        Route::get('/pengaturan/branding', fn () => $page(SiteBrandingController::class, 'edit'))->name('settings.branding.edit');
        Route::put('/pengaturan/branding', [SiteBrandingController::class, 'update'])
            ->name('settings.branding.update');

        // Inventory & Stok
        Route::get('/inventory', fn () => $page(InventoryController::class))->name('inventory.index');
        Route::post('/inventory/bahan', [InventoryController::class, 'store'])->name('inventory.store');
        Route::patch('/inventory/bahan/{ingredient}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::patch('/inventory/bahan/{ingredient}/status', [InventoryController::class, 'toggleStatus'])->name('inventory.toggle');
        Route::post('/inventory/bahan/{ingredient}/pergerakan', [InventoryController::class, 'recordMovement'])->name('inventory.movement');

        Route::get('/supplier', fn () => $page(SupplierController::class))->name('suppliers.index');
        Route::post('/supplier', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::patch('/supplier/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::patch('/supplier/{supplier}/status', [SupplierController::class, 'toggleStatus'])->name('suppliers.toggle');
        Route::delete('/supplier/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('/purchase-order', fn () => $page(PurchaseOrderController::class))->name('purchase-orders.index');
        Route::view('/purchase-order/buat', 'spa')->name('purchase-orders.create');
        Route::post('/purchase-order', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('/purchase-order/{purchaseOrder}', fn (PurchaseOrder $purchaseOrder) => $page(PurchaseOrderController::class, 'show', ['purchaseOrder' => $purchaseOrder]))->name('purchase-orders.show');
        Route::post('/purchase-order/{purchaseOrder}/ajukan', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
        Route::post('/purchase-order/{purchaseOrder}/setujui', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
        Route::post('/purchase-order/{purchaseOrder}/terima', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');

        // Master data — hanya owner/manager.
        Route::get('/kategori', fn () => $page(CategoryController::class))->name('categories.index');
        Route::post('/kategori', [CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/kategori/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('/kategori/{category}/status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle');
        Route::delete('/kategori/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/menu', fn () => $page(MenuItemController::class))->name('menu-items.index');
        Route::post('/menu', [MenuItemController::class, 'store'])->name('menu-items.store');
        Route::patch('/menu/{menuItem}', [MenuItemController::class, 'update'])->name('menu-items.update');
        Route::patch('/menu/{menuItem}/ketersediaan', [MenuItemController::class, 'toggleAvailability'])->name('menu-items.availability');
        Route::delete('/menu/{menuItem}', [MenuItemController::class, 'destroy'])->name('menu-items.destroy');

        // Kelola meja (admin) sengaja memakai prefix berbeda agar tidak bertabrakan
        // dengan route pelanggan `GET /meja` (menu QR meja).
        Route::get('/kelola-meja', fn () => $page(CafeTableController::class))->name('tables.index');
        Route::post('/kelola-meja', [CafeTableController::class, 'store'])->name('tables.store');
        Route::patch('/kelola-meja/{table}', [CafeTableController::class, 'update'])->name('tables.update');
        Route::patch('/kelola-meja/{table}/status', [CafeTableController::class, 'toggleStatus'])->name('tables.toggle');
        Route::delete('/kelola-meja/{table}', [CafeTableController::class, 'destroy'])->name('tables.destroy');
        Route::get('/meja/qr', fn () => $page(CafeTableController::class, 'qr'))->name('tables.qr');
        Route::view('/meja/qr-cetak', 'spa')->name('tables.qr.print');
        Route::get('/meja/{table}/qr.svg', [CafeTableController::class, 'qrImage'])->name('tables.qr.image');
        Route::post('/meja/{table}/qr-ulang', [CafeTableController::class, 'rotateQr'])
            ->name('tables.qr.rotate');
        Route::get('/voucher', fn () => $page(VoucherController::class))->name('vouchers.index');
        Route::post('/voucher', [VoucherController::class, 'store'])->name('vouchers.store');
        Route::patch('/voucher/{voucher}/status', [VoucherController::class, 'toggle'])->name('vouchers.toggle');

        // Blog (kelola artikel) — owner/manager.
        Route::get('/kelola-blog', fn () => $page(PostController::class))->name('posts.index');
        Route::post('/kelola-blog', [PostController::class, 'store'])->name('posts.store');
        Route::get('/kelola-blog/{post}', [PostController::class, 'show'])->name('posts.show');
        Route::patch('/kelola-blog/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::post('/kelola-blog/{post}/terbitkan', [PostController::class, 'publish'])->name('posts.publish');
        Route::post('/kelola-blog/{post}/draf', [PostController::class, 'draft'])->name('posts.draft');
        Route::delete('/kelola-blog/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

        // Media inline untuk editor blog (unggah gambar ke posts/inline).
        Route::post('/kelola-blog/media', [PostMediaController::class, 'store'])->name('posts.media');

        // Analitik kunjungan website.
        Route::get('/analitik-web', fn () => $page(AnalyticsController::class))->name('analytics.index');
        Route::post('/sesi-kasir/{session}/verifikasi-selisih', [CashierSessionApprovalController::class, 'update'])
            ->name('cashier.session.verify');
    });

    Route::middleware('role:owner,manager,dapur')->group(function () use ($page) {
        Route::get('/kds', fn () => $page(KdsController::class))->name('kds.index');
        Route::patch('/kds/{order}', [KdsController::class, 'updateStatus'])->name('kds.status');
    });
});

/* --------------------------------------------------------------------
 | Kanal pelanggan — guard `customer`, terpisah dari staf.
 | --------------------------------------------------------------------
 */
// Scanning the table QR lands here; guests are sent to /masuk and returned after login.
Route::get('/m/{token}', [CustomerOrderingController::class, 'scan'])->name('customer.table.scan');

/*
 * Midtrans Payment Gateway Webhook (R1.2).
 * Endpoint publik untuk menerima notifikasi server-to-server.
 * Dibebaskan dari CSRF; keamanan bergantung pada verifikasi signature SHA512.
 */
Route::post('/midtrans/webhook', MidtransWebhookController::class)
    ->name('midtrans.webhook');

Route::name('customer.')->group(function () use ($page) {
    Route::middleware('guest:customer')->group(function () {
        Route::get('/masuk', [CustomerAuthController::class, 'showLogin'])->name('login');
        Route::post('/masuk', [CustomerAuthController::class, 'login'])->name('login.store');
        Route::get('/daftar', [CustomerAuthController::class, 'showRegister'])->name('register');
        Route::post('/daftar', [CustomerAuthController::class, 'register'])
            ->middleware('throttle:10,1')->name('register.store');

        // Reset sandi: terbatas per IP agar email orang lain tidak bisa dibanjiri tautan reset.
        Route::get('/lupa-sandi', [CustomerPasswordResetController::class, 'request'])->name('password.request');
        Route::post('/lupa-sandi', [CustomerPasswordResetController::class, 'email'])
            ->middleware('throttle:6,1')->name('password.email');
        Route::get('/atur-ulang-sandi/{token}', [CustomerPasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('/atur-ulang-sandi', [CustomerPasswordResetController::class, 'update'])->name('password.update');
    });

    Route::middleware('auth:customer')->group(function () {
        Route::get('/verifikasi-email', [CustomerVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('/verifikasi-email/{id}/{hash}', [CustomerVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
        Route::post('/verifikasi-email/kirim-ulang', [CustomerVerificationController::class, 'send'])
            ->middleware('throttle:6,1')->name('verification.send');
    });

    Route::middleware('auth:customer')->group(function () use ($page) {
        Route::post('/keluar', [CustomerAuthController::class, 'logout'])->name('logout');
        Route::get('/akun', fn () => $page(CustomerAccountController::class, 'show'))->name('home');
        Route::view('/akun/saldo', 'spa')->name('wallet.topup');
        Route::get('/akun/pesanan/{order}', function (Request $request, Order $order) {
            abort_unless($order->customer_id === $request->user('customer')?->id, 404);

            return $request->expectsJson()
                ? app()->call([app(CustomerOrderController::class), 'show'], ['order' => $order])
                : view('spa');
        })->name('order.show');
        Route::get('/akun/pesanan/{order}/bayar', function (Request $request, Order $order) {
            abort_unless($order->customer_id === $request->user('customer')?->id, 404);

            return view('spa');
        })->name('order.pay');

        Route::get('/verifikasi-email', [CustomerVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('/verifikasi-email/{id}/{hash}', [CustomerVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
        Route::post('/verifikasi-email/kirim-ulang', [CustomerVerificationController::class, 'send'])
            ->middleware('throttle:6,1')->name('verification.send');
    });

    /*
     * Memesan hanya diwajibkan verifikasi email setelah owner mengaktifkan
     * POS_REQUIRE_CUSTOMER_EMAIL_VERIFICATION (menunggu SMTP Gate 0). Selama nonaktif,
     * pelanggan yang belum verifikasi tetap bisa memesan dan hanya melihat ajakan verifikasi di /akun.
     */
    Route::middleware(['auth:customer', 'customer.verified'])->group(function () {
        Route::get('/meja', [CustomerOrderingController::class, 'menu'])->name('order.menu');
        Route::post('/meja/keranjang', [CustomerOrderingController::class, 'addToCart'])->name('order.cart.add');
        Route::patch('/meja/keranjang', [CustomerOrderingController::class, 'updateCart'])->name('order.cart.update');
        Route::delete('/meja/keranjang', [CustomerOrderingController::class, 'clearCart'])->name('order.cart.clear');
        Route::post('/meja/pesan', [CustomerOrderingController::class, 'checkout'])
            ->middleware('throttle:30,1')->name('order.checkout');
    });
});
