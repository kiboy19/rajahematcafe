<?php

namespace App\Payments\Notification;

use App\Payments\Support\MoneyCalculator;
use InvalidArgumentException;

final class AmountNormalizer
{
    public function __construct(private readonly MoneyCalculator $money = new MoneyCalculator)
    {
    }

    /**
     * Normalize a provider-declared amount into an IDR integer string.
     *
     * Rejects: fractional digits beyond two, leading sign, exponent, non-digit
     * characters, comma decimal separators, and any IDR amount whose fractional
     * part is not ".00".
     *
     * @return string integer amount (no decimal point)
     */
    public function normalize(string $raw, ?string $currency = null): string
    {
        $currency ??= 'IDR';
        if (strcasecmp($currency, 'IDR') !== 0) {
            throw new AmountNormalizationException('currency_mismatch');
        }

        $value = trim($raw);
        if ($value === '') {
            throw new AmountNormalizationException('amount_empty');
        }

        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new AmountNormalizationException('amount_malformed');
        }

        $parts = explode('.', $value, 2);
        $fraction = $parts[1] ?? '';
        if (strlen($fraction) > 2) {
            throw new AmountNormalizationException('amount_precision');
        }

        $padded = $parts[0] . '.' . str_pad($fraction, 2, '0');

        try {
            $formatted = $this->money->assertIdrZeroCents($padded);
        } catch (InvalidArgumentException $exception) {
            throw new AmountNormalizationException('amount_precision', previous: $exception);
        }

        return $formatted === '0.00' ? '0' : (string) (int) substr($formatted, 0, -3);
    }
}
