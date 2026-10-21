<?php

namespace Tests\Support;

use App\Payments\Midtrans\GatewayStatusResponse;
use App\Payments\Midtrans\MidtransClient;
use App\Payments\Midtrans\RefundRequest;
use App\Payments\Midtrans\RefundResponse;
use App\Payments\Midtrans\SnapRequest;
use App\Payments\Midtrans\SnapTokenResponse;

/**
 * Deterministic fake driven by fixture files keyed by order_id + call sequence.
 * Fixtures live in tests/Fixtures/Payments/provider-responses/{order_id}/{n}.json.
 * A fixture's `__response` key selects the result kind; remaining keys are the payload.
 */
final class FakeMidtransClient implements MidtransClient
{
    /** @var array<string, int> */
    private array $sequence = [];

    public function __construct(
        private readonly ?string $fixtureBase = null,
    ) {}

    public function fixtureBasePath(): string
    {
        return $this->fixtureBase ?? base_path('tests/Fixtures/Payments/provider-responses');
    }

    public function createSnapTransaction(SnapRequest $request): SnapTokenResponse
    {
        $fixture = $this->nextFixture($request->midtransOrderId);
        $kind = $fixture['__response'] ?? 'success';
        $payload = $fixture['payload'] ?? $fixture;

        if ($kind === 'success') {
            return new SnapTokenResponse(
                true,
                $payload['token'] ?? 'fake-'.$request->midtransOrderId,
                $payload['redirect_url'] ?? null,
                null,
                200,
                $payload,
            );
        }

        return new SnapTokenResponse(
            false,
            null,
            null,
            self::tokenFor($kind),
            $payload['http_status'] ?? 400,
            $payload,
            $payload['message'] ?? null,
        );
    }

    public function fetchTransactionStatus(string $orderId): GatewayStatusResponse
    {
        $fixture = $this->nextFixture($orderId);
        $kind = $fixture['__response'] ?? 'success';
        $payload = $fixture['payload'] ?? $fixture;

        if ($kind === 'success') {
            return new GatewayStatusResponse(
                true,
                $payload['order_id'] ?? $orderId,
                $payload['transaction_status'] ?? 'pending',
                $payload['fraud_status'] ?? null,
                null,
                200,
                $payload,
            );
        }

        return new GatewayStatusResponse(
            false,
            $orderId,
            null,
            null,
            self::tokenFor($kind),
            $payload['http_status'] ?? 400,
            $payload,
            $payload['message'] ?? null,
        );
    }

    public function directRefund(RefundRequest $request): RefundResponse
    {
        $fixture = $this->nextFixture($request->midtransOrderId);
        $kind = $fixture['__response'] ?? 'success';
        $payload = $fixture['payload'] ?? $fixture;

        if ($kind === 'success') {
            return new RefundResponse(
                true,
                $payload['refund_key'] ?? $request->providerRefundId,
                null,
                200,
                $payload,
            );
        }

        return new RefundResponse(
            false,
            null,
            self::tokenFor($kind),
            $payload['http_status'] ?? 400,
            $payload,
            $payload['message'] ?? null,
        );
    }

    private static function tokenFor(string $kind): \App\Enums\GatewayResultToken
    {
        return match ($kind) {
            'timeout' => \App\Enums\GatewayResultToken::ProviderTimeout,
            'rejected' => \App\Enums\GatewayResultToken::ProviderRejected,
            'unavailable' => \App\Enums\GatewayResultToken::ProviderUnavailable,
            'malformed' => \App\Enums\GatewayResultToken::ProviderMalformed,
            default => \App\Enums\GatewayResultToken::ProviderRejected,
        };
    }

    /** @return array<string, mixed> */
    private function nextFixture(string $orderId): array
    {
        $index = $this->sequence[$orderId] = ($this->sequence[$orderId] ?? 0) + 1;
        $path = $this->fixtureBasePath().DIRECTORY_SEPARATOR.$orderId.DIRECTORY_SEPARATOR.$index.'.json';

        if (! is_file($path)) {
            // Fall back to a 1-indexed single fixture, then to a deterministic success.
            $fallback = $this->fixtureBasePath().DIRECTORY_SEPARATOR.$orderId.'.json';

            if ($index === 1 && is_file($fallback)) {
                $path = $fallback;
            } else {
                return ['__response' => 'success'];
            }
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : ['__response' => 'success'];
    }
}
