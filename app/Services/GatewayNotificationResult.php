<?php

namespace App\Services;

use App\Enums\GatewayResultToken;
use App\Models\GatewayTransaction;

/**
 * Hasil pemrosesan satu notifikasi gateway: efek bisnis opsional plus token
 * hasil yang memetakan ke status HTTP (lihat GatewayResultToken::httpStatus()).
 */
final class GatewayNotificationResult
{
    public function __construct(
        public readonly ?GatewayTransaction $transaction,
        public readonly GatewayResultToken $token,
    ) {}
}
