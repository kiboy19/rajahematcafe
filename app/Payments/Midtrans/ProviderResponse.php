<?php

namespace App\Payments\Midtrans;

use App\Enums\GatewayResultToken;

readonly class ProviderResponse
{
    public function __construct(
        public bool $successful,
        public ?GatewayResultToken $errorType = null,
        public int $httpStatus = 200,
        public array $payload = [],
        public ?string $message = null,
    ) {}
}
