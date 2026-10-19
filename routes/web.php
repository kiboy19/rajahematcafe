<?php

use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CashierSessionApprovalController;
use App\Http\Controllers\CashierSessionController;
use App\Http\Controllers\KdsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentVoidController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [StaffLoginController::class, 'create'])->name('login');
    Route::post('/login', [StaffLoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'staff.active'])->group(function () {
    Route::post('/logout', [StaffLoginController::class, 'destroy'])->name('logout');

    Route::middleware('role:owner,manager,kasir')->group(function () {
        Route::view('/dashboard', 'dashboard')->name('dashboard');

        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/keranjang', [CartController::class, 'store'])->name('pos.cart.store');
        Route::patch('/pos/keranjang', [CartController::class, 'update'])->name('pos.cart.update');
        Route::delete('/pos/keranjang', [CartController::class, 'destroy'])->name('pos.cart.destroy');
        Route::post('/pos/checkout', [CartController::class, 'checkout'])->name('pos.checkout');

        Route::get('/sesi-kasir', [CashierSessionController::class, 'show'])->name('cashier.session');
        Route::post('/sesi-kasir/buka', [CashierSessionController::class, 'open'])->name('cashier.session.open');
        Route::post('/sesi-kasir/tutup', [CashierSessionController::class, 'close'])->name('cashier.session.close');

        Route::get('/pesanan', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/pesanan/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('/pesanan/{order}/struk', [OrderController::class, 'receipt'])->name('orders.receipt');
        Route::post('/pesanan/{order}/bayar', [PaymentController::class, 'store'])->name('orders.pay');
        Route::delete('/pesanan/{order}/bayar/{payment}', [PaymentVoidController::class, 'store'])
            ->middleware('role:owner,manager')->name('orders.pay.void');
    });

    Route::middleware('role:owner,manager')->group(function () {
        Route::get('/laporan/harian', [ReportController::class, 'daily'])->name('reports.daily');
        Route::post('/sesi-kasir/{session}/verifikasi-selisih', [CashierSessionApprovalController::class, 'update'])
            ->name('cashier.session.verify');
    });

    Route::middleware('role:owner,manager,dapur')->group(function () {
        Route::get('/kds', [KdsController::class, 'index'])->name('kds.index');
        Route::patch('/kds/{order}', [KdsController::class, 'updateStatus'])->name('kds.status');
    });
});
