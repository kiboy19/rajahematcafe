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
     * Payment Gateway Midtrans (R1.2) — namespace tunggal `pos.midtrans.*`.
     *
     * Semua keputusan environment/mock/fail-closed WAJIB lewat App\Payments\Environment\EnvironmentClass;
     * jangan membaca `pos.midtrans.*` atau `app.env` langsung dari kode pembayaran.
     * Validasi tipe/rentang dilakukan EnvironmentClass::configErrors(); nilai di luar rentang atau
     * kunci wajib yang kosong membuat EnvironmentClass fail-closed (payment 503, webhook 503, mock 404).
     *
     * | key                                  | tipe   | rentang / wajib              | default |
     * |--------------------------------------|--------|------------------------------|---------|
     * | enabled                              | bool   | —                            | false   |
     * | is_production                        | bool   | —                            | false   |
     * | is_sandbox                           | bool   | —                            | false   |
     * | paused                               | bool   | —                            | false   |
     * | new_stack_enabled                    | bool   | —                            | false   |
     * | shadow_mode_enabled                  | bool   | —                            | false   |
     * | legacy_fallback_enabled              | bool   | —                            | false   |
     * | server_key                           | string | wajib bila is_production     | null    |
     * | server_key_rotation                  | array  | daftar kunci pensiun (verifikasi saja) | [] |
     * | payload_key                          | string | wajib bila is_production     | null    |
     * | webhook_timeout_seconds              | int    | 5–30                         | 10      |
     * | max_payload_bytes                    | int    | 1024–1048576                 | 65536   |
     * | settlement_retry_limit               | int    | 1–10                         | 3       |
     * | poll_threshold_seconds               | int    | 30–3600                      | 300     |
     * | poll_lock_timeout_seconds            | int    | 5–300                        | 30      |
     * | status_request_budget_ms             | int    | 50–5000                      | 800     |
     * | reconciliation_interval_minutes      | int    | 5–1440                       | 60      |
     * | reconcile_rate_per_minute            | int    | 1–60                         | 10      |
     * | buffer_ttl_minutes                   | int    | 5–10080                      | 1440    |
     * | buffer_lock_timeout_seconds          | int    | 10–1800                      | 300     |
     * | payload_retention_days               | int    | 1–2555                       | 730     |
     * | circuit_failure_threshold            | int    | 1–100                        | 5       |
     * | circuit_window_seconds               | int    | 10–300                       | 60      |
     * | circuit_half_open_probes             | int    | 1–10                         | 2       |
     * | poll_customer_rate_per_minute        | int    | 1–120                        | 30      |
     * | poll_global_rate_per_minute          | int    | 10–1000                      | 200     |
     * | overpayment_cap_percent              | float  | 0.0–0.10                     | 0.02    |
     *
     * Server key hanya disimpan di server, jangan pernah terekspos ke frontend atau log.
     */
    'midtrans' => [
        'enabled' => (bool) env('MIDTRANS_ENABLED', false),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'is_sandbox' => (bool) env('MIDTRANS_IS_SANDBOX', false),
        'paused' => (bool) env('MIDTRANS_PAUSED', false),
        'new_stack_enabled' => (bool) env('MIDTRANS_NEW_STACK_ENABLED', false),
        'shadow_mode_enabled' => (bool) env('MIDTRANS_SHADOW_MODE_ENABLED', false),
        'legacy_fallback_enabled' => (bool) env('MIDTRANS_LEGACY_FALLBACK_ENABLED', false),
        'server_key' => env('POS_MIDTRANS_SERVER_KEY'),
        'server_key_rotation' => array_values(array_filter(array_map('trim', explode(',', (string) env('POS_MIDTRANS_SERVER_KEY_ROTATION', ''))))),
        'payload_key' => env('POS_PAYLOAD_KEY'),
        'webhook_timeout_seconds' => (int) env('POS_MIDTRANS_WEBHOOK_TIMEOUT_SECONDS', 10),
        'max_payload_bytes' => (int) env('POS_MIDTRANS_MAX_PAYLOAD_BYTES', 65536),
        'settlement_retry_limit' => (int) env('POS_MIDTRANS_SETTLEMENT_RETRY_LIMIT', 3),
        'poll_threshold_seconds' => (int) env('POS_MIDTRANS_POLL_THRESHOLD_SECONDS', 300),
        'poll_lock_timeout_seconds' => (int) env('POS_MIDTRANS_POLL_LOCK_TIMEOUT_SECONDS', 30),
        'status_request_budget_ms' => (int) env('POS_MIDTRANS_STATUS_REQUEST_BUDGET_MS', 800),
        'reconciliation_interval_minutes' => (int) env('POS_MIDTRANS_RECONCILIATION_INTERVAL_MINUTES', 60),
        'reconcile_rate_per_minute' => (int) env('POS_MIDTRANS_RECONCILE_RATE_PER_MINUTE', 10),
        'buffer_ttl_minutes' => (int) env('POS_MIDTRANS_BUFFER_TTL_MINUTES', 1440),
        'buffer_lock_timeout_seconds' => (int) env('POS_MIDTRANS_BUFFER_LOCK_TIMEOUT_SECONDS', 300),
        'payload_retention_days' => (int) env('POS_MIDTRANS_PAYLOAD_RETENTION_DAYS', 730),
        'circuit_failure_threshold' => (int) env('POS_MIDTRANS_CIRCUIT_FAILURE_THRESHOLD', 5),
        'circuit_window_seconds' => (int) env('POS_MIDTRANS_CIRCUIT_WINDOW_SECONDS', 60),
        'circuit_half_open_probes' => (int) env('POS_MIDTRANS_CIRCUIT_HALF_OPEN_PROBES', 2),
        'poll_customer_rate_per_minute' => (int) env('POS_MIDTRANS_POLL_CUSTOMER_RATE_PER_MINUTE', 30),
        'poll_global_rate_per_minute' => (int) env('POS_MIDTRANS_POLL_GLOBAL_RATE_PER_MINUTE', 200),
        'overpayment_cap_percent' => (float) env('POS_MIDTRANS_OVERPAYMENT_CAP_PERCENT', 0.02),

        // @deprecated Kunci lama dipertahankan agar deployment yang ada tidak pecah; tidak dipakai untuk keputusan mock/fail-closed.
        'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
        // @deprecated lihat catatan di atas.
        'merchant_id' => env('MIDTRANS_MERCHANT_ID', ''),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'enabled' => (bool) env('MIDTRANS_ENABLED', false),
        // Batas waktu pre-commit webhook (detik). Melewati batas ⇒ rollback + 503.
        'webhook_timeout_seconds' => (int) env('MIDTRANS_WEBHOOK_TIMEOUT_SECONDS', 5),
    ],

    'currency' => 'IDR',
    'timezone' => 'Asia/Jakarta',
];
