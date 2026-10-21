<?php

/*
 * Environment matrix fixture (Rencana TASK-001 / SRS R1.1-R1.8, R10.1-R10.7).
 *
 * Setiap baris mencakup skenario {local, testing-console, testing-http,
 * production, staging, ci} × {enabled, disabled} × {is_production true/false}
 * beserta perilaku yang diharapkan.
 *
 * | kolom              | arti                                                        |
 * |--------------------|-------------------------------------------------------------|
 * | label              | nama skenario                                               |
 * | env                | peta raw environment yang dipasang untuk baris ini          |
 * | config             | override `pos.midtrans.*`                                   |
 * | tier               | tier yang diharapkan                                        |
 * | is_production      | is_production yang diharapkan (selalu dari config)          |
 * | fail_closed        | true = payment/webhook 503, mock 404                        |
 * | allows_mock        | true = route simulasi/mock tersedia                         |
 * | payment_status     | status HTTP pembuatan pembayaran                            |
 * | webhook_status     | status HTTP webhook                                         |
 * | mock_route_status  | status HTTP route simulasi                                  |
 *
 * Catatan: baris dengan `enabled=true` + `is_production=true` + server_key valid
 * akan memanggil API Midtrans nyata bila diuji via HTTP; pengujian HTTP untuk
 * kombinasi live tidak dilakukan (kolom `null` = tidak diuji via HTTP).
 */

$failingIntKeys = [
    'webhook_timeout_seconds' => [5, 30],
    'max_payload_bytes' => [1024, 1048576],
    'settlement_retry_limit' => [1, 10],
    'poll_threshold_seconds' => [30, 3600],
    'poll_lock_timeout_seconds' => [5, 300],
    'status_request_budget_ms' => [50, 5000],
    'reconciliation_interval_minutes' => [5, 1440],
    'reconcile_rate_per_minute' => [1, 60],
    'buffer_ttl_minutes' => [5, 10080],
    'buffer_lock_timeout_seconds' => [10, 1800],
    'payload_retention_days' => [1, 2555],
    'circuit_failure_threshold' => [1, 100],
    'circuit_window_seconds' => [10, 300],
    'circuit_half_open_probes' => [1, 10],
    'poll_customer_rate_per_minute' => [1, 120],
    'poll_global_rate_per_minute' => [10, 1000],
];

/** Kombinasi tier/skenario dasar: label => [APP_ENV, flag test-runner]. */
$scenarios = [
    'local' => ['local', []],
    'testing-console' => ['testing', ['PHPUNIT_RUNNING' => '1']],
    'testing-http' => ['testing', []],
    'staging' => ['staging', []],
    'production' => ['production', []],
    'ci' => ['testing', ['CI' => '1']],
];

$validConfig = static fn (): array => [
    'enabled' => null, // diisi per baris
    'is_production' => null, // diisi per baris
    'is_sandbox' => false,
    'server_key' => 'SB-Mid-server-validkey',
    'server_key_rotation' => [],
    'payload_key' => 'payload-key-valid',
    'webhook_timeout_seconds' => 10,
    'max_payload_bytes' => 65536,
    'settlement_retry_limit' => 3,
    'poll_threshold_seconds' => 300,
    'poll_lock_timeout_seconds' => 30,
    'status_request_budget_ms' => 800,
    'reconciliation_interval_minutes' => 60,
    'reconcile_rate_per_minute' => 10,
    'buffer_ttl_minutes' => 1440,
    'buffer_lock_timeout_seconds' => 300,
    'payload_retention_days' => 730,
    'circuit_failure_threshold' => 5,
    'circuit_window_seconds' => 60,
    'circuit_half_open_probes' => 2,
    'poll_customer_rate_per_minute' => 30,
    'poll_global_rate_per_minute' => 200,
    'overpayment_cap_percent' => 0.02,
];

$tierOf = static fn (string $label): string => match (true) {
    str_starts_with($label, 'local') => 'local',
    str_starts_with($label, 'testing') || str_starts_with($label, 'ci') => 'testing',
    str_starts_with($label, 'staging') => 'staging',
    str_starts_with($label, 'production') => 'production',
    default => 'unknown',
};

