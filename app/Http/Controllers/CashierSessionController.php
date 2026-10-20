<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashierSession;
use App\Models\User;
use App\Services\CashierSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CashierSessionController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $session = CashierSession::openFor($user);

        return view('cashier.session', [
            'session' => $session,
            'cashSales' => $session?->cashSalesTotal() ?? 0,
            'cashTopups' => $session?->cashTopupsTotal() ?? 0,
            'paymentCount' => $session?->payments()->where('status', 'paid')->count() ?? 0,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        if (CashierSession::openFor($request->user())) {
            return back()->withErrors(['opening_float' => 'Sesi kasir Anda masih terbuka. Tutup dulu sebelum membuka sesi baru.']);
        }

        $validated = $request->validate([
            'opening_float' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);

        $session = DB::transaction(function () use ($request, $validated): CashierSession {
            $session = CashierSession::query()->create([
                'cashier_id' => $request->user()->id,
                'opening_float' => (int) $validated['opening_float'],
                'expected_cash' => (int) $validated['opening_float'],
                'status' => 'open',
                'opened_by' => $request->user()->id,
                'opened_at' => now(),
            ]);

            AuditLog::record('cashier_session.opened', $session, after: [
                'opening_float' => $session->opening_float,
            ]);

            return $session;
        });

        return redirect()->route('pos.index')
            ->with('success', 'Sesi kasir dibuka dengan modal awal Rp'.number_format($session->opening_float, 0, ',', '.').'.');
    }

    public function close(Request $request, CashierSessionService $service): RedirectResponse
    {
        $session = CashierSession::openFor($request->user());

        if (! $session) {
            return back()->withErrors(['counted_cash' => 'Tidak ada sesi kasir yang terbuka.']);
        }

        $validated = $request->validate([
            'counted_cash' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'closing_note' => ['nullable', 'string', 'max:255'],
        ]);

        self::guardNoPendingPayments($session);
        $expectedCash = $service->calculateExpectedCash(
            openingFloat: $session->opening_float,
            cashSales: $session->cashSalesTotal(),
            cashTopups: $session->cashTopupsTotal(),
        );

        if ((int) $validated['counted_cash'] !== $expectedCash && blank($validated['closing_note'] ?? null)) {
            return back()->withErrors(['closing_note' => 'Catatan wajib diisi jika terdapat selisih kas.'])->withInput();
        }

        $service->close($session, (int) $validated['counted_cash'], $validated['closing_note'] ?? null, $request->user());

        $variance = (int) $session->variance;
        $message = 'Sesi kasir ditutup. Uang laci tercatat';

        return redirect()->route('cashier.session')->with('success', $variance === 0
            ? $message.'.'
            : $message.', selisih Rp'.number_format(abs($variance), 0, ',', '.').($variance < 0 ? ' (kurang).' : ' (lebih).'));
    }

    public function openSessionFor(?User $user): ?CashierSession
    {
        return CashierSession::openFor($user);
    }

    public function requireOpenSession(User $user): CashierSession
    {
        $session = $this->openSessionFor($user);

        if (! $session) {
            abort(409, 'Buka sesi kasir terlebih dahulu sebelum melayani pembayaran.');
        }

        return $session;
    }

    /**
     * Sesi yang masih punya pembayaran pending tidak boleh ditutup: uangnya belum pasti masuk laci.
     */
    public static function guardNoPendingPayments(CashierSession $session): void
    {
        if ($session->payments()->where('status', 'pending')->exists()) {
            abort(409, 'Ada pembayaran yang masih pending. Selesaikan atau batalkan sebelum tutup kas.');
        }
    }
}
