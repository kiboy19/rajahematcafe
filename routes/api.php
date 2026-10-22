<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\BillingSettingsController;
use App\Http\Controllers\CafeTableController as TableController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CashierSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Customer\CustomerAccountController;
use App\Http\Controllers\Customer\CustomerAuthController;
use App\Http\Controllers\Customer\CustomerOrderController;
use App\Http\Controllers\Customer\CustomerOrderingController;
use App\Http\Controllers\Customer\CustomerWalletController;
use App\Http\Controllers\Customer\PaymentInitiationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KdsController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentVoidController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostMediaController;
use App\Http\Controllers\PublicAnalyticsController;
use App\Http\Controllers\PublicBlogController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SiteBrandingController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Backend JSON untuk SPA React
|--------------------------------------------------------------------------
|
| Semua UI dirender oleh React SPA (single page). Laravel hanya berperan
| sebagai backend: authorization, validasi, transaksi, audit log, dan data.
| Autentikasi memakai session cookie guard `web` (staf) dan `customer`
| (pelanggan) sehingga policy yang sudah teruji tetap berlaku.
|
*/

// Route file ini sudah otomatis berada di bawah prefix /api (lihat bootstrap/app.php).
Route::group([], function () {
    // Profil sesi yang sedang aktif (dipakai SPA saat boot).
    Route::get('/me', [StaffLoginController::class, 'me'])->name('api.me');
    Route::get('/customer/me', [CustomerAuthController::class, 'me'])->name('api.customer.me');

    // Staf: owner/manager/kasir
    Route::middleware(['auth', 'staff.active'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

        Route::get('/pos', [PosController::class, 'index'])->name('api.pos.index');
        Route::post('/pos/keranjang', [CartController::class, 'store'])->name('api.pos.cart.store');
        Route::patch('/pos/keranjang', [CartController::class, 'update'])->name('api.pos.cart.update');
        Route::delete('/pos/keranjang', [CartController::class, 'destroy'])->name('api.pos.cart.destroy');
        Route::post('/pos/checkout', [CartController::class, 'checkout'])->middleware('throttle:60,1')->name('api.pos.checkout');

        Route::get('/saldo', [WalletController::class, 'index'])->name('api.wallet.index');
        Route::post('/saldo/topup', [WalletController::class, 'store'])->middleware('throttle:30,1')->name('api.wallet.topup');

        Route::get('/sesi-kasir', [CashierSessionController::class, 'show'])->name('api.cashier.session');
        Route::post('/sesi-kasir/buka', [CashierSessionController::class, 'open'])->name('api.cashier.session.open');
        Route::post('/sesi-kasir/tutup', [CashierSessionController::class, 'close'])->name('api.cashier.session.close');
        Route::post('/sesi-kasir/{session}/tutup-paksa', [CashierSessionController::class, 'forceClose'])
            ->middleware('role:owner,manager')
            ->name('api.cashier.session.force-close');

        Route::get('/pesanan', [OrderController::class, 'index'])->name('api.orders.index');
        Route::get('/pesanan/{order}', [OrderController::class, 'show'])->name('api.orders.show');
        Route::get('/pesanan/{order}/struk', [OrderController::class, 'receipt'])->name('api.orders.receipt');
        Route::post('/pesanan/{order}/bayar/midtrans', [PaymentController::class, 'initiateMidtrans'])->middleware('throttle:30,1')->name('api.orders.pay.midtrans');
        Route::post('/pesanan/{order}/bayar', [PaymentController::class, 'store'])->middleware('throttle:60,1')->name('api.orders.pay');
        Route::delete('/pesanan/{order}/bayar/{payment}', [PaymentVoidController::class, 'store'])->middleware('role:owner,manager')->name('api.orders.pay.void');

        Route::middleware('role:owner,manager,dapur')->group(function () {
            Route::get('/kds', [KdsController::class, 'index'])->name('api.kds.index');
            Route::patch('/kds/{order}', [KdsController::class, 'updateStatus'])->name('api.kds.status');
        });

        Route::middleware('role:owner,manager')->group(function () {
            Route::get('/laporan/harian', [ReportController::class, 'daily'])->name('api.reports.daily');
            Route::get('/pengaturan/tagihan', [BillingSettingsController::class, 'edit'])->name('api.settings.billing.edit');
            Route::put('/pengaturan/tagihan', [BillingSettingsController::class, 'update'])->name('api.settings.billing.update');

            Route::get('/inventory', [InventoryController::class, 'index'])->name('api.inventory.index');
            Route::post('/inventory/bahan', [InventoryController::class, 'store'])->name('api.inventory.store');
            Route::patch('/inventory/bahan/{ingredient}', [InventoryController::class, 'update'])->name('api.inventory.update');
            Route::patch('/inventory/bahan/{ingredient}/status', [InventoryController::class, 'toggleStatus'])->name('api.inventory.toggle');
            Route::post('/inventory/bahan/{ingredient}/pergerakan', [InventoryController::class, 'recordMovement'])->name('api.inventory.movement');

            Route::get('/supplier', [SupplierController::class, 'index'])->name('api.suppliers.index');
            Route::post('/supplier', [SupplierController::class, 'store'])->name('api.suppliers.store');
            Route::patch('/supplier/{supplier}', [SupplierController::class, 'update'])->name('api.suppliers.update');
            Route::patch('/supplier/{supplier}/status', [SupplierController::class, 'toggleStatus'])->name('api.suppliers.toggle');
            Route::delete('/supplier/{supplier}', [SupplierController::class, 'destroy'])->name('api.suppliers.destroy');

            Route::get('/purchase-order', [PurchaseOrderController::class, 'index'])->name('api.purchase-orders.index');
            Route::post('/purchase-order', [PurchaseOrderController::class, 'store'])->name('api.purchase-orders.store');
            Route::get('/purchase-order/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('api.purchase-orders.show');
            Route::post('/purchase-order/{purchaseOrder}/ajukan', [PurchaseOrderController::class, 'submit'])->name('api.purchase-orders.submit');
            Route::post('/purchase-order/{purchaseOrder}/setujui', [PurchaseOrderController::class, 'approve'])->name('api.purchase-orders.approve');
            Route::post('/purchase-order/{purchaseOrder}/terima', [PurchaseOrderController::class, 'receive'])->name('api.purchase-orders.receive');

            Route::get('/kategori', [CategoryController::class, 'index'])->name('api.categories.index');
            Route::post('/kategori', [CategoryController::class, 'store'])->name('api.categories.store');
            Route::patch('/kategori/{category}', [CategoryController::class, 'update'])->name('api.categories.update');
            Route::patch('/kategori/{category}/status', [CategoryController::class, 'toggleStatus'])->name('api.categories.toggle');
            Route::delete('/kategori/{category}', [CategoryController::class, 'destroy'])->name('api.categories.destroy');

            Route::get('/menu', [MenuItemController::class, 'index'])->name('api.menu-items.index');
            Route::post('/menu', [MenuItemController::class, 'store'])->name('api.menu-items.store');
            Route::patch('/menu/{menuItem}', [MenuItemController::class, 'update'])->name('api.menu-items.update');
            Route::patch('/menu/{menuItem}/ketersediaan', [MenuItemController::class, 'toggleAvailability'])->name('api.menu-items.availability');
            Route::delete('/menu/{menuItem}', [MenuItemController::class, 'destroy'])->name('api.menu-items.destroy');

            Route::get('/kelola-meja', [TableController::class, 'index'])->name('api.tables.index');
            Route::post('/kelola-meja', [TableController::class, 'store'])->name('api.tables.store');
            Route::patch('/kelola-meja/{table}', [TableController::class, 'update'])->name('api.tables.update');
            Route::patch('/kelola-meja/{table}/status', [TableController::class, 'toggleStatus'])->name('api.tables.toggle');
            Route::delete('/kelola-meja/{table}', [TableController::class, 'destroy'])->name('api.tables.destroy');
            Route::post('/meja/{table}/qr-ulang', [TableController::class, 'rotateQr'])->name('api.tables.qr.rotate');
            Route::get('/meja/qr', [TableController::class, 'qr'])->name('api.tables.qr');
            Route::get('/meja/qr-cetak', [TableController::class, 'qrPrint'])->name('api.tables.qr.print');

            Route::get('/voucher', [VoucherController::class, 'index'])->name('api.vouchers.index');
            Route::post('/voucher', [VoucherController::class, 'store'])->name('api.vouchers.store');
            Route::patch('/voucher/{voucher}/status', [VoucherController::class, 'toggle'])->name('api.vouchers.toggle');

            Route::post('/sesi-kasir/{session}/verifikasi-selisih', [CashierSessionController::class, 'verify'])
                ->name('api.cashier.session.verify');

            // Branding website — JSON API untuk pengaturan identitas kafe.
            Route::get('/pengaturan/branding', [SiteBrandingController::class, 'edit'])->name('api.settings.branding.show');
            Route::put('/pengaturan/branding', [SiteBrandingController::class, 'update'])->name('api.settings.branding.update');

            // Blog — CRUD artikel oleh owner/manager.
            Route::get('/kelola-blog', [PostController::class, 'index'])->name('api.posts.index');
            Route::post('/kelola-blog', [PostController::class, 'store'])->name('api.posts.store');
            Route::get('/kelola-blog/{post}', [PostController::class, 'show'])->name('api.posts.show');
            Route::patch('/kelola-blog/{post}', [PostController::class, 'update'])->name('api.posts.update');
            Route::post('/kelola-blog/{post}/terbitkan', [PostController::class, 'publish'])->name('api.posts.publish');
            Route::post('/kelola-blog/{post}/draf', [PostController::class, 'draft'])->name('api.posts.draft');
            Route::delete('/kelola-blog/{post}', [PostController::class, 'destroy'])->name('api.posts.destroy');
            Route::post('/kelola-blog/media', [PostMediaController::class, 'store'])->name('api.posts.media');

            // Analitik web — owner/manager.
            Route::get('/analitik-web', [AnalyticsController::class, 'index'])->name('api.analytics.index');
        });
    });

    // Blog publik — tanpa guard.
    Route::get('/publik/blog', [PublicBlogController::class, 'index'])->name('api.public.blog.index');
    Route::get('/publik/blog/{slug}', [PublicBlogController::class, 'show'])->name('api.public.blog.show');

    // Halaman publik website — tanpa guard.
    Route::get('/publik/beranda', [PublicSiteController::class, 'home'])->name('api.public.site.home');
    Route::get('/publik/menu', [PublicSiteController::class, 'menu'])->name('api.public.site.menu');
    Route::post('/publik/kontak', [PublicContactController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('api.public.contact.store');

    // Analitik kunjungan halaman publik (beacon). Throttle 30/menit per IP.
    Route::post('/publik/analitik', [PublicAnalyticsController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('api.public.analytics.store');

    // Pelanggan: guard `customer`
    Route::middleware('auth:customer')->group(function () {
        Route::get('/akun', [CustomerAccountController::class, 'show'])->name('api.customer.home');
        Route::get('/akun/saldo', [CustomerWalletController::class, 'show'])->name('api.customer.wallet.show');
        Route::get('/akun/pesanan/{order}', [CustomerOrderController::class, 'show'])->name('api.customer.order.show');
        Route::post('/akun/saldo/topup', [CustomerWalletController::class, 'topUp'])
            ->middleware('throttle:20,1')->name('api.customer.wallet.topup');

        // Inisiasi pembayaran Snap (R8.1–R8.6): ownership-scoped, idempotent.
        Route::post('/orders/{order}/pay', [PaymentInitiationController::class, 'order'])
            ->middleware('throttle:30,1')->name('api.orders.pay.initiate');
        Route::post('/wallet-topups/{topUp}/pay', [PaymentInitiationController::class, 'topUp'])
            ->middleware('throttle:30,1')->name('api.wallet-topups.pay.initiate');

        Route::middleware('customer.verified')->group(function () {
            Route::get('/meja', [CustomerOrderingController::class, 'menu'])->name('api.customer.order.menu');
            Route::post('/meja/pilih', [CustomerOrderingController::class, 'selectTable'])->name('api.customer.table.select');
            Route::post('/meja/keranjang', [CustomerOrderingController::class, 'addToCart'])->name('api.customer.cart.add');
            Route::patch('/meja/keranjang', [CustomerOrderingController::class, 'updateCart'])->name('api.customer.cart.update');
            Route::delete('/meja/keranjang', [CustomerOrderingController::class, 'clearCart'])->name('api.customer.cart.clear');
            Route::post('/meja/pesan', [CustomerOrderingController::class, 'checkout'])->middleware('throttle:30,1')->name('api.customer.checkout');
            Route::post('/meja/pesanan/{order}/bayar', [CustomerOrderingController::class, 'pay'])->middleware('throttle:30,1')->name('customer.order.pay.store');
        });
    });
});
