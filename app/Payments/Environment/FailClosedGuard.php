<?php

namespace App\Payments\Environment;

use App\Models\AuditLog;

/**
 * Gerbang fail-closed untuk jalur pembayaran Midtrans.
 *
 * Produksi dengan `midtrans.enabled=false` atau config tidak valid:
 * pembuatan pembayaran → 503 `internal_error`, webhook → 503 `provider_unavailable`,
 * route simulasi/mock → 404. Error config dicatat sekali per logger (ConfigErrorLogOnce)
 * dan sebagai audit `config_error`.
 */
final class FailClosedGuard
{
    /** @return array{error: string, message: string} */
    public static function paymentCreationEnvelope(): array
    {
        self::report();

        return [
            'error' => 'internal_error',
            'message' => 'Payment gateway tidak tersedia.',
        ];
    }

    /** @return array{error: string, message: string} */
    public static function webhookEnvelope(): array
    {
        self::report();

        return [
            'error' => 'provider_unavailable',
            'message' => 'Payment provider tidak tersedia.',
        ];
    }

    public static function blocksPaymentCreation(): bool
    {
        return EnvironmentClass::isFailClosed() || EnvironmentClass::isPaused();
    }

    public static function blocksWebhook(): bool
    {
        return self::blocksPaymentCreation();
    }

    public static function blocksMockRoutes(): bool
    {
        return EnvironmentClass::isFailClosed() || EnvironmentClass::isPaused();
    }

    public static function allowsMockRoutes(): bool
    {
        return ! self::blocksMockRoutes() && EnvironmentClass::allowsMock();
    }

    private static function report(): void
    {
        if (EnvironmentClass::configErrors() === []) {
            return;
        }

        ConfigErrorLogOnce::emit(EnvironmentClass::configErrors());
        AuditLog::record('config_error', after: ['keys' => array_keys(EnvironmentClass::configErrors())]);
    }
}
