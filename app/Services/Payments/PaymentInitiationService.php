<?php

namespace App\Services\Payments;

use App\Enums\GatewayResultToken;
use App\Enums\OrderStatus;
use App\Models\GatewayTransaction;
use App\Models\Order;
use App\Models\WalletTopUp;
use App\Payments\Environment\EnvironmentClass;
use App\Payments\Environment\FailClosedGuard;
use App\Payments\Midtrans\MidtransClient;
use App\Payments\Midtrans\SnapRequest;
use App\Payments\Midtrans\SnapTokenResponse;
use App\Services\MidtransGatewayService;
use App\Support\Clock;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Inisiasi pembayaran Snap (R8.1–R8.3) dengan batas commit yang ketat:
 *
 * Transaksi 1 (pre-provider): kunci baris entitas, validasi payable, buat
 * `gateway_transactions` `pending` + stamp `midtrans_order_id` pada entitas, COMMIT.
 * Tidak ada panggilan jaringan di dalam transaksi basis data mana pun.
 *
 * Transaksi 2 (post-provider): di luar DB, panggil MidtransClient::createSnapTransaction();
 * sukses → simpan `snap_token` pada entitas dan baris gateway; gagal → tandai baris
 * gateway `failed`. Nominal/orders tidak pernah berubah di luar kedua batas itu.
 */
