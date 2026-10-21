<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\GatewayTransaction;
use App\Models\Order;
use App\Models\Payment;
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
    ) {}

    /**
     * URL basis API Snap Midtrans (sandbox vs production).
     */
    public function snapBaseUrl(): string
    {
        return config('pos.midtrans.is_production')
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
        $min = (int) config('pos.wallet_min_topup');
        if ($amount < $min) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal top-up minimal Rp'.number_format($min, 0, ',', '.').'.',
            ]);
        }

        $serverKey = (string) config('pos.midtrans.server_key');
        $enabled = (bool) config('pos.midtrans.enabled');
        $orderId = 'TOPUP-'.$customer->id.'-'.time().'-'.bin2hex(random_bytes(3));
        $idempotencyKey = GatewayTransaction::idempotencyKey('midtrans', $orderId.':pending:create');

        // Jika Midtrans aktif dan server_key sudah diisi, hubungi API Snap Midtrans.
        if ($enabled && $serverKey !== '') {
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

        // Mode simulasi/lokal jika Midtrans belum diaktifkan di .env (mis. pengujian lokal)
        GatewayTransaction::query()->create([
            'provider' => 'midtrans',
            'provider_reference' => $orderId,
            'idempotency_key' => $idempotencyKey,
            'type' => 'topup',
            'status' => 'pending',
            'gross_amount' => $amount,
            'fee_amount' => 0,
            'net_amount' => $amount,
            'payload' => ['mode' => 'mock'],
            'customer_id' => $customer->id,
        ]);

        return [
            'snap_token' => null,
            'redirect_url' => null,
            'order_id' => $orderId,
            'mode' => 'mock',
        ];
    }

    /**
     * Simulasi pelunasan untuk mode lokal/testing tanpa harus memicu webhook asli.
     * Mendukung top-up wallet maupun pembayaran pesanan online.
     */
    public function simulateSettlement(Customer $customer, string $orderId): GatewayTransaction
    {
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
    public function createOrderPayment(Order $order): array
    {
        $serverKey = (string) config('pos.midtrans.server_key');
        $enabled = (bool) config('pos.midtrans.enabled');
        $orderId = $order->number;
        $idempotencyKey = GatewayTransaction::idempotencyKey('midtrans', $orderId.':pending');

        if ($enabled && $serverKey !== '') {
            $response = Http::withBasicAuth($serverKey, '')
                ->connectTimeout(3)
                ->timeout(10)
                ->acceptJson()
                ->post($this->snapBaseUrl(), [
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => (int) $order->total,
                    ],
                    'customer_details' => [
                        'first_name' => $order->customer?->name,
                        'email' => $order->customer?->email,
                        'phone' => $order->customer?->phone,
                    ],
                    'item_details' => $order->items->map(fn ($item): array => [
                        'id' => (string) ($item->menu_item_id ?? $item->id),
                        'price' => (int) $item->unit_price,
                        'quantity' => (int) $item->quantity,
                        'name' => $item->name,
                    ])->values()->all(),
                ]);

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
                    'gross_amount' => (int) $order->total,
                    'fee_amount' => 0,
                    'net_amount' => (int) $order->total,
                    'payload' => $response->json(),
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                ],
            );

            return [
                'snap_token' => (string) $response->json('token'),
                'redirect_url' => (string) $response->json('redirect_url'),
                'order_id' => $orderId,
                'mode' => 'midtrans',
            ];
        }

        GatewayTransaction::query()->updateOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'provider' => 'midtrans',
                'provider_reference' => $orderId,
                'type' => 'payment',
                'status' => 'pending',
                'gross_amount' => (int) $order->total,
                'fee_amount' => 0,
                'net_amount' => (int) $order->total,
                'payload' => ['mode' => 'mock'],
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
            ],
        );

        return [
            'snap_token' => null,
            'redirect_url' => null,
            'order_id' => $orderId,
            'mode' => 'mock',
        ];
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
        $serverKey ??= (string) config('pos.midtrans.server_key');

        if ($serverKey === '' || ! isset($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $payload['signature_key'])) {
            return false;
        }

        $expected = $this->generateSignature(
            (string) $payload['order_id'],
            (string) $payload['status_code'],
            (string) $payload['gross_amount'],
            $serverKey,
        );

        return hash_equals($expected, (string) $payload['signature_key']);
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
    public function handleNotification(array $payload): GatewayTransaction
    {
        if (! $this->verifySignature($payload)) {
            throw ValidationException::withMessages([
                'signature' => 'Signature Midtrans tidak valid. Notifikasi ditolak demi keamanan.',
            ]);
        }

        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = (string) ($payload['fraud_status'] ?? '');
        $grossAmount = $this->parseGrossAmount((string) ($payload['gross_amount'] ?? '0'));
        $idempotencyKey = GatewayTransaction::idempotencyKey('midtrans', $orderId.':'.$transactionStatus.':'.$statusCode);

        return DB::transaction(function () use ($payload, $orderId, $transactionStatus, $fraudStatus, $grossAmount, $idempotencyKey): GatewayTransaction {
            // Cek replay idempotency
            $existing = GatewayTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $isSettlement = ($transactionStatus === 'settlement')
                || ($transactionStatus === 'capture' && $fraudStatus === 'accept');

            // Deteksi jenis transaksi: topup pelanggan ("TOPUP-CUST-{id}-...") atau order ("ORD-...")
            $isTopUp = str_starts_with($orderId, 'TOPUP-');
            $customer = null;
            $order = null;

            if ($isTopUp) {
                // Format: TOPUP-{customerId}-{timestamp}
                $parts = explode('-', $orderId);
                $customerId = isset($parts[1]) ? (int) $parts[1] : 0;
                $customer = Customer::query()->find($customerId);
            } else {
                $order = Order::query()->where('number', $orderId)->first();
                $customer = $order?->customer;
            }

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
                'signature' => (string) ($payload['signature_key'] ?? null),
                'customer_id' => $customer?->id,
                'order_id' => $order?->id,
                'settled_at' => $isSettlement ? now() : null,
            ]);

            if ($isSettlement) {
                if ($isTopUp && $customer) {
                    $walletTrx = $this->wallet->credit(
                        customer: $customer,
                        type: WalletTransactionType::TopUp,
                        amount: $grossAmount,
                        note: 'Top-up online Midtrans (Ref: '.$orderId.')',
                    );
                    $gatewayTrx->update(['wallet_transaction_id' => $walletTrx->id]);
                } elseif ($order !== null) {
                    // Pelunasan online. Payment pending dari checkout diperbarui,
                    // bukan ditulis dua kali (reference unik, metode dipetakan dari
                    // payment_type Midtrans: bank transfer/*_va -> Virtual Account).
                    $payment = Payment::query()->firstOrCreate(
                        ['reference' => 'MIDTRANS-'.$orderId],
                        [
                            'order_id' => $order->id,
                            'method' => $this->paymentMethodFrom($payload['payment_type'] ?? ''),
                            'status' => PaymentStatus::Paid,
                            'amount' => $grossAmount,
                            'tendered' => $grossAmount,
                            'change' => 0,
                            'paid_at' => now(),
                        ],
                    );

                    $order->update(['paid_at' => now()]);

                    // Hanya pesanan yang menunggu pembayaran yang dipromosikan;
                    // pesanan kasir tidak boleh tersentuh logika ini.
                    if ($order->status === OrderStatus::MenungguPembayaran) {
                        $order->update(['status' => OrderStatus::Baru]);
                    }
                }

                AuditLog::record('gateway.settlement', $gatewayTrx, after: [
                    'order_id' => $orderId,
                    'status' => $transactionStatus,
                    'gross_amount' => $grossAmount,
                ]);
            }

            return $gatewayTrx;
        });
    }
}
