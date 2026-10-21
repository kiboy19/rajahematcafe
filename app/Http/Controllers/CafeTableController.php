<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCafeTableRequest;
use App\Http\Requests\UpdateCafeTableRequest;
use App\Models\AuditLog;
use App\Models\CafeTable;
use App\Models\Order;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CafeTableController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => CafeTable::query()->orderBy('label')->get()]);
    }

    public function store(StoreCafeTableRequest $request): RedirectResponse|JsonResponse
    {
        $table = CafeTable::query()->create([
            'label' => trim($request->validated('label')),
            'seats' => (int) $request->validated('seats'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLog::record('table.created', $table, after: [
            'label' => $table->label,
            'seats' => $table->seats,
            'is_active' => $table->is_active,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $table, 'message' => 'Meja '.$table->label.' berhasil ditambahkan.'], 201);
        }

        return redirect()->route('tables.index')->with('success', 'Meja '.$table->label.' berhasil ditambahkan.');
    }

    public function update(UpdateCafeTableRequest $request, CafeTable $table): RedirectResponse|JsonResponse
    {
        $before = [
            'label' => $table->label,
            'seats' => $table->seats,
            'is_active' => $table->is_active,
        ];

        $table->update([
            'label' => trim($request->validated('label')),
            'seats' => (int) $request->validated('seats'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $table->is_active,
        ]);

        AuditLog::record('table.updated', $table, before: $before, after: [
            'label' => $table->label,
            'seats' => $table->seats,
            'is_active' => $table->is_active,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $table->fresh(), 'message' => 'Meja '.$table->label.' berhasil diperbarui.']);
        }

        return redirect()->route('tables.index')->with('success', 'Meja '.$table->label.' berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, CafeTable $table): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $table);

        if ($table->is_active && $this->hasActiveOrders($table)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Meja masih memiliki pesanan aktif dan tidak dapat dinonaktifkan.'], 422);
            }

            return back()->withErrors(['table' => 'Meja masih memiliki pesanan aktif dan tidak dapat dinonaktifkan.']);
        }

        $before = $table->is_active;
        $table->update(['is_active' => ! $before]);

        AuditLog::record('table.status_changed', $table, before: [
            'is_active' => $before,
        ], after: [
            'is_active' => $table->is_active,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $table->fresh(), 'message' => 'Status meja '.$table->label.' diubah menjadi '.($table->is_active ? 'Aktif' : 'Nonaktif').'.']);
        }

        return back()->with('success', 'Status meja '.$table->label.' diubah menjadi '.($table->is_active ? 'Aktif' : 'Nonaktif').'.');
    }

    public function destroy(Request $request, CafeTable $table): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $table);

        if (Order::query()->where('table_label', $table->label)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Meja memiliki riwayat pesanan. Nonaktifkan meja sebagai gantinya.'], 422);
            }

            return back()->withErrors(['table' => 'Meja memiliki riwayat pesanan. Nonaktifkan meja sebagai gantinya.']);
        }

        $label = $table->label;
        $table->delete();

        AuditLog::record('table.deleted', null, before: ['label' => $label], reason: 'Meja dihapus dari master data', actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Meja '.$label.' berhasil dihapus.']);
        }

        return redirect()->route('tables.index')->with('success', 'Meja '.$label.' berhasil dihapus.');
    }

    /**
     * Lembar QR meja: daftar URL yang harus dicetak/ditempel di tiap meja.
     */
    public function qr(): JsonResponse
    {
        return response()->json(['data' => ['tables' => $this->qrPayload()]]);
    }

    /**
     * QR bocor/dicuri: terbitkan token baru dan batalkan yang lama.
     */
    public function rotateQr(Request $request, CafeTable $table): RedirectResponse|JsonResponse
    {
        $this->authorize('rotateQr', $table);

        $before = $table->qr_token;
        $table->rotateQrToken();

        AuditLog::record('table.qr_rotated', $table,
            before: ['token_fingerprint' => hash('sha256', $before)],
            after: ['token_fingerprint' => hash('sha256', $table->qr_token)],
            reason: 'Token QR meja diganti dari halaman QR',
            actor: $request->user(),
            ipAddress: $request->ip(),
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $table->fresh(), 'message' => "Token QR {$table->label} diganti. Cetak ulang QR lama."]);
        }

        return redirect()->route('tables.qr')->with('success', "Token QR {$table->label} diganti. Cetak ulang QR lama.");
    }

    /**
     * SVG QR code untuk satu meja — dipakai oleh template cetak dan halaman QR.
     */
    public function qrImage(CafeTable $table, QrCodeService $qr): Response
    {
        $url = route('customer.table.scan', $table->qr_token);
        $svg = $qr->svg($url);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Lembar cetak: kartu QR per meja (A4), monokrom tanpa blok warna.
     */
    public function qrPrint(): JsonResponse
    {
        return response()->json(['data' => ['tables' => $this->qrPayload()]]);
    }

    private function qrPayload(): array
    {
        return CafeTable::query()->orderBy('label')->get()->map(function ($table) {
            return [
                'id' => $table->id,
                'label' => $table->label,
                'seats' => $table->seats,
                'is_active' => $table->is_active,
                'scan_url' => route('customer.table.scan', $table->qr_token),
            ];
        })->toArray();
    }

    private function hasActiveOrders(CafeTable $table): bool
    {
        return Order::query()
            ->where('table_label', $table->label)
            ->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->exists();
    }
}
