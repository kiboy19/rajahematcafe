<?php

namespace Tests\Unit\Payments;

use App\Payments\Notification\AmountNormalizationException;
use App\Payments\Notification\AmountNormalizer;
use App\Payments\Notification\DuplicateKeyScanner;
use App\Payments\Notification\NotificationParser;
use App\Payments\Notification\NotificationValidationException;
use App\Payments\Notification\PayloadCipher;
use App\Payments\Notification\SignatureVerifier;
use Tests\TestCase;

class NotificationParserTest extends TestCase
{
    private const SERVER_KEY = 'SB-Mid-server-testkey123456';

    private const PAYLOAD_KEY = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'pos.midtrans.server_key' => self::SERVER_KEY,
            'pos.midtrans.payload_key' => self::PAYLOAD_KEY,
            'pos.midtrans.max_payload_bytes' => 65536,
        ]);
    }

    private function signedFields(string $orderId = 'ORD-001', string $grossAmount = '50000', string $statusCode = '200', string $serverKey = self::SERVER_KEY): array
    {
        $signature = (new SignatureVerifier)->computeSignature($orderId, $statusCode, $grossAmount, $serverKey);

        return [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
        ];
    }

    private function parser(): NotificationParser
    {
        return new NotificationParser;
    }

    private function j(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function assertRejected(int $status, string $category, string $rawBody, string $contentType = 'application/json'): void
    {
        try {
            $this->parser()->parse($rawBody, $contentType);
            $this->fail("Expected rejection: {$category}");
        } catch (NotificationValidationException $exception) {
            $this->assertSame($status, $exception->getHttpStatus());
            $this->assertSame($category, $exception->getAuditCategory());
        }
    }

    public function test_accepts_valid_json_and_normalizes_amount(): void
    {
        $raw = $this->j($this->signedFields());
        $observation = $this->parser()->parse($raw, 'application/json; charset=utf-8');

        $this->assertSame('ORD-001', $observation->orderId);
        $this->assertSame('50000', $observation->grossAmount);
        $this->assertSame('50000', $observation->normalizedAmount);
        $this->assertSame(hash('sha256', $raw), $observation->payloadDigest);
        $this->assertNotEmpty($observation->payloadEncrypted);
    }

    public function test_encrypted_payload_round_trips_and_carries_key_id_prefix(): void
    {
        $raw = $this->j($this->signedFields());
        $observation = $this->parser()->parse($raw, 'application/json');

        $cipher = new PayloadCipher(self::PAYLOAD_KEY);
        $this->assertSame('0001', substr($observation->payloadEncrypted, 0, 4));
        $this->assertSame($raw, $cipher->decrypt($observation->payloadEncrypted));
    }

    public function test_form_encoded_plus_is_decoded_as_space(): void
    {
        $fields = $this->signedFields('ORD 001');
        $body = http_build_query($fields);
        $observation = $this->parser()->parse($body, 'application/x-www-form-urlencoded');

        $this->assertSame('ORD 001', $observation->orderId);
    }

    public function test_repeated_parameter_rejected(): void
    {
        $fields = $this->signedFields();
        $body = http_build_query($fields) . '&order_id=ORD-002';
        $this->assertRejected(400, 'repeated_parameter', $body, 'application/x-www-form-urlencoded');
    }

    public function test_double_encoded_payload_rejected(): void
    {
        $body = 'order_id=ORD%2525001&status_code=200&gross_amount=50000&signature_key=abc';
        $this->assertRejected(400, 'double_encoded_payload', $body, 'application/x-www-form-urlencoded');
    }

    public function test_malformed_percent_encoding_rejected(): void
    {
        $body = 'order_id=ORD%ZZ&status_code=200&gross_amount=50000&signature_key=abc';
        $this->assertRejected(400, 'malformed_percent_encoding', $body, 'application/x-www-form-urlencoded');
    }

    public function test_invalid_utf8_rejected(): void
    {
        $this->assertRejected(400, 'invalid_utf8', "\xC3\x28", 'application/json');
    }

    public function test_duplicate_json_keys_rejected(): void
    {
        $fields = $this->signedFields();
        $raw = '{"order_id":"ORD-001","order_id":"ORD-001","status_code":"200","gross_amount":"50000","signature_key":"'.$fields['signature_key'].'"}';
        $this->assertRejected(400, 'malformed_payload', $raw);
    }

    public function test_duplicate_keys_nested_in_arrays_rejected(): void
    {
        $raw = '{"order_id":"ORD-001","status_code":"200","gross_amount":"50000","signature_key":"x","items":[{"a":1,"a":2}]}';
        $this->assertExpectedRuntime(fn () => (new DuplicateKeyScanner)->decode($raw));
    }

    public function test_unsupported_content_type_rejected(): void
    {
        $this->assertRejected(415, 'unsupported_media_type', $this->j($this->signedFields()), 'text/plain');
    }

    public function test_missing_signature_rejected(): void
    {
        $fields = $this->signedFields();
        unset($fields['signature_key']);
        $this->assertRejected(400, 'missing_signature', $this->j($fields));
    }

    public function test_invalid_signature_rejected(): void
    {
        $fields = $this->signedFields();
        $fields['signature_key'] = str_repeat('a', 128);
        $this->assertRejected(401, 'invalid_signature', $this->j($fields));
    }

    public function test_empty_server_key_fails_closed(): void
    {
        config(['pos.midtrans.server_key' => '']);
        $fields = $this->signedFields();
        $this->assertRejected(503, 'server_key_unavailable', $this->j($fields));
    }

    public function test_unicode_order_id_supported(): void
    {
        $fields = $this->signedFields('ORD-çafé-001');
        $observation = $this->parser()->parse($this->j($fields), 'application/json');
        $this->assertSame('ORD-çafé-001', $observation->orderId);
    }

    public function test_payload_over_limit_rejected(): void
    {
        config(['pos.midtrans.max_payload_bytes' => 64]);
        $this->assertRejected(413, 'payload_too_large', $this->j($this->signedFields()));
    }

    /**
     * @dataProvider amountProvider
     */
    public function test_amount_normalization(string $raw, ?string $currency, ?string $expected, ?string $errorCode = null): void
    {
        $normalizer = new AmountNormalizer;

        if ($errorCode !== null) {
            try {
                $normalizer->normalize($raw, $currency);
                $this->fail("Expected {$errorCode}");
            } catch (AmountNormalizationException $exception) {
                $this->assertSame($errorCode, $exception->getErrorCode());
            }
            return;
        }

        $this->assertSame($expected, $normalizer->normalize($raw, $currency));
    }

    public static function amountProvider(): array
    {
        return [
            ['50000.00', null, '50000'],
            ['50000.50', null, 'amount_precision', 'amount_precision'],
            ['50000', null, '50000'],
            ['50000.000', null, 'amount_precision', 'amount_precision'],
            ['+50000', null, 'amount_malformed', 'amount_malformed'],
            ['5e4', null, 'amount_malformed', 'amount_malformed'],
            ['50000,00', null, 'amount_malformed', 'amount_malformed'],
            ['50 000', null, 'amount_malformed', 'amount_malformed'],
            ['50000', 'USD', 'currency_mismatch', 'currency_mismatch'],
            ['50000', 'IDR', '50000'],
            ['', null, 'amount_empty', 'amount_empty'],
        ];
    }

    public function test_amount_rejection_surfaces_http_400_and_no_state(): void
    {
        $fields = $this->signedFields(grossAmount: '+50000');
        $this->assertRejected(400, 'amount_malformed', $this->j($fields));
    }

    private function assertExpectedRuntime(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Duplicate JSON object key.', $exception->getMessage());
        }
    }
}
