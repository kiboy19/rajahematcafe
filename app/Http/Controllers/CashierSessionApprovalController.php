<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashierSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashierSessionApprovalController extends Controller
{
    public function update(Request $request, CashierSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'approval_note' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $session, $validated): void {
            $session = CashierSession::query()->lockForUpdate()->findOrFail($session->id);

            abort_unless($session->status === 'closed' && (int) $session->variance !== 0, 409,
                'Sesi harus sudah ditutup dan memiliki selisih untuk diverifikasi.');

            abort_if($session->cashier_id === $request->user()->id, 403,
                'Kasir tidak dapat memverifikasi selisih sesi miliknya sendiri.');

            abort_if($session->variance_verified_at !== null, 409,
                'Selisih sesi ini sudah diverifikasi.');

            $session->update([
                'variance_verified_by' => $request->user()->id,
                'variance_verified_at' => now(),
            ]);

            AuditLog::record('cashier_session.variance_verified', $session, after: [
                'variance' => $session->variance,
                'verified_by' => $request->user()->id,
            ], reason: $validated['approval_note'], actor: $request->user());
        });

        return back()->with('success', 'Selisih sesi kasir berhasil diverifikasi.');
    }
}
