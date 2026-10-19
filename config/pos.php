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

    // Pendaftaran pelanggan baru ditutup sampai teks legal/privacy disetujui (Gate 0).
    'customer_registration_open' => (bool) env('POS_CUSTOMER_REGISTRATION_OPEN', false),

    'currency' => 'IDR',
    'timezone' => 'Asia/Jakarta',
];
