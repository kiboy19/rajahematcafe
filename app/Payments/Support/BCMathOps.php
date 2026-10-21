<?php

namespace App\Payments\Support;

/**
 * Thin, injectable wrapper over the bcmath extension so money arithmetic can be
 * substituted in unit tests without a running database or a globally
 * configured bcmath scale (R5.4).
 */
interface BCMathOps
{
    public function add(string $left, string $right): string;

    public function sub(string $left, string $right): string;

    public function mul(string $left, string $right): string;

    public function div(string $left, string $right): string;

    public function comp(string $left, string $right): int;
}