$matrix = [];
foreach ($scenarios as $label => [$appEnv, $flags]) {
    foreach ([true, false] as $isProduction) {
        foreach ([true, false] as $enabled) {
            $env = array_merge(['APP_ENV' => $appEnv], $flags);
            $tier = $tierOf($label);
            $runnerActive = $flags !== [];

            // Kontradiksi / ambigu -> fail-closed:
            // - production + disabled
            // - production + test-runner aktif
            $failClosed = $isProduction && (! $enabled || $runnerActive);

            $allowsMock = $failClosed === false
                && in_array($tier, ['local', 'testing'], true)
                && $isProduction === false;

            $config = array_merge($validConfig(), [
                'enabled' => $enabled,
                'is_production' => $isProduction,
            ]);

            // Baris live production (enabled + is_production) memanggil API nyata
            // bila lewat HTTP; tidak diuji penuh, cukup gate class.
            $paymentStatus = null;
            $webhookStatus = null;
            $mockRouteStatus = null;

            if ($failClosed) {
                $paymentStatus = 503;
                $webhookStatus = 503;
                $mockRouteStatus = 404;
            } elseif ($enabled === false) {
                // Non-produksi + disabled: local/testing masih mock, tier lain 503.
                $paymentStatus = $allowsMock ? 201 : 503;
                $webhookStatus = 503;
                $mockRouteStatus = $allowsMock ? 200 : 404;
            } elseif ($allowsMock) {
                // enabled + mock (local/testing non-produksi, tanpa server key wajib):
                // topup dibuat mode mock -> 201; webhook 200; mock route 200.
                $paymentStatus = 201;
                $webhookStatus = 200;
                $mockRouteStatus = 200;
            }

            $matrix[] = [
                'label' => "{$label}-".($enabled ? 'enabled' : 'disabled').'-'.($isProduction ? 'is-production' : 'not-production'),
                'env' => $env,
                'config' => $config,
                'tier' => $tier,
                'is_production' => $isProduction,
                'fail_closed' => $failClosed,
                'allows_mock' => $allowsMock,
                'payment_status' => $paymentStatus,
                'webhook_status' => $webhookStatus,
                'mock_route_status' => $mockRouteStatus,
            ];
        }
    }
}

/** Konfigurasi invalid/out-of-range: [key, nilai, apakah hanya relevan saat production]. */
$invalidRows = [];

foreach ($failingIntKeys as $key => [$min, $max]) {
    $invalidRows[] = [
        'label' => "{$key}-below-min",
        'config' => [$key => $min - 1],
        'expected_error_key' => $key,
        'production_only' => false,
    ];
    $invalidRows[] = [
        'label' => "{$key}-above-max",
        'config' => [$key => $max + 1],
        'expected_error_key' => $key,
        'production_only' => false,
    ];
    $invalidRows[] = [
        'label' => "{$key}-non-int",
        'config' => [$key => 'not-an-int'],
        'expected_error_key' => $key,
        'production_only' => false,
    ];
    $invalidRows[] = [
        'label' => "{$key}-negative",
        'config' => [$key => -1],
        'expected_error_key' => $key,
        'production_only' => false,
    ];
}

$invalidRows[] = [
    'label' => 'overpayment_cap_percent-below-zero',
    'config' => ['overpayment_cap_percent' => -0.1],
    'expected_error_key' => 'overpayment_cap_percent',
    'production_only' => false,
];
$invalidRows[] = [
    'label' => 'overpayment_cap_percent-above-cap',
    'config' => ['overpayment_cap_percent' => 0.5],
    'expected_error_key' => 'overpayment_cap_percent',
    'production_only' => false,
];
$invalidRows[] = [
    'label' => 'overpayment_cap_percent-string',
    'config' => ['overpayment_cap_percent' => 'bukan-angka'],
    'expected_error_key' => 'overpayment_cap_percent',
    'production_only' => false,
];
$invalidRows[] = [
    'label' => 'server_key_rotation-non-array',
    'config' => ['server_key_rotation' => 'bukan-array'],
    'expected_error_key' => 'server_key_rotation',
    'production_only' => false,
];
$invalidRows[] = [
    'label' => 'server_key-missing-in-production',
    'config' => ['server_key' => ''],
    'expected_error_key' => 'server_key',
    'production_only' => true,
];
$invalidRows[] = [
    'label' => 'payload_key-missing-in-production',
    'config' => ['payload_key' => ''],
    'expected_error_key' => 'payload_key',
    'production_only' => true,
];

return [
    'matrix' => $matrix,
    'invalid_config' => $invalidRows,
];
