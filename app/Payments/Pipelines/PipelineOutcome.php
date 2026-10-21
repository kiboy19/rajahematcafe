<?php

namespace App\Payments\Pipelines;

use App\Enums\GatewayResultToken;

final readonly class PipelineOutcome
{
    /**
     * @param  array<array{phase: string, token: string}>  $timeline
     */
    public function __construct(
        public GatewayResultToken $token,
        public array $timeline = [],
        public ?int $httpStatus = null,
    ) {}

    public function status(): int
    {
        return $this->httpStatus ?? $this->token->httpStatus();
    }

    public static function failed(string $phase, GatewayResultToken $token, array $timeline = []): self
    {
        $timeline[] = ['phase' => $phase, 'token' => $token->value];

        return new self($token, $timeline);
    }
}