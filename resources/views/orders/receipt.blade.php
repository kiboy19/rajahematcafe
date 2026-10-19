<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $order->number }} — Rajahemat Cafe</title>
    <style>
        /* 58mm thermal: ~48mm printable width. Tune after hardware spike (Gate 0). */
        @page { size: 58mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 4mm 3mm;
            width: 58mm;
            font: 11px/1.35 "Courier New", monospace;
            color: #000;
            background: #fff;
        }
        .center { text-align: center; }
        .brand { font-size: 14px; font-weight: 700; letter-spacing: .5px; }
        .muted { font-size: 10px; }
        .rule { border-top: 1px dashed #000; margin: 2mm 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 0.4mm 0; }
        .num { text-align: right; white-space: nowrap; padding-left: 2mm; }
        .total { font-size: 13px; font-weight: 700; }
        .toolbar { margin-bottom: 4mm; text-align: center; }
        .toolbar button { font: inherit; padding: 2mm 4mm; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak struk</button>
    </div>

    <div class="center">
        <div class="brand">RAJAHEMAT CAFE</div>
        <div class="muted">Terima kasih atas kunjungan Anda</div>
    </div>

    <div class="rule"></div>

    <table>
        <tr><td>No</td><td class="num">{{ $order->number }}</td></tr>
        <tr><td>Tanggal</td><td class="num">{{ $order->placed_at?->format('d/m/y H:i') }}</td></tr>
        <tr><td>Kasir</td><td class="num">{{ $order->cashier?->name ?? '—' }}</td></tr>
        <tr><td>Layanan</td><td class="num">{{ $order->channel->label() }}{{ $order->table_label ? ' / '.$order->table_label : '' }}</td></tr>
    </table>

    <div class="rule"></div>

    <table>
        @foreach ($order->items as $item)
            <tr>
                <td colspan="2">{{ $item->name }}</td>
            </tr>
            <tr>
                <td class="muted">{{ $item->quantity }} x {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($item->line_total, 0, ',', '.') }}</td>
            </tr>
            @if ($item->note)
                <tr><td colspan="2" class="muted">* {{ $item->note }}</td></tr>
            @endif
        @endforeach
    </table>

    <div class="rule"></div>

    <table>
        <tr><td>Subtotal</td><td class="num">{{ number_format($order->subtotal, 0, ',', '.') }}</td></tr>
        @if ($order->discount > 0)
            <tr><td>Diskon</td><td class="num">-{{ number_format($order->discount, 0, ',', '.') }}</td></tr>
        @endif
        <tr><td>PB1 {{ $order->tax_percent }}%</td><td class="num">{{ number_format($order->tax, 0, ',', '.') }}</td></tr>
        <tr><td>Layanan {{ $order->service_percent }}%</td><td class="num">{{ number_format($order->service, 0, ',', '.') }}</td></tr>
        <tr class="total"><td>TOTAL</td><td class="num">{{ number_format($order->total, 0, ',', '.') }}</td></tr>
    </table>

    <div class="rule"></div>

    <table>
        @forelse ($order->payments->where('status', \App\Enums\PaymentStatus::Paid) as $payment)
            <tr>
                <td>{{ $payment->method->label() }}</td>
                <td class="num">{{ number_format($payment->amount, 0, ',', '.') }}</td>
            </tr>
            @if ($payment->tendered)
                <tr><td class="muted">Tunai</td><td class="num muted">{{ number_format($payment->tendered, 0, ',', '.') }}</td></tr>
                <tr><td class="muted">Kembali</td><td class="num muted">{{ number_format($payment->change, 0, ',', '.') }}</td></tr>
            @endif
        @empty
            <tr><td colspan="2" class="center">BELUM DIBAYAR</td></tr>
        @endforelse
    </table>

    <div class="rule"></div>

    <div class="center muted">
        PB1 dikenakan sesuai peraturan daerah.<br>
        Struk ini adalah bukti pembayaran yang sah.
    </div>
</body>
</html>
