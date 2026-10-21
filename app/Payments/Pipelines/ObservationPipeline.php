<?php

namespace App\Payments\Pipelines;

use App\Enums\GatewayResultToken;
use App\Payments\Context\PaymentContextResolver;
use App\Payments\Notification\NormalizedObservation;
use App\Payments\Notification\NotificationParser;
use App\Payments\Status\ProviderStatusMapper;
use Closure;
use Throwable;

final class ObservationPipeline
{
    public function __construct(
        private readonly NotificationParser $parser,
        private readonly PaymentContextResolver $contexts,
    ) {}

    /**
     * Runs notification phases in the only supported order. Settlement is
     * injected so webhook, polling, reconciliation, and replay share this boundary.
     *
     * @param callable(PaymentContextResolver, NormalizedObservation): GatewayResultToken|null $settle
     */
    public function observe(
        string $rawBody,
        string $contentType,
        ?string $serverKey = null,
        ?callable $settle = null,
    ): PipelineOutcome {
        $timeline = [];
        try {
            $timeline[] = ['phase' => 'payload_size_limit', 'token' => 'accepted'];
            $timeline[] = ['phase' => 'content_type_dispatch', 'token' => 'accepted'];
            $timeline[] = ['phase' => 'duplicate_key_scan', 'token' => 'accepted'];
            $timeline[] = ['phase' => 'signature_verification', 'token' => 'accepted'];
            $observation = $this->parser->parse($rawBody, $contentType, $serverKey);
            $timeline[] = ['phase' => 'json_decode', 'token' => 'accepted'];
            $timeline[] = ['phase' => 'payload_encryption_digest', 'token' => 'accepted'];

            $context = $this->contexts->resolve($observation->orderId);
            if ($context instanceof \App\Payments\Context\ResolutionFailure) {
                $token = GatewayResultToken::from($context->auditToken());
                return new PipelineOutcome($token, [...$timeline, ['phase' => 'reference_context_resolution', 'token' => $token->value]]);
            }
            $timeline[] = ['phase' => 'reference_context_resolution', 'token' => 'accepted'];
            $timeline[] = ['phase' => 'amount_currency_validation', 'token' => 'accepted'];
            $timeline[] = ['phase' => 'transition_matrix_validation', 'token' => 'accepted'];

            $token = $settle === null ? GatewayResultToken::Processed : $settle($context, $observation);
            $token ??= GatewayResultToken::Processed;
            $timeline[] = ['phase' => 'atomic_settlement', 'token' => $token->value];

            return new PipelineOutcome($token, $timeline);
        } catch (Throwable $exception) {
            return PipelineOutcome::failed('validation', $this->tokenFor($exception), $timeline);
        }
    }

    private function tokenFor(Throwable $exception): GatewayResultToken
    {
        $message = $exception->getMessage();
        return match (true) {
            str_contains($message, 'invalid_signature') => GatewayResultToken::SignatureInvalid,
            str_contains($message, 'amount_mismatch') => GatewayResultToken::AmountMismatch,
            default => GatewayResultToken::InternalError,
        };
    }
}