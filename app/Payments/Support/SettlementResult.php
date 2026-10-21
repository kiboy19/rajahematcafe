<?php

namespace App\Payments\Support;

enum SettlementResult: string
{
    case Credited = 'credited';
    case DuplicateIgnored = 'duplicate_ignored';
    case Overpayment = 'overpayment';
    case InternalError = 'internal_error';
    case InvalidTransition = 'invalid_transition';

    public function isSuccess(): bool
    {
        return match ($this) {
            self::Credited, self::DuplicateIgnored, self::Overpayment => true,
            default => false,
        };
    }

    public function isTerminalError(): bool
    {
        return match ($this) {
            self::InternalError, self::InvalidTransition => true,
            default => false,
        };
    }
}