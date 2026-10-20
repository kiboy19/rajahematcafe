<?php

namespace App\Http\Controllers;

use App\Models\CafeTable;
use Illuminate\View\View;

class CafeTableController extends Controller
{
    /**
     * Lembar QR meja: daftar URL yang harus dicetak/ditempel di tiap meja.
     */
    public function qr(): View
    {
        return view('tables.qr', [
            'tables' => CafeTable::query()->orderBy('label')->get(),
        ]);
    }
}
