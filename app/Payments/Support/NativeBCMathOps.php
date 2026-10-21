<?php

namespace App\Payments\Support;

/**
 * Default {@see BCMathOps} backed by PHP's bcmath extension. All values are
 * treated as decimal strings; the scale is normalised to 2 so results never
 * carry hidden sub-cent precision.
 */
final class NativeBCMathOps implements BCMathOps
{
    public function __construct(private readonly int $scale = 2)
    {
    }

    public function add(string $left, string $right): string
    {
        return bcadd($left, $right, $this->scale);
    }

    public function sub(string $left, string $right): string
    {
        return bcsub($left, $right, $this->scale);
    }

    public function mul(string $left, string $right): string
    {
        return bcmul($left, $right, $this->scale);
    }

    public function div(string $left, string $right): string
    {
        return bcdiv($left, $right, $this->scale);
    }

    public function comp(string $left, string $right): int
    {
        return bccomp($left, $right, $this->scale);
    }
}
