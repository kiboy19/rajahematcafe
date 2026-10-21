<?php

namespace App\Payments\Support;

use InvalidArgumentException;

final class MoneyCalculator
{
    public function __construct(private readonly BCMathOps $math = new NativeBCMathOps)
    {
    }

    public function add(string $left, string $right): string
    {
        return $this->format($this->math->add($left, $right));
    }

    public function sub(string $left, string $right): string
    {
        return $this->format($this->math->sub($left, $right));
    }

    public function mul(string $left, string $right): string
    {
        return $this->format($this->math->mul($left, $right));
    }

    public function div(string $left, string $right): string
    {
        if ($this->math->comp($right, '0') === 0) {
            throw new InvalidArgumentException('Division by zero is not allowed.');
        }

        return $this->format($this->math->div($left, $right));
    }

    public function comp(string $left, string $right): int
    {
        return $this->math->comp($left, $right);
    }

    public function format(string $amount): string
    {
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a decimal string.');
        }

        return number_format((float) $amount, 2, '.', '');
    }

    public function assertIdrZeroCents(string $amount): string
    {
        $formatted = $this->format($amount);
        if (substr($formatted, -3) !== '.00') {
            throw new InvalidArgumentException('IDR amounts must have zero cents.');
        }

        return $formatted;
    }
}
