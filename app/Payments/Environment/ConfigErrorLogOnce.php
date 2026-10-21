<?php

namespace App\Payments\Environment;

use Illuminate\Support\Facades\Log;

final class ConfigErrorLogOnce
{
    /** @var array<string, true> */
    private static array $emitted = [];

    /** @param array<string, string> $errors */
    public static function emit(array $errors): void
    {
        $fingerprint = hash('sha256', serialize($errors));

        if (isset(self::$emitted[$fingerprint])) {
            return;
        }

        self::$emitted[$fingerprint] = true;
        Log::error('Midtrans configuration error; payment gateway fail-closed.', [
            'fingerprint' => $fingerprint,
            'keys' => array_keys($errors),
        ]);
    }

    public static function reset(): void
    {
        self::$emitted = [];
    }
}
