<?php

namespace App\Payments\Midtrans;

use App\Enums\GatewayResultToken;

readonly class GatewayStatusResponse extends ProviderResponse
{
    public function __construct(
        bool $successful,
        public string $orderId = '',
        public ?string $transactionStatus = null,
        public ?string $fraudStatus = null,
        ?GatewayResultToken $errorType = null,
        int $httpStatus = 200,
        array $payload = [],
        ?string $message = null,
    ) {
        parent::__construct($successful, $errorType, $httpStatus, $payload, $message);
    }
}
