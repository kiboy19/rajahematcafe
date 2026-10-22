<?php

namespace App\Payments\Midtrans;

use App\Enums\GatewayResultToken;
use App\Support\Clock;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

final class HttpMidtransClient implements MidtransClient
{
    private const RETRYABLE_STATUS = [408, 425, 429];

    public function __construct(
        private readonly string $serverKey,
        private readonly string $baseUrl,
        private readonly int $connectTimeoutMs,
        private readonly int $readTimeoutMs,
        private readonly Clock $clock,
        private readonly ?CircuitBreaker $breaker = null,
        private readonly ?ClientInterface $httpClient = null,
    ) {}

    public function createSnapTransaction(SnapRequest $request): SnapTokenResponse
    {
        /** @var SnapTokenResponse $response */
        $response = $this->call(
            fn (): ProviderResponse => $this->decode(
                $this->send('POST', $this->url('/transactions'), [
                    'json' => $this->buildSnapPayload($request),
                ]),
                'create_snap',
            ),
            SnapTokenResponse::class,
        );

        return $response;
    }

    public function fetchTransactionStatus(string $orderId): GatewayStatusResponse
    {
        /** @var GatewayStatusResponse $response */
        $response = $this->call(
            fn (): ProviderResponse => $this->decode(
                $this->send('GET', $this->url('/transactions/'.$orderId.'/status'), []),
                'fetch_status',
            ),
            GatewayStatusResponse::class,
        );

        return $response;
    }

    public function directRefund(RefundRequest $request): RefundResponse
    {
        /** @var RefundResponse $response */
        $response = $this->call(
            fn (): ProviderResponse => $this->decode(
                $this->send('POST', $this->url('/transactions/'.$request->midtransOrderId.'/refund'), [
                    'json' => [
                        'refund_key' => $request->providerRefundId,
                        'amount' => $request->amount,
                    ],
                ]),
                'direct_refund',
            ),
            RefundResponse::class,
        );

        return $response;
    }

    /**
     * Ejecuta la operación lógica con un único reintento sobre la misma petición
     * serializada (mismo order_id / refund_key / idempotencia). El breaker registra
     * exactamente un desenlace por operación lógica: nunca por intento.
     */
    private function call(callable $operation, string $dtoClass): ProviderResponse
    {
        if ($this->breaker !== null && ! $this->breaker->tryProbe()) {
            return $this->error($dtoClass, ProviderErrorType::ProviderUnavailable, 503, 'circuit_open');
        }

        $attempts = 0;
        $result = null;
        $retryable = false;

        do {
            try {
                $result = $operation();
                $retryable = $this->isRetryable($result);
            } catch (ConnectException $e) {
                $result = $this->error($dtoClass, ProviderErrorType::ProviderTimeout, 0, $e->getMessage());
                $retryable = true;
            } catch (RequestException $e) {
                $status = $e->getResponse()?->getStatusCode() ?? 0;

                if ($status === 0) {
                    $result = $this->error($dtoClass, ProviderErrorType::ProviderTimeout, 0, $e->getMessage());
                    $retryable = true;
                } else {
                    $result = $this->httpError($dtoClass, $status, (string) $e->getResponse()?->getBody());
                }

                $retryable = $this->isRetryable($result);
            }
        } while ($retryable && ++$attempts < 2);

        $this->settleBreaker($result);

        return $result;
    }

    private function settleBreaker(ProviderResponse $result): void
    {
        if ($this->breaker === null) {
            return;
        }

        if ($result->successful) {
            $this->breaker->recordSuccess();

            return;
        }

        $this->breaker->recordFailure();
    }

    private function isRetryable(ProviderResponse $response): bool
    {
        if ($response->errorType === GatewayResultToken::ProviderMalformed) {
            return false;
        }

        if ($response->errorType === GatewayResultToken::ProviderRejected) {
            return false;
        }

        if ($response->errorType === GatewayResultToken::ProviderTimeout) {
            return true;
        }

        if ($response->errorType === GatewayResultToken::ProviderUnavailable) {
            return $response->httpStatus >= 500
                || in_array($response->httpStatus, self::RETRYABLE_STATUS, true)
                || $response->httpStatus === 0;
        }

        return false;
    }

