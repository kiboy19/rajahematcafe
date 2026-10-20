<?php

namespace App\Http\Controllers;

use App\Enums\VoucherType;
use App\Http\Requests\StoreVoucherRequest;
use App\Models\AuditLog;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VoucherController extends Controller
{
    /**
     * Daftar voucher + form pembuatan. Hanya owner/manager (lihat route).
     */
    public function index(): View
    {
        return view('vouchers.index', [
            'vouchers' => Voucher::query()->latest('id')->get(),
            'typeLabels' => collect(VoucherType::cases())->mapWithKeys(
                fn (VoucherType $type): array => [$type->value => $type->label()],
            ),
        ]);
    }

    public function store(StoreVoucherRequest $request): RedirectResponse
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

        return redirect()->route('vouchers.index')->with('success', 'Voucher '.$voucher->code.' dibuat dan aktif.');
    }

    public function toggle(Request $request, Voucher $voucher): RedirectResponse
    {
        $voucher->update(['is_active' => ! $voucher->is_active]);

        AuditLog::record('voucher.status_changed', $voucher, after: [
            'is_active' => $voucher->is_active,
        ], actor: $request->user());

        return back()->with('success', 'Voucher '.$voucher->code.' '.($voucher->is_active ? 'diaktifkan' : 'dinonaktifkan').'.');
    }
}
