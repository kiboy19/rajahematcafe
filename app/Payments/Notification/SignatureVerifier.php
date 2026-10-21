<?php

namespace App\Payments\Notification;

use RuntimeException;

final class SignatureVerifier
{
    public function verify(string $orderId, string $statusCode, string $grossAmount, string $signatureKey, string $serverKey): bool
    {
        $expected = $this->computeSignature($orderId, $statusCode, $grossAmount, $serverKey);
        return hash_equals($expected, $signatureKey);
    }

    public function computeSignature(string $orderId, string $statusCode, string $grossAmount, string $serverKey): string
    {
        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $serverKey === '') {
            throw new RuntimeException('Signature verification requires non-empty order_id, status_code, gross_amount, and server_key.');
        }

        $data = $orderId . '|' . $statusCode . '|' . $grossAmount . '|' . $serverKey;
        return hash('sha512', $data);
    }
}