<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBillingSettingsRequest;
use App\Models\AuditLog;
use App\Models\BillingSetting;
use App\Services\BillingSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class BillingSettingsController extends Controller
{
    public function edit(): JsonResponse
    {
        $settings = app(BillingSettingsService::class)->current();

        return response()->json(['data' => $this->toPayload($settings)]);
    }

    public function update(UpdateBillingSettingsRequest $request, BillingSettingsService $service): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $taxBps = (int) round((float) $validated['tax_percent'] * 100);
        $serviceBps = (int) round((float) $validated['service_percent'] * 100);

        $settings = DB::transaction(function () use ($request, $validated, $taxBps, $serviceBps): BillingSetting {
            $settings = BillingSetting::query()->first() ?? new BillingSetting;

            $before = $settings->exists ? [
                'tax_name' => $settings->tax_name,
                'tax_bps' => $settings->tax_bps,
                'tax_enabled' => $settings->tax_enabled,
                'service_name' => $settings->service_name,
                'service_bps' => $settings->service_bps,
                'service_enabled' => $settings->service_enabled,
            ] : null;

            $settings->fill([
                'tax_name' => trim($validated['tax_name']),
                'tax_bps' => $taxBps,
                'tax_enabled' => $request->boolean('tax_enabled'),
                'service_name' => trim($validated['service_name']),
                'service_bps' => $serviceBps,
                'service_enabled' => $request->boolean('service_enabled'),
                'updated_by' => $request->user()->id,
            ])->save();

            AuditLog::record('billing_settings.updated', $settings, before: $before, after: [
                'tax_name' => $settings->tax_name,
                'tax_bps' => $settings->tax_bps,
                'tax_enabled' => $settings->tax_enabled,
                'service_name' => $settings->service_name,
                'service_bps' => $settings->service_bps,
                'service_enabled' => $settings->service_enabled,
            ], reason: $validated['reason'] ?? null, actor: $request->user(), ipAddress: $request->ip());

            return $settings;
        });

        $service->clearCache();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->toPayload($settings->fresh()),
                'message' => 'Konfigurasi pajak & service charge diperbarui.',
            ]);
        }

        return redirect()->route('settings.billing.edit')->with('success', 'Konfigurasi pajak & service charge diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(BillingSetting $settings): array
    {
        return [
            'tax_name' => $settings->tax_name,
            'tax_bps' => $settings->tax_bps,
            'tax_percent' => $settings->tax_bps / 100,
            'tax_enabled' => (bool) $settings->tax_enabled,
            'service_name' => $settings->service_name,
            'service_bps' => $settings->service_bps,
            'service_percent' => $settings->service_bps / 100,
            'service_enabled' => (bool) $settings->service_enabled,
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }
}
