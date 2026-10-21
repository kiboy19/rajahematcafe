<?php

namespace App\Payments\Support;

interface Clock
{
    public function now(): \DateTimeImmutable;
}