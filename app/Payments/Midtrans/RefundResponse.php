<?php
namespace App\Payments\Midtrans;
readonly class RefundResponse extends ProviderResponse
{
    public function __construct(
        bool $successful,
        public ?string $providerRefundId = null,
        ?GatewayResultToken $errorType = null,
        int $httpStatus = 200,
        array $payload = [],
        ?string $message = null,
    ) {
        parent::__construct($successful, $errorType, $httpStatus, $payload, $message);
    }
}