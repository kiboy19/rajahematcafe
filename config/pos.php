<?php

return [
    /*
     * Tarif default pembentukan tagihan. Angka final harus disahkan owner (Gate 0 / Lampiran C);
     * nilai di sini adalah default sementara agar POS dapat diuji.
     */
    'tax_percent' => (int) env('POS_TAX_PERCENT', 10),
    'service_percent' => (int) env('POS_SERVICE_PERCENT', 5),

    // Diskon maksimum (%) yang boleh diterapkan kasir tanpa approval manager.
    'max_cashier_discount_percent' => (int) env('POS_MAX_CASHIER_DISCOUNT', 10),

    // Pendaftaran pelanggan baru (default terbuka).
    'customer_registration_open' => (bool) env('POS_CUSTOMER_REGISTRATION_OPEN', true),

    // Menit cache payload halaman publik (landing/menu) agar hemat query.
    'public_cache_minutes' => (int) env('POS_PUBLIC_CACHE_MINUTES', 5),
    /*
     * Saldo pelanggan (R1.1). Top-up hanya tunai di kasir; saldo tidak dapat ditarik kembali
     * menjadi uang tunai (kebijakan default, menunggu keputusan owner di Gate 0).
     */
    'wallet_daily_topup_limit' => (int) env('POS_WALLET_DAILY_TOPUP_LIMIT', 2000000),
    'wallet_min_topup' => (int) env('POS_WALLET_MIN_TOPUP', 10000),

    /*
     * Verifikasi email pelanggan (R1.1). Selama false, pelanggan tetap dapat memesan tanpa
     * verifikasi — supaya pembukaan kafe tidak tertahan pengaturan SMTP. Aktifkan setelah
     * konfigurasi email hosting lolos Gate 0, lalu pastikan tautan verifikasi benar-benar masuk.
     */
    'require_customer_email_verification' => (bool) env('POS_REQUIRE_CUSTOMER_EMAIL_VERIFICATION', false),

    /*
     * Payment Gateway Midtrans (R1.2).
     * Server key hanya disimpan di server, jangan pernah terekspos ke frontend atau log.
     */
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY', ''),
        'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID', ''),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'enabled' => (bool) env('MIDTRANS_ENABLED', false),
    ],

    'currency' => 'IDR',
    'timezone' => 'Asia/Jakarta',
];