    private function httpError(string $dtoClass, int $status, string $body): ProviderResponse
    {
        if ($status >= 500 || in_array($status, self::RETRYABLE_STATUS, true)) {
            return $this->error($dtoClass, ProviderErrorType::ProviderUnavailable, $status, $body);
        }

        return $this->error($dtoClass, ProviderErrorType::ProviderRejected, $status, $body);
    }

    private function send(string $method, string $uri, array $options): ResponseInterface
    {
        $options['http_errors'] = true;
        $options['headers'] = array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic '.base64_encode($this->serverKey.':'),
        ], $options['headers'] ?? []);

        $client = $this->httpClient ?? $this->defaultClient();

        return $client->request($method, $uri, $options);
    }

    private function defaultClient(): Client
    {
        return new Client([
            'base_uri' => rtrim($this->baseUrl, '/').'/',
            'connect_timeout' => $this->connectTimeoutMs / 1000,
            'timeout' => $this->readTimeoutMs / 1000,
            'http_errors' => true,
        ]);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $body): array
    {
        if (trim($body) === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new MalformedProviderPayloadException;
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function decode(ResponseInterface $response, string $operation): ProviderResponse
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $dtoClass = $this->dtoClassFor($operation);

        if ($status >= 400) {
            return $this->httpError($dtoClass, $status, $body);
        }

        try {
            $payload = $this->json($body);
        } catch (MalformedProviderPayloadException) {
            return $this->error($dtoClass, ProviderErrorType::ProviderMalformed, $status, $body);
        }

        return match ($operation) {
            'create_snap' => new SnapTokenResponse(
                true,
                isset($payload['token']) ? (string) $payload['token'] : null,
                isset($payload['redirect_url']) ? (string) $payload['redirect_url'] : null,
                null,
                $status,
                $payload,
            ),
            'fetch_status' => new GatewayStatusResponse(
                true,
                isset($payload['order_id']) ? (string) $payload['order_id'] : '',
                isset($payload['transaction_status']) ? (string) $payload['transaction_status'] : null,
                isset($payload['fraud_status']) ? (string) $payload['fraud_status'] : null,
                null,
                $status,
                $payload,
            ),
            default => new RefundResponse(
                true,
                isset($payload['refund_key']) ? (string) $payload['refund_key'] : null,
                null,
                $status,
                $payload,
            ),
        };
    }

    /**
     * DTO yang sesuai untuk tiap operasi; dipakai agar error terklasifikasi
     * tidak pernah kembali sebagai tipe respons yang salah.
     *
     * @return class-string<ProviderResponse>
     */
    private function dtoClassFor(string $operation): string
    {
        return match ($operation) {
            'create_snap' => SnapTokenResponse::class,
            'fetch_status' => GatewayStatusResponse::class,
            default => RefundResponse::class,
        };
    }

    private function error(string $dtoClass, ProviderErrorType $type, int $status, string $message): ProviderResponse
    {
        $token = match ($type) {
            ProviderErrorType::ProviderTimeout => GatewayResultToken::ProviderTimeout,
            ProviderErrorType::ProviderRejected => GatewayResultToken::ProviderRejected,
            ProviderErrorType::ProviderUnavailable => GatewayResultToken::ProviderUnavailable,
            ProviderErrorType::ProviderMalformed => GatewayResultToken::ProviderMalformed,
        };

        $errorType = $token;

        return match ($dtoClass) {
            SnapTokenResponse::class => new SnapTokenResponse(false, null, null, $errorType, $status, [], $message),
            GatewayStatusResponse::class => new GatewayStatusResponse(false, '', null, null, $errorType, $status, [], $message),
            RefundResponse::class => new RefundResponse(false, null, $errorType, $status, [], $message),
            default => throw new \InvalidArgumentException("Unknown DTO class {$dtoClass}"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapPayload(SnapRequest $request): array
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $request->midtransOrderId,
                'gross_amount' => $request->grossAmount,
            ],
            'credit_card' => ['secure' => true],
        ];

        if ($request->payload !== []) {
            $payload += $request->payload;
        }

        return $payload;
    }
}
