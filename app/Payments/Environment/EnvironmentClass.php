<?php

namespace App\Payments\Environment;

/**
 * Satu-satunya gerbang environment untuk kode pembayaran.
 *
 * - Tier environment dari APP_ENV (bukan dari config aplikasi yang bisa di-cache).
 * - is_production HANYA dari `pos.midtrans.is_production`, tidak pernah dari APP_ENV.
 * - Kombinasi ambigu (production + sandbox, test-runner + production, APP_ENV tak dikenal,
 *   config tidak valid) selalu fail-closed: tidak ada mock, pembuatan pembayaran 503, webhook 503.
 */
final class EnvironmentClass
{
    public const TIER_LOCAL = 'local';

    public const TIER_TESTING = 'testing';

    public const TIER_STAGING = 'staging';

    public const TIER_PRODUCTION = 'production';

    public const TIER_UNKNOWN = 'unknown';

    /** Rentang valid tiap kunci integer: [min, max]. */
    private const INT_RANGES = [
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

    private const BOOL_KEYS = [
        'enabled',
        'is_production',
        'is_sandbox',
        'paused',
        'new_stack_enabled',
        'shadow_mode_enabled',
        'legacy_fallback_enabled',
    ];

    public static function rawEnv(string $key): ?string
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== null) {
            return (string) $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== null) {
            return (string) $_SERVER[$key];
        }

        $val = getenv($key);

        return $val !== false ? (string) $val : null;
    }

    public static function tier(): string
    {
        $raw = self::rawEnv('APP_ENV');
        $env = strtolower(trim($raw ?? ''));

        return match ($env) {
            'local' => self::TIER_LOCAL,
            'testing' => self::TIER_TESTING,
            'staging' => self::TIER_STAGING,
            'production', 'prod' => self::TIER_PRODUCTION,
            default => self::TIER_UNKNOWN,
        };
    }

    /** @return list<string> */
    public static function testRunnerFlags(): array
    {
        $flags = [];

        foreach (['PHPUNIT_RUNNING', 'PEST_RUNNING', 'CI'] as $name) {
            $value = self::rawEnv($name);
            if ($value !== null && $value !== '' && $value !== '0' && strtolower($value) !== 'false') {
                $flags[] = $name;
            }
        }

        return $flags;
    }

    public static function isTestRunner(): bool
    {
        return self::testRunnerFlags() !== [];
    }

    public static function isProduction(): bool
    {
        return self::bool('is_production');
    }

    public static function isSandbox(): bool
    {
        return self::bool('is_sandbox');
    }

    public static function isEnabled(): bool
    {
        return self::bool('enabled');
    }

    public static function isPaused(): bool
    {
        $file = storage_path('app/midtrans-paused');

        if (file_exists($file)) {
            return true;
        }

        return self::bool('paused');
    }

    public static function serverKey(): string
    {
        $key = self::config()['server_key'] ?? null;

        return is_string($key) ? trim($key) : '';
    }

    /** @return list<string> */
    public static function serverKeyRotation(): array
    {
        $keys = self::config()['server_key_rotation'] ?? [];

        if (! is_array($keys)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($key): string => is_string($key) ? trim($key) : '',
            $keys,
        ), static fn (string $key): bool => $key !== ''));
    }

    /** Kunci yang diterima saat verifikasi: kunci aktif + kunci pensiun (rotation). */
    public static function verificationKeys(): array
    {
        return array_values(array_unique(array_filter([...self::serverKeyRotation(), self::serverKey()])));
    }

    public static function payloadKey(): string
    {
        $key = self::config()['payload_key'] ?? null;

        return is_string($key) ? trim($key) : '';
    }

    /**
     * Kesalahan konfigurasi `pos.midtrans.*` (tipe salah, di luar rentang, kunci wajib kosong).
     *
     * @return array<string, string> nama kunci => alasan
     */
    public static function configErrors(): array
    {
        $errors = [];
        $config = self::config();

        foreach (self::BOOL_KEYS as $key) {
            if (! array_key_exists($key, $config) || ! is_bool($config[$key])) {
                $errors[$key] = 'harus boolean';
            }
        }

        foreach (self::INT_RANGES as $key => [$min, $max]) {
            $value = $config[$key] ?? null;

            if (! is_int($value)) {
                $errors[$key] = 'harus integer';
            } elseif ($value < $min || $value > $max) {
                $errors[$key] = "harus di antara {$min} dan {$max}";
            }
        }

        $cap = $config['overpayment_cap_percent'] ?? null;
        if (! is_float($cap) && ! is_int($cap)) {
            $errors['overpayment_cap_percent'] = 'harus angka';
        } elseif ($cap < 0.0 || $cap > 0.10) {
            $errors['overpayment_cap_percent'] = 'harus di antara 0.0 dan 0.10';
        }

        if (! is_array($config['server_key_rotation'] ?? null)) {
            $errors['server_key_rotation'] = 'harus array';
        }

        if (self::isProduction()) {
            if (! is_string($config['server_key'] ?? null) || trim($config['server_key']) === '') {
                $errors['server_key'] = 'wajib diisi saat is_production=true';
            }

            if (! is_string($config['payload_key'] ?? null) || trim($config['payload_key']) === '') {
                $errors['payload_key'] = 'wajib diisi saat is_production=true';
            }
        }

        return $errors;
    }

    /**
     * Alasan fail-closed; array kosong berarti jalur pembayaran boleh aktif secara normal.
     *
     * @return list<string>
     */
    public static function failClosedReasons(): array
    {
        $reasons = [];

        if (self::tier() === self::TIER_UNKNOWN) {
            $reasons[] = 'unknown_app_env';
        }

        if (self::isProduction() && self::isSandbox()) {
            $reasons[] = 'production_and_sandbox_conflict';
        }

        if (self::isProduction() && self::isTestRunner()) {
            $reasons[] = 'test_runner_in_production';
        }

        if (self::isProduction() && ! self::isEnabled()) {
            $reasons[] = 'midtrans_disabled_in_production';
        }

        if ($errors = self::configErrors()) {
            $reasons[] = 'config_error:'.implode(',', array_keys($errors));
        }

        return $reasons;
    }

    public static function isFailClosed(): bool
    {
        return self::failClosedReasons() !== [];
    }

    /** Mock/simulasi lokal hanya boleh di tier local/testing, non-produksi, tanpa test-runner saat produksi. */
    public static function allowsMock(): bool
    {
        if (in_array(self::tier(), [self::TIER_LOCAL, self::TIER_TESTING], true) === false) {
            return false;
        }

        return ! self::isProduction() && self::failClosedReasons() === [];
    }

    /** @return array<string, mixed> */
    private static function config(): array
    {
        $config = config('pos.midtrans');

        return is_array($config) ? $config : [];
    }

    private static function bool(string $key): bool
    {
        return (self::config()[$key] ?? false) === true;
    }
}
