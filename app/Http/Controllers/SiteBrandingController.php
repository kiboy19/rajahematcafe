<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSiteBrandingRequest;
use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Services\SiteSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SiteBrandingController extends Controller
{
    public function edit(SiteSettingsService $service): JsonResponse
    {
        return response()->json(['data' => $service->toPayload($service->current())]);
    }

    public function update(UpdateSiteBrandingRequest $request, SiteSettingsService $service): RedirectResponse|JsonResponse
    {
        $settings = DB::transaction(function () use ($request, $service): SiteSetting {
            $settings = SiteSetting::query()->first() ?? new SiteSetting;
            $before = $service->toPayload($settings);
            $validated = $request->validated();

            $settings->fill(collect($validated)->except([
                'logo', 'favicon', 'remove_logo', 'remove_favicon',
            ])->all());
            $settings->updated_by = $request->user()->id;

            $this->replaceImage($request, $settings, 'logo', 'logo_path', 'branding');
            $this->replaceImage($request, $settings, 'favicon', 'favicon_path', 'branding');

            if ($request->boolean('remove_logo') && ! $request->hasFile('logo')) {
                $this->removeImage($settings, 'logo_path');
            }

            if ($request->boolean('remove_favicon') && ! $request->hasFile('favicon')) {
                $this->removeImage($settings, 'favicon_path');
            }

            $settings->save();

            AuditLog::record('site_branding.updated', $settings, before: $before, after: $service->toPayload($settings), actor: $request->user(), ipAddress: $request->ip());

            return $settings;
        });

        $service->clearCache();
        $payload = $service->toPayload($settings->fresh());

        if ($request->expectsJson()) {
            return response()->json(['data' => $payload, 'message' => 'Branding website berhasil diperbarui.']);
        }

        return redirect()->route('settings.branding.edit')->with('success', 'Branding website berhasil diperbarui.');
    }

    private function replaceImage(UpdateSiteBrandingRequest $request, SiteSetting $settings, string $input, string $column, string $directory): void
    {
        if (! $request->hasFile($input)) {
            return;
        }

        $this->removeImage($settings, $column);
        $settings->{$column} = $request->file($input)->store($directory, 'public');
    }

    private function removeImage(SiteSetting $settings, string $column): void
    {
        if ($settings->{$column}) {
            Storage::disk('public')->delete($settings->{$column});
            $settings->{$column} = null;
        }
    }
}