class PaymentInitiationService
{
    public function __construct(
        protected MidtransClient $client,
        protected ?Clock $clock = null,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentInitiationException
     */
    public function initiateForOrder(Order $order): array
    {
        $initiated = $this->initiate(
            entity: $order,
            type: GatewayTransaction::TYPE_PAYMENT,
            internalType: GatewayTransaction::INTERNAL_TYPE_ORDER,
            internalId: (int) $order->id,
            customerId: (int) $order->customer_id,
            amount: (int) $order->total,
        );

        if ($initiated['duplicate']) {
            return $this->duplicateResponse($initiated['gateway'], $order);
        }

        return $this->afterProvider($initiated['gateway'], $order);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentInitiationException
     */
    public function initiateForTopUp(WalletTopUp $topUp): array
    {
        $initiated = $this->initiate(
            entity: $topUp,
            type: GatewayTransaction::TYPE_TOPUP,
            internalType: GatewayTransaction::INTERNAL_TYPE_WALLET_TOP_UP,
            internalId: (int) $topUp->id,
            customerId: (int) $topUp->customer_id,
            amount: (int) $topUp->gross_amount,
        );

        if ($initiated['duplicate']) {
            return $this->duplicateResponse($initiated['gateway'], $topUp);
        }

        return $this->afterProvider($initiated['gateway'], $topUp);
    }

    /**
     * Transaksi 1. Mengembalikan baris gateway yang BARU dibuat (duplicate=false),
     * atau baris yang sudah ada (duplicate=true) tanpa menyentuh jaringan.
     *
     * @param  Order|WalletTopUp  $entity
     * @return array{gateway: GatewayTransaction, duplicate: bool}
     */
    private function initiate(
        object $entity,
        string $type,
        string $internalType,
        int $internalId,
        int $customerId,
        int $amount,
    ): array {
        if (FailClosedGuard::blocksPaymentCreation()) {
            throw PaymentInitiationException::internalError();
        }

        /** @var array{gateway: GatewayTransaction, duplicate: bool} $result */
        $result = DB::transaction(function () use ($entity, $type, $internalType, $internalId, $customerId, $amount): array {
            /** @var Order|WalletTopUp $locked */
            $locked = $entity::query()->lockForUpdate()->findOrFail($entity->getKey());

            if ((string) ($locked->midtrans_order_id ?? '') !== '') {
                // duplicate_ignored: kembalikan pembayaran yang sudah ada.
                $existing = GatewayTransaction::query()
                    ->where('midtrans_order_id', $locked->midtrans_order_id)
                    ->orderByDesc('id')
                    ->first();

                if ($existing === null) {
                    throw PaymentInitiationException::internalError();
                }

                return ['gateway' => $existing, 'duplicate' => true];
            }

            $this->assertPayable($locked, $type);
            $this->assertConfigValid();

            $midtransOrderId = $this->generateMidtransOrderId($type, $internalId);
            $idempotencyKey = GatewayTransaction::idempotencyKey('midtrans', $midtransOrderId);

            $gatewayTransaction = GatewayTransaction::query()->create([
                'provider' => 'midtrans',
                'provider_reference' => $midtransOrderId,
                'idempotency_key' => $idempotencyKey,
                'type' => $type,
                'status' => GatewayTransaction::STATUS_PENDING,
                'gross_amount' => $amount,
                'fee_amount' => 0,
                'net_amount' => $amount,
                'customer_id' => $customerId,
                'order_id' => $type === GatewayTransaction::TYPE_PAYMENT ? $locked->id : null,
                'internal_type' => $internalType,
                'internal_id' => $internalId,
                'midtrans_order_id' => $midtransOrderId,
            ]);

            $locked->update(['midtrans_order_id' => $midtransOrderId]);
            $entity->setRawAttributes($locked->getAttributes(), true);

            return ['gateway' => $gatewayTransaction, 'duplicate' => false];
        });

        return $result;
    }

    /**
     * Batas provider + Transaksi 2. Jaringan dipanggil TANPA transaksi terbuka.
     *
     * @param  Order|WalletTopUp  $entity
     * @return array<string, mixed>
     */
    private function afterProvider(GatewayTransaction $gatewayTransaction, object $entity): array
    {
        $request = new SnapRequest(
            midtransOrderId: (string) $gatewayTransaction->midtrans_order_id,
            grossAmount: (int) $gatewayTransaction->gross_amount,
            payload: $this->snapPayload($gatewayTransaction, $entity),
            idempotencyKey: (string) $gatewayTransaction->idempotency_key,
        );

        try {
            $response = $this->client->createSnapTransaction($request);
        } catch (Throwable $exception) {
            report($exception);
            $response = new SnapTokenResponse(
                false,
                null,
                null,
                GatewayResultToken::ProviderUnavailable,
                0,
                [],
                $exception->getMessage(),
            );
        }

        return DB::transaction(function () use ($response, $gatewayTransaction, $entity): array {
            /** @var GatewayTransaction $fresh */
            $fresh = GatewayTransaction::query()->lockForUpdate()->findOrFail($gatewayTransaction->id);

            if (! $response->successful || $response->token === null || $response->token === '') {
                $fresh->update(['status' => GatewayTransaction::STATUS_FAILED]);

                throw PaymentInitiationException::providerUnavailable();
            }

            $fresh->update(['payload' => $response->payload ?: ['token' => $response->token]]);
            $entity::query()->whereKey($entity->getKey())->update(['snap_token' => $response->token]);

            return $this->successResponse($fresh, $response->token, $response->redirectUrl);
        });
    }

    private function assertPayable(Order|WalletTopUp $entity, string $type): void
    {
        if ($type === GatewayTransaction::TYPE_PAYMENT) {
            $statusValue = $entity->status instanceof OrderStatus
                ? $entity->status->value
                : (string) $entity->status;

            $payable = in_array($statusValue, ['pending', 'menunggu_pembayaran'], true);
        } else {
            $payable = (string) $entity->status === 'pending';
        }

        if (! $payable) {
            throw PaymentInitiationException::unprocessable('not_payable');
        }
    }

    private function assertConfigValid(): void
    {
        if (EnvironmentClass::configErrors() !== []) {
            throw PaymentInitiationException::internalError();
        }
    }

    private function generateMidtransOrderId(string $type, int $internalId): string
    {
        $prefix = $type === GatewayTransaction::TYPE_PAYMENT ? 'ORDER' : 'TOPUP';

        return sprintf('%s-%d-%d-%s', $prefix, $internalId, $this->clock()->nowMs(), bin2hex(random_bytes(4)));
    }

    /** @return array<string, mixed> */
    private function snapPayload(GatewayTransaction $gatewayTransaction, Order|WalletTopUp $entity): array
    {
        $details = [
            'transaction_details' => [
                'order_id' => $gatewayTransaction->midtrans_order_id,
                'gross_amount' => (int) $gatewayTransaction->gross_amount,
            ],
        ];

        $customer = $entity->customer;

        if ($customer !== null) {
            $details['customer_details'] = [
                'first_name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ];
        }

        if ($entity instanceof Order) {
            $itemDetails = MidtransGatewayService::orderItemDetails($entity, (int) $gatewayTransaction->gross_amount);

            if ($itemDetails !== null) {
                $details['item_details'] = $itemDetails;
            }

            $details['callbacks'] = [
                'finish' => url('/akun/pesanan/'.$entity->id),
                'unfinish' => url('/akun/pesanan/'.$entity->id.'/bayar'),
                'error' => url('/akun/pesanan/'.$entity->id.'/bayar?error=1'),
            ];
        } else {
            $details['callbacks'] = [
                'finish' => url('/akun?topup=finish'),
                'unfinish' => url('/akun/saldo'),
                'error' => url('/akun/saldo?error=1'),
            ];
        }

        return $details;
    }

    private function clock(): Clock
    {
        return $this->clock ??= app(Clock::class);
    }

    /** @return array<string, mixed> */
    private function successResponse(GatewayTransaction $gatewayTransaction, string $token, ?string $redirectUrl): array
    {
        return [
            'idempotency_key' => $gatewayTransaction->idempotency_key,
            'midtrans_order_id' => $gatewayTransaction->midtrans_order_id,
            'gross_amount' => (int) $gatewayTransaction->gross_amount,
            'snap_token' => $token,
            'redirect_url' => $redirectUrl,
        ];
    }

    /** @return array<string, mixed> */
    private function duplicateResponse(GatewayTransaction $gatewayTransaction, Order|WalletTopUp $entity): array
    {
        /** @var Order|WalletTopUp $fresh */
        $fresh = $entity::query()->findOrFail($entity->getKey());
        $token = (string) ($fresh->snap_token ?? '');

        if ($token === '') {
            // Sesuatu menghapus/mengunci stamp; periksa payload baris gateway.
            $token = (string) data_get($gatewayTransaction->payload, 'token', '');
        }

        if ($token === '') {
            throw PaymentInitiationException::conflict();
        }

        return [
            'idempotency_key' => $gatewayTransaction->idempotency_key,
            'midtrans_order_id' => $gatewayTransaction->midtrans_order_id,
            'gross_amount' => (int) $gatewayTransaction->gross_amount,
            'snap_token' => $token,
            'redirect_url' => (string) data_get($gatewayTransaction->payload, 'redirect_url', '') ?: null,
            'duplicate' => true,
        ];
    }
}
