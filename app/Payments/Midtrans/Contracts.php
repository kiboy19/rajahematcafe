<?php

namespace App\Payments\Midtrans;

use App\Enums\GatewayResultToken;

interface MidtransClient
{
    public function createSnapTransaction(SnapRequest $request): SnapTokenResponse;

    public function fetchTransactionStatus(string $orderId): GatewayStatusResponse;

    public function directRefund(RefundRequest $request): RefundResponse;
}

final readonly class SnapRequest
{
    public function __construct(
        public string $midtransOrderId,
        public int $grossAmount,
        public array $payload = [],
        public string $idempotencyKey = '',
    ) {}
}

final readonly class RefundRequest
{
    public function __construct(
        public string $midtransOrderId,
        public string $providerRefundId,
        public int $amount,
        public string $idempotencyKey = '',
    ) {}
}

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

final readonly class SnapTokenResponse extends ProviderResponse
{
    public function __construct(
        bool $successful,
        public ?string $token = null,
        public ?string $redirectUrl = null,
        ?GatewayResultToken $errorType = null,
        int $httpStatus = 200,
        array $payload = [],
        ?string $message = null,
    ) {
        parent::__construct($successful, $errorType, $httpStatus, $payload, $message);
    }
}

final readonly class GatewayStatusResponse extends ProviderResponse
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

final readonly class RefundResponse extends ProviderResponse
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
