<?php

namespace App\Payments\Midtrans;

use RuntimeException;

final class MalformedProviderPayloadException extends RuntimeException
{
    public function __construct(string $message = 'Payload could not be parsed as JSON', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
