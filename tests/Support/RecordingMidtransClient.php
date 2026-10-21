<?php

namespace Tests\Support;

use App\Payments\Midtrans\GatewayStatusResponse;
use App\Payments\Midtrans\MidtransClient;
use App\Payments\Midtrans\RefundRequest;
use App\Payments\Midtrans\RefundResponse;
use App\Payments\Midtrans\SnapRequest;
use App\Payments\Midtrans\SnapTokenResponse;
use Illuminate\Support\Facades\DB;

/**
 * Recording test double for the initiation flow: counts createSnapTransaction
 * calls and returns either a configured success token or a provider failure.
 */
final class RecordingMidtransClient implements MidtransClient
{
    /** @var list<SnapRequest> */
    public array $snapRequests = [];

    /**
     * DB transaction depth observed at the moment the provider call was made.
     * The initiation flow must call the provider OUTSIDE any DB transaction.
     */
    public ?int $transactionLevelAtCall = null;

    public function __construct(
        private bool $successful = true,
        private ?string $token = 'snap-tok-test',
        private ?\App\Enums\GatewayResultToken $error = null,
    ) {}

    public function createSnapTransaction(SnapRequest $request): SnapTokenResponse
    {
        $this->snapRequests[] = $request;
        $this->transactionLevelAtCall = DB::transactionLevel();

        if ($this->successful) {
            return new SnapTokenResponse(
                true,
                $this->token,
                'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.$this->token,
                null,
                200,
                ['token' => $this->token, 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.$this->token],
            );
        }

        return new SnapTokenResponse(false, null, null, $this->error, 500, [], 'provider error');
    }

    public function fetchTransactionStatus(string $orderId): GatewayStatusResponse
    {
        return new GatewayStatusResponse(false, $orderId, null, null, \App\Enums\GatewayResultToken::ProviderUnavailable, 500);
    }

    public function directRefund(RefundRequest $request): RefundResponse
    {
        return new RefundResponse(false, null, \App\Enums\GatewayResultToken::ProviderUnavailable, 500);
    }
}
