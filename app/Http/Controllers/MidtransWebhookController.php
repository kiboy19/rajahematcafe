<?php

namespace App\Http\Controllers;

use App\Payments\Environment\EnvironmentClass;
use App\Payments\Environment\FailClosedGuard;
use App\Enums\GatewayResultToken;
use App\Exceptions\PreCommitTimeoutException;
use App\Services\AuditRecorder;
use App\Services\MidtransGatewayService;
use App\Support\Clock;
use App\Support\GatewayResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class MidtransWebhookController extends Controller
{
    /**
     * Endpoint notifikasi Midtrans. Wajib verifikasi signature dan idempotent:
     * replay dengan payload sama tidak menambah saldo/pembayaran kedua kali.
     */
    public function __invoke(
        Request $request,
        MidtransGatewayService $gateway,
        AuditRecorder $recorder,
        Clock $clock
    ): JsonResponse {
        $startMs = $clock->nowMs();

        if (EnvironmentClass::isPaused()) {
            return response()->json(FailClosedGuard::webhookEnvelope(), 503);
        }

        if (FailClosedGuard::blocksWebhook()) {
            return response()->json(FailClosedGuard::webhookEnvelope(), 503);
        }

        if (! EnvironmentClass::isEnabled()) {
            return response()->json(['message' => 'Gateway tidak aktif.'], 503);
        }

        $payload = $request->all();

        try {
            $result = $gateway->processNotification($payload, $startMs);

            // R13.2: Timeout pasca-commit hanya logging, respons sudah fix.
            if ((($clock->nowMs() - $startMs) / 1000) > (int) config('pos.midtrans.webhook_timeout_seconds', 5)) {
                \Log::warning('Webhook gateway committed after timeout window', [
                    'order_id' => $payload['order_id'] ?? null,
                    'elapsed_ms' => $clock->nowMs() - $startMs,
                ]);
            }

            return GatewayResponse::for($result->token);
        } catch (PreCommitTimeoutException $exception) {
            return $this->respondToRollback($recorder, $payload, $clock->nowMs() - $startMs);
        } catch (ValidationException $exception) {
            // Signature tidak valid: terminal, tidak ada efek bisnis (200).
            return GatewayResponse::for(GatewayResultToken::SignatureInvalid);
        } catch (Throwable $exception) {
            // Kegagalan infrastruktur / outbox insert gagal => rollback inti
            // (data bisnis tidak ikut commit), provider retry layak (503).
            \Log::error('Webhook gateway internal error', [
                'exception' => $exception->getMessage(),
            ]);

            return GatewayResponse::for(GatewayResultToken::InternalError);
        }
    }

    /**
     * Transaksi bisnis + claim di-rollback. Tulis claim provider_timeout di
     * transaksi BARU (row audit tetap tercatat, efek bisnis tidak).
     */
    private function respondToRollback(
        AuditRecorder $recorder,
        array $payload,
        int $elapsedMs
    ): JsonResponse {
        $providerKey = ($payload['order_id'] ?? 'unknown').':'.($payload['transaction_status'] ?? 'unknown').':'.($payload['status_code'] ?? 'unknown');
        $digest = hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        DB::transaction(function () use ($recorder, $providerKey, $digest, $elapsedMs) {
            $recorder->claim(
                $providerKey,
                GatewayResultToken::ProviderTimeout->value,
                $digest,
                ['event_type' => 'timeout', 'elapsed_ms' => $elapsedMs]
            );
        });

        return GatewayResponse::for(GatewayResultToken::ProviderTimeout);
    }
}

