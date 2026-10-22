<?php

namespace App\Services;

use App\Enums\GatewayResultToken;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\PreCommitTimeoutException;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\GatewayTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletTopUp;
use App\Payments\Context\PaymentContext;
use App\Payments\Environment\EnvironmentClass;
use App\Payments\Environment\FailClosedGuard;
use App\Payments\Environment\PaymentGatewayUnavailableException;
use App\Payments\Notification\NormalizedObservation;
use App\Support\Clock;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Service integrasi Payment Gateway Midtrans (R1.2).
 * Menangani pembuatan order charge, verifikasi signature SHA512, dan
 * pemrosesan webhook yang idempotent. Return dari browser tidak pernah
 * dijadikan bukti pembayaran lunas — hanya webhook/settlement terverifikasi.
 */
class MidtransGatewayService
{
    public function __construct(
        protected WalletService $wallet,
        protected AuditRecorder $auditRecorder,
        protected ?Clock $clock = null,
    ) {}

    private function guardPaymentCreation(): void
    {
        if (! FailClosedGuard::blocksPaymentCreation()) {
            return;
        }

        throw new PaymentGatewayUnavailableException;
    }

    private function serverKey(): string
    {
        return EnvironmentClass::serverKey();
    }

    /**
     * URL basis API Snap Midtrans (sandbox vs production).
     */
    public function clock(): Clock
    {
        return $this->clock ??= app(Clock::class);
    }

    public function setClock(Clock $clock): self
    {
        $this->clock = $clock;

        return $this;
    }

