<?php

namespace App\Payments\Notification;

use App\Payments\Environment\EnvironmentClass;
use JsonException;
use RuntimeException;

final class NotificationParser
{
    public function __construct(
        private readonly DuplicateKeyScanner $json = new DuplicateKeyScanner,
        private readonly SignatureVerifier $signatures = new SignatureVerifier,
        private readonly AmountNormalizer $amounts = new AmountNormalizer,
    ) {
    }

    public function parse(string $rawBody, string $contentType, ?string $serverKey = null): NormalizedObservation
    {
        $this->validateRawBody($rawBody);
        $contentType = strtolower(trim(explode(';', $contentType, 2)[0]));

        try {
            $fields = match ($contentType) {
                'application/json' => $this->json->decode($rawBody),
                'application/x-www-form-urlencoded' => $this->parseForm($rawBody),
                default => throw new NotificationValidationException(415, 'unsupported_media_type'),
            };
        } catch (NotificationValidationException $exception) {
            throw $exception;
        } catch (RuntimeException|JsonException $exception) {
            throw new NotificationValidationException(400, 'malformed_payload', previous: $exception);
        }

        $fields = array_map(static fn ($value): string => is_scalar($value) ? (string) $value : '', $fields);
        $orderId = $fields['order_id'] ?? '';
        $statusCode = $fields['status_code'] ?? '';
        $grossAmount = $fields['gross_amount'] ?? '';
        $signatureKey = $fields['signature_key'] ?? '';
        if ($orderId === '' || $statusCode === '' || $grossAmount === '') {
            throw new NotificationValidationException(400, 'missing_required_field');
        }
        if ($signatureKey === '') {
            throw new NotificationValidationException(400, 'missing_signature');
        }

        try {
            $normalizedAmount = $this->amounts->normalize($grossAmount, $fields['currency'] ?? null);
        } catch (AmountNormalizationException $exception) {
            throw new NotificationValidationException(400, $exception->getErrorCode(), previous: $exception);
        }

        $key = $serverKey ?? EnvironmentClass::serverKey();
        if ($key === '') {
            throw new NotificationValidationException(503, 'server_key_unavailable');
        }
        if (! $this->signatures->verify($orderId, $statusCode, $grossAmount, $signatureKey, $key)) {
            throw new NotificationValidationException(401, 'invalid_signature');
        }

        $encrypted = '';
        if (EnvironmentClass::payloadKey() !== '') {
            $encrypted = (new PayloadCipher(EnvironmentClass::payloadKey()))->encrypt($rawBody);
        }

        return new NormalizedObservation(
            orderId: $orderId,
            statusCode: $statusCode,
            grossAmount: $grossAmount,
            signatureKey: $signatureKey,
            payloadDigest: hash('sha256', $rawBody),
            payloadEncrypted: $encrypted,
            rawFields: $fields,
            currency: $fields['currency'] ?? null,
            normalizedAmount: $normalizedAmount,
        );
    }

    private function parseForm(string $rawBody): array
    {
        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $rawBody)) {
            throw new NotificationValidationException(400, 'malformed_percent_encoding');
        }
        if (str_contains($rawBody, '%25')) {
            throw new NotificationValidationException(400, 'double_encoded_payload');
        }

        $fields = [];
        foreach (explode('&', $rawBody) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            $value = urldecode($value);
            if (array_key_exists($key, $fields)) {
                throw new NotificationValidationException(400, 'repeated_parameter');
            }
            $fields[$key] = $value;
        }
        return $fields;
    }

    private function validateRawBody(string $rawBody): void
    {
        if (strlen($rawBody) > EnvironmentClass::maxPayloadBytes()) {
            throw new NotificationValidationException(413, 'payload_too_large');
        }
        if (function_exists('mb_check_encoding') && ! mb_check_encoding($rawBody, 'UTF-8')) {
            throw new NotificationValidationException(400, 'invalid_utf8');
        }
    }
}