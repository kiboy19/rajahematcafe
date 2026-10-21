<?php

namespace App\Http\Controllers;

use App\Enums\VoucherType;
use App\Http\Requests\StoreVoucherRequest;
use App\Models\AuditLog;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherController extends Controller
{
    /**
     * Daftar voucher + form pembuatan. Hanya owner/manager (lihat route).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Voucher::query()->latest('id')->get(),
            'typeLabels' => collect(VoucherType::cases())->mapWithKeys(
                fn (VoucherType $type): array => [$type->value => $type->label()],
            ),
        ]);
    }

    public function store(StoreVoucherRequest $request): RedirectResponse|JsonResponse
    {
        $voucher = DB::transaction(function () use ($request): Voucher {
            $voucher = Voucher::query()->create($request->sanitized());

            AuditLog::record('voucher.created', $voucher, after: [
                'code' => $voucher->code,
                'type' => $voucher->type->value,
                'value' => $voucher->value,
                'max_discount' => $voucher->max_discount,
                'quota' => $voucher->quota,
            ], actor: $request->user());

            return $voucher;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $voucher,
                'message' => 'Voucher '.$voucher->code.' dibuat dan aktif.',
            ], 201);
        }

        return redirect()->route('vouchers.index')->with('success', 'Voucher '.$voucher->code.' dibuat dan aktif.');
    }

    public function toggle(Request $request, Voucher $voucher): RedirectResponse|JsonResponse
    {
        $voucher->update(['is_active' => ! $voucher->is_active]);

        AuditLog::record('voucher.status_changed', $voucher, after: [
            'is_active' => $voucher->is_active,
        ], actor: $request->user());

        $message = 'Voucher '.$voucher->code.' '.($voucher->is_active ? 'diaktifkan' : 'dinonaktifkan').'.';

        if ($request->expectsJson()) {
            return response()->json(['data' => $voucher, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