    public function snapBaseUrl(): string
    {
        return EnvironmentClass::isProduction()
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    /**
     * Buat transaksi Snap untuk top-up saldo mandiri pelanggan.
     * Mengembalikan data token snap dan ID transaksi unik.
     *
     * @return array{snap_token: string|null, redirect_url: string|null, order_id: string, mode: 'midtrans'|'mock'}
     *
     * @throws ValidationException
     */
    public function createCustomerTopUp(Customer $customer, int $amount): array
    {
        $this->guardPaymentCreation();

        $min = (int) config('pos.wallet_min_topup');
        if ($amount < $min) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal top-up minimal Rp'.number_format($min, 0, ',', '.').'.',
            ]);
        }

        $serverKey = $this->serverKey();
        $enabled = EnvironmentClass::isEnabled();
        $orderId = 'TOPUP-'.$customer->id.'-'.time().'-'.bin2hex(random_bytes(3));
        $idempotencyKey = GatewayTransaction::idempotencyKey('midtrans', $orderId.':pending:create');

        // Tidak ada mode simulasi: gateway belum aktif/terkonfigurasi => fail-closed (503).
        if (! $enabled || $serverKey === '') {
            throw new PaymentGatewayUnavailableException;
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
            'item_details' => [
                [
                    'id' => 'TOPUP-SALDO',
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => 'Top-up Saldo Rajahemat Cafe',
                ],
            ],
            'callbacks' => [
                'finish' => url('/akun?topup=finish'),
                'unfinish' => url('/akun/saldo'),
                'error' => url('/akun/saldo?error=1'),
            ],
        ];

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->timeout(10)
                ->acceptJson()
                ->post($this->snapBaseUrl(), $payload);

            if (! $response->successful()) {
                throw ValidationException::withMessages([
                    'amount' => 'Gagal menghubungi payment gateway Midtrans: '.($response->json('error_messages.0') ?? 'Kode HTTP '.$response->status()),
                ]);
            }

            $snapToken = (string) $response->json('token');
            $redirectUrl = (string) $response->json('redirect_url');

            $topUp = WalletTopUp::query()->create([
                'customer_id' => $customer->id,
                'midtrans_order_id' => $orderId,
                'gross_amount' => $amount,
                'settled_amount' => 0,
                'status' => 'pending',
            ]);

            GatewayTransaction::query()->create([
                'provider' => 'midtrans',
                'provider_reference' => $orderId,
                'idempotency_key' => $idempotencyKey,
                'type' => 'topup',
                'status' => 'pending',
                'gross_amount' => $amount,
                'fee_amount' => 0,
                'net_amount' => $amount,
                'payload' => $response->json(),
                'customer_id' => $customer->id,
                'internal_type' => 'wallet_top_up',
                'internal_id' => $topUp->id,
                'midtrans_order_id' => $orderId,
            ]);

            return [
                'snap_token' => $snapToken,
                'redirect_url' => $redirectUrl,
                'order_id' => $orderId,
                'mode' => 'midtrans',
            ];
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'amount' => 'Tidak dapat terhubung ke server Midtrans. Periksa koneksi internet Anda.',
            ]);
        }
    }

    /**
     * Simulasi pelunasan untuk mode lokal/testing tanpa harus memicu webhook asli.
     * Mendukung top-up wallet maupun pembayaran pesanan online.
     */
    public function simulateSettlement(Customer $customer, string $orderId): GatewayTransaction
    {
        abort_unless(FailClosedGuard::allowsMockRoutes(), 404);

        $gatewayTrx = GatewayTransaction::query()
            ->where('provider_reference', $orderId)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        if ($gatewayTrx->status === 'settlement') {
            return $gatewayTrx;
        }

        return DB::transaction(function () use ($customer, $gatewayTrx, $orderId): GatewayTransaction {
            if ($gatewayTrx->type === 'topup') {
                $walletTrx = $this->wallet->credit(
                    customer: $customer,
                    type: WalletTransactionType::TopUp,
                    amount: $gatewayTrx->gross_amount,
                    note: 'Top-up online (Simulasi: '.$orderId.')',
                );

                $gatewayTrx->update([
                    'status' => 'settlement',
                    'wallet_transaction_id' => $walletTrx->id,
                    'settled_at' => now(),
                ]);
            } else {
                $order = Order::query()->where('number', $orderId)->first();

                if ($order !== null) {
                    Payment::query()->updateOrCreate(
                        ['reference' => 'MIDTRANS-'.$orderId],
                        [
                            'order_id' => $order->id,
                            'method' => PaymentMethod::Qris,
                            'status' => PaymentStatus::Paid,
                            'amount' => $gatewayTrx->gross_amount,
                            'tendered' => $gatewayTrx->gross_amount,
                            'change' => 0,
                            'paid_at' => now(),
                        ],
                    );

                    $order->update([
                        'paid_at' => now(),
                        'status' => OrderStatus::Baru,
                    ]);
                }

                $gatewayTrx->update([
                    'status' => 'settlement',
                    'settled_at' => now(),
                ]);
            }

            AuditLog::record('gateway.settlement_simulated', $gatewayTrx, after: [
                'order_id' => $orderId,
                'gross_amount' => $gatewayTrx->gross_amount,
            ]);

            return $gatewayTrx;
        });
    }

    /**
     * Petakan `payment_type` Midtrans ke metode pembayaran internal.
     */
    private function paymentMethodFrom(string $paymentType): PaymentMethod
    {
        return match (true) {
            str_contains($paymentType, 'qris'),
            str_contains($paymentType, 'gopay'),
            str_contains($paymentType, 'shopeepay'),
            str_contains($paymentType, 'echannel') => PaymentMethod::Qris,
            str_contains($paymentType, 'bank_transfer'),
            str_contains($paymentType, 'cstore'),
            str_contains($paymentType, '_va') => PaymentMethod::VirtualAccount,
            str_contains($paymentType, 'credit_card') => PaymentMethod::Edc,
            default => PaymentMethod::Qris,
        };
    }

    /**
     * Buat pembayaran Snap untuk order pelanggan.
     *
     * @return array{snap_token: string|null, redirect_url: string|null, order_id: string, mode: 'midtrans'|'mock'}
     *
     * @throws ValidationException
     */
    public function createOrderPayment(Order $order, ?int $amount = null): array
    {
        $this->guardPaymentCreation();

        $serverKey = $this->serverKey();
        $enabled = EnvironmentClass::isEnabled();
        $orderId = $order->number;
        $grossAmount = $amount ?? (int) $order->total;
        $idempotencyKey = GatewayTransaction::idempotencyKey('midtrans', $orderId.':pending');

        // Tidak ada mode simulasi: gateway belum aktif/terkonfigurasi => fail-closed (503).
        if (! $enabled || $serverKey === '') {
            throw new PaymentGatewayUnavailableException;
        }

        // Transaksi Snap yang masih pending dipakai ulang: Midtrans menolak order_id ganda.
        $existing = GatewayTransaction::query()
            ->where('midtrans_order_id', $orderId)
            ->where('status', 'pending')
            ->whereNotNull('payload')
            ->latest('id')
            ->first();

        $existingToken = (string) data_get($existing?->payload, 'token', '');

        if ($existingToken !== '') {
            return [
                'snap_token' => $existingToken,
                'redirect_url' => (string) data_get($existing->payload, 'redirect_url', ''),
                'order_id' => $orderId,
                'mode' => 'midtrans',
            ];
        }

        $response = Http::withBasicAuth($serverKey, '')
            ->connectTimeout(3)
            ->timeout(10)
            ->acceptJson()
            ->post($this->snapBaseUrl(), $this->snapOrderPayload($order, $orderId, $grossAmount));

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Gagal membuat pembayaran Midtrans. Silakan coba lagi.',
            ]);
        }

        GatewayTransaction::query()->updateOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'provider' => 'midtrans',
                'provider_reference' => $orderId,
                'type' => 'payment',
                'status' => 'pending',
                'gross_amount' => $grossAmount,
                'fee_amount' => 0,
                'net_amount' => $grossAmount,
                'payload' => $response->json(),
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'internal_type' => 'order',
                'internal_id' => $order->id,
                'midtrans_order_id' => $orderId,
            ],
        );

        return [
            'snap_token' => (string) $response->json('token'),
            'redirect_url' => (string) $response->json('redirect_url'),
            'order_id' => $orderId,
            'mode' => 'midtrans',
        ];
    }

    /**
     * Susun payload Snap untuk pesanan.
     * Hanya sertakan item_details bila totalnya pas dengan gross_amount (Midtrans wajib sum(items)=gross).
     * Ini menangani kasus split payment (gross < total) dengan tidak mengirim item_details.
     */
    private function snapOrderPayload(Order $order, string $orderId, int $grossAmount): array
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $order->customer?->name,
                'email' => $order->customer?->email,
                'phone' => $order->customer?->phone,
            ],
            'callbacks' => [
                'finish' => $order->customer_id
                    ? url('/akun/pesanan/'.$order->id)
                    : url('/pesanan/'.$order->id),
                'unfinish' => $order->customer_id
                    ? url('/akun/pesanan/'.$order->id.'/bayar')
                    : url('/pesanan/'.$order->id),
                'error' => $order->customer_id
                    ? url('/akun/pesanan/'.$order->id.'/bayar?error=1')
                    : url('/pesanan/'.$order->id),
            ],
        ];

        $itemDetails = self::orderItemDetails($order, $grossAmount);

        if ($itemDetails !== null) {
            $payload['item_details'] = $itemDetails;
        }

        return $payload;
    }

    /**
     * Rincian item Snap: baris menu + diskon/PB1/layanan agar total sama dengan gross_amount.
     * Mengembalikan null bila jumlahnya tidak cocok (mis. split payment) — Midtrans menolak 400.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public static function orderItemDetails(Order $order, int $grossAmount): ?array
    {
        $lines = $order->items->map(fn ($item): array => [
            'id' => (string) ($item->menu_item_id ?? $item->id),
            'price' => (int) $item->unit_price,
            'quantity' => (int) $item->quantity,
            'name' => $item->name,
        ])->values()->all();

        $adjustments = [];
        if ((int) $order->discount > 0) {
            $adjustments[] = ['id' => 'DISKON', 'price' => -(int) $order->discount, 'quantity' => 1, 'name' => 'Diskon'];
        }
        if ((int) $order->tax > 0) {
            $adjustments[] = ['id' => 'PB1', 'price' => (int) $order->tax, 'quantity' => 1, 'name' => 'PB1'];
        }
        if ((int) $order->service > 0) {
            $adjustments[] = ['id' => 'LAYANAN', 'price' => (int) $order->service, 'quantity' => 1, 'name' => 'Layanan'];
        }

        $itemSum = collect($lines)->sum(fn ($l) => $l['price'] * $l['quantity']) + collect($adjustments)->sum('price');

        if ($itemSum !== $grossAmount) {
            return null;
        }

        return array_merge($lines, $adjustments);
    }

    /**
     * Hitung signature SHA512 Midtrans: sha512(order_id + status_code + gross_amount + server_key)
     */
    public function generateSignature(string $orderId, string $statusCode, string $grossAmount, string $serverKey): string
    {
        return hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
    }

    /**
     * Validasi signature dari payload notifikasi Midtrans.
     */
    public function verifySignature(array $payload, ?string $serverKey = null): bool
    {
        $candidates = $serverKey !== null ? [$serverKey] : EnvironmentClass::verificationKeys();

        if (! isset($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $payload['signature_key'])) {
            return false;
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $expected = $this->generateSignature(
                (string) $payload['order_id'],
                (string) $payload['status_code'],
                (string) $payload['gross_amount'],
                $candidate,
            );

            if (hash_equals($expected, (string) $payload['signature_key'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nominal gateway dalam format string "50000" atau "50000.00" — IDR tanpa sen.
     * Float aman di sini karena Midtrans tidak pernah mengirim pecahan Rupiah.
     */
    private function parseGrossAmount(string $grossAmount): int
    {
        return (int) round((float) $grossAmount);
    }

    /**
     * Proses notifikasi webhook Midtrans secara atomik dan idempotent.
     * Mengembalikan GatewayTransaction yang sudah diperbarui atau dibentuk.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function handleNotification(array $payload): ?GatewayTransaction
    {
        return $this->processNotification($payload)->transaction;
    }

    /**
     * Proses notifikasi webhook Midtrans secara atomik dan idempotent.
     * Efek bisnis dan claim outbox commit bersama; hasilnya membawa token
     * hasil untuk dipetakan controller ke status HTTP.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function processNotification(array $payload, ?int $pipelineStartMs = null): GatewayNotificationResult
    {
        if (! $this->verifySignature($payload)) {
            throw ValidationException::withMessages([
                'signature' => 'Signature Midtrans tidak valid. Notifikasi ditolak demi keamanan.',
            ]);
        }

        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $idempotencyKey = GatewayTransaction::idempotencyKey('midtrans', $orderId.':'.$transactionStatus.':'.$statusCode);
        $providerEventKey = $orderId.':'.$transactionStatus.':'.$statusCode;
        $payloadDigest = hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use ($payload, $orderId, $statusCode, $transactionStatus, $payloadDigest, $providerEventKey, $idempotencyKey, $pipelineStartMs): GatewayNotificationResult {
            $fraudStatus = (string) ($payload['fraud_status'] ?? '');
            $grossAmount = $this->parseGrossAmount((string) ($payload['gross_amount'] ?? '0'));
            $currency = (string) ($payload['currency'] ?? 'IDR');
            if ($currency !== '' && strcasecmp($currency, 'IDR') !== 0) {
                $this->auditRecorder->claim(
                    $providerEventKey,
                    GatewayResultToken::CurrencyMismatch->value,
                    $payloadDigest,
                    ['event_type' => 'webhook', 'event_source' => 'midtrans', 'currency' => $currency],
                );

                return new GatewayNotificationResult(null, GatewayResultToken::CurrencyMismatch);
            }

            // N13.5: Pre-commit timeout check — sebelum commit, jika lewat batas,
            // lempar exception sehingga efek bisnis + claim ikut rollback.
            if ($pipelineStartMs !== null) {
                $limitMs = (int) config('pos.midtrans.webhook_timeout_seconds', 5) * 1000;

                if (($this->clock()?->nowMs() ?? 0) - $pipelineStartMs > $limitMs) {
                    throw new PreCommitTimeoutException;
                }
            }

            // Cek replay idempotency
            $existing = GatewayTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $this->auditRecorder->claim(
                    $providerEventKey,
                    GatewayResultToken::DuplicateIgnored->value,
                    $payloadDigest,
                    ['event_type' => 'webhook', 'event_source' => 'midtrans'],
                );

                return new GatewayNotificationResult($existing, GatewayResultToken::DuplicateIgnored);
            }

            $isSettlement = ($transactionStatus === 'settlement')
                || ($transactionStatus === 'capture' && $fraudStatus === 'accept');

            // Deteksi jenis transaksi: topup pelanggan ("TOPUP-CUST-{id}-...") atau order ("ORD-...")
            $isTopUp = str_starts_with($orderId, 'TOPUP-');
            $customer = null;
            $order = null;
            $walletTopUp = null;

            if ($isTopUp) {
                $walletTopUp = WalletTopUp::query()->where('midtrans_order_id', $orderId)->first();
                if ($walletTopUp !== null) {
                    $customer = $walletTopUp->customer;
                } else {
                    // Legacy format: TOPUP-{customerId}-{timestamp}
                    $parts = explode('-', $orderId);
                    $customerId = isset($parts[1]) ? (int) $parts[1] : 0;
                    $customer = Customer::query()->find($customerId);
                }
            } else {
                $order = Order::query()->where('number', $orderId)->first();
                $customer = $order?->customer;
            }

            // Referensi tak dikenal: tidak ada efek uang, terminal (200).
            if ($customer === null && $order === null) {
                $this->auditRecorder->claim(
                    $providerEventKey,
                    GatewayResultToken::UnknownReference->value,
                    $payloadDigest,
                    ['event_type' => 'webhook', 'event_source' => 'midtrans', 'order_id' => $orderId],
                );

                return new GatewayNotificationResult(null, GatewayResultToken::UnknownReference);
            }

            $gatewayTrx = GatewayTransaction::query()
                ->where('midtrans_order_id', $orderId)
                ->orderBy('id')
                ->first();

            if ($gatewayTrx === null) {
                $gatewayTrx = GatewayTransaction::query()->create([
                    'provider' => 'midtrans',
                    'provider_reference' => $orderId,
                    'idempotency_key' => $idempotencyKey,
                    'type' => $isTopUp ? 'topup' : 'payment',
                    'status' => $isSettlement ? 'settlement' : $transactionStatus,
                    'gross_amount' => $grossAmount,
                    'fee_amount' => 0,
                    'net_amount' => $grossAmount,
                    'payload' => $payload,
                    'payload_digest' => $payloadDigest,
                    'signature' => (string) ($payload['signature_key'] ?? null),
                    'customer_id' => $customer?->id,
                    'order_id' => $order?->id,
                    'internal_type' => $isTopUp ? 'wallet_top_up' : 'order',
                    'internal_id' => $isTopUp ? ($walletTopUp?->id ?? $customer?->id) : $order?->id,
                    'midtrans_order_id' => $orderId,
                    'settled_at' => $isSettlement ? now() : null,
                ]);
            } else {
                $gatewayTrx->fill([
                    'status' => $isSettlement ? 'settlement' : $transactionStatus,
                    'payload' => $payload,
                    'payload_digest' => $payloadDigest,
                    'signature' => (string) ($payload['signature_key'] ?? null),
                    'settled_at' => $isSettlement ? ($gatewayTrx->settled_at ?? now()) : $gatewayTrx->settled_at,
                ])->save();
            }

            $internalContext = $isTopUp && $customer
                ? new PaymentContext($gatewayTrx, null, $walletTopUp, $customer->id, 'topup', $walletTopUp?->id ?? $customer->id)
                : ($order !== null ? new PaymentContext($gatewayTrx, $order, null, $order->customer_id, 'payment', $order->id) : null);

            $resultToken = GatewayResultToken::Processed;

            if ($internalContext !== null) {
                $observation = new NormalizedObservation(
                    orderId: $orderId,
                    statusCode: $statusCode,
                    grossAmount: (string) ($payload['gross_amount'] ?? '0'),
                    signatureKey: (string) ($payload['signature_key'] ?? ''),
                    payloadDigest: $payloadDigest,
                    payloadEncrypted: '',
                    rawFields: array_map(static fn ($value): mixed => is_scalar($value) ? (string) $value : $value, $payload),
                    currency: $currency,
                    normalizedAmount: number_format($grossAmount, 2, '.', ''),
                );
                $resultToken = $this->dispatchObservation($internalContext, $observation);
            }

            // Timeout dipastikan tepat sebelum transaksi commit; exception ini
            // juga mengembalikan transaksi dan claim bisnis ke keadaan awal.
            if ($pipelineStartMs !== null) {
                $limitMs = (int) config('pos.midtrans.webhook_timeout_seconds', 5) * 1000;

                if (($this->clock()?->nowMs() ?? 0) - $pipelineStartMs > $limitMs) {
                    throw new PreCommitTimeoutException;
                }
            }

            // Claim audit dalam transaksi yang sama dengan efek bisnis.
            $this->auditRecorder->claim(
                $providerEventKey,
                $resultToken->value,
                $payloadDigest,
                [
                    'event_type' => 'webhook',
                    'event_source' => 'midtrans',
                    'order_id' => $orderId,
                    'transaction_status' => $transactionStatus,
                ],
            );

            return new GatewayNotificationResult($gatewayTrx, $resultToken);
        });
    }

    /**
     * Route a verified notification to the pipeline that owns the money effect.
     *
     * Settlement (money in) stays with SettlementService; refund / chargeback
     * notifications are provider-initiated reversals we only record, and a
     * partial-refund -> terminal transition restores the refunded amount first.
     * Provider-side refunds initiated by operators never pass through here.
     */
    private function dispatchObservation(PaymentContext $context, NormalizedObservation $observation): GatewayResultToken
    {
        $event = strtolower(trim((string) ($observation->rawFields['transaction_status'] ?? '')));

        if (in_array($event, ['refund', 'partial_refund'], true)) {
            return app(SettlementReversalService::class)->processRefund($context, $observation);
        }

        if ($event === 'chargeback') {
            return app(SettlementReversalService::class)->processChargeback($context, $observation);
        }

        if (in_array($event, ['deny', 'cancel', 'expire', 'failure'], true)) {
            return app(SettlementReversalService::class)->processReversal($context, $observation);
        }

        return app(SettlementService::class)->processSettlement($context, $observation);
    }
}
