<?php
namespace App\Payments\Midtrans;
interface MidtransClient
{
    public function createSnapTransaction(SnapRequest $request): SnapTokenResponse;
    public function fetchTransactionStatus(string $orderId): GatewayStatusResponse;
    public function directRefund(RefundRequest $request): RefundResponse;
}
