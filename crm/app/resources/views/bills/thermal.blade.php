@php
    use App\Support\Money;
    use App\Support\Phone;
    $i = $invoice;
    $m = fn (int $p) => Money::format($p, true);
    $tax = $i->isTaxInvoice();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $i->number }} · 80 mm</title>
@include('bills.partials.web-fonts')
<style>
    /* 80 mm thermal roll: any printer driver works; the page is as long as the bill. */
    @page { size: 80mm auto; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; background: #e5e5e5; }
    body { font-family: 'Noto Sans', system-ui, sans-serif; font-size: 11.5px; font-weight: 400; color: #000; line-height: 1.35; font-variant-numeric: tabular-nums; }
    .roll { width: 80mm; margin: 12px auto; background: #fff; padding: 4mm 4mm 6mm; }
    .c { text-align: center; }
    .r { text-align: right; white-space: nowrap; }
    .b { font-weight: 600; }
    .m { font-weight: 500; }
    .big { font-size: 15px; font-weight: 600; }
    .sm { font-size: 10px; }
    .rule { border-top: 1px dashed #000; margin: 5px 0; }
    .rule2 { border-top: 2px solid #000; margin: 5px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 1px 0; vertical-align: top; }
    .item td { padding-top: 3px; }
    s { color: #444; }
    .stamp { border: 2px solid #000; padding: 2px 0; margin: 4px 0; font-size: 16px; font-weight: 700; letter-spacing: 3px; }
    .save { border: 1px solid #000; padding: 3px 4px; margin: 4px 0; font-weight: 700; }
    .noprint { width: 80mm; margin: 12px auto 0; display: flex; gap: 8px; font-family: system-ui, sans-serif; }
    .noprint button, .noprint a { flex: 1; height: 40px; border-radius: 8px; border: 1px solid #d4d4d8; background: #fff; font-weight: 600; font-size: 14px; color: #111; text-decoration: none; display: grid; place-items: center; cursor: pointer; }
    .noprint button.primary { background: #FFD51C; border-color: #FFD51C; }
    @media print {
        html, body { background: #fff; }
        .roll { margin: 0; width: 80mm; padding: 2mm 3mm 6mm; }
        .noprint { display: none; }
    }
</style>
</head>
<body>
<div class="noprint">
    <button class="primary" data-print>Print receipt</button>
    <a href="{{ route('bills.show', $i) }}">Back to bill</a>
</div>
<div class="roll">
    <div class="c big">{{ $shop['name'] }}</div>
    <div class="c sm">{{ $shop['address'] }}</div>
    @if ($shop['phone'])<div class="c sm">Ph {{ $shop['phone'] }}</div>@endif
    @if ($shop['gstin'])<div class="c sm">GSTIN {{ $shop['gstin'] }}</div>@endif
    <div class="rule2"></div>
    <div class="c b">{{ $tax ? 'TAX INVOICE' : 'BILL OF SUPPLY' }}</div>
    @if ($i->isCancelled())<div class="c stamp">CANCELLED</div>@endif
    <table>
        <tr><td>Bill</td><td class="r b">{{ $i->number }}</td></tr>
        <tr><td>Date</td><td class="r">{{ $i->created_at->timezone('Asia/Kolkata')->format('d M Y h:i A') }}</td></tr>
        <tr><td>Customer</td><td class="r">{{ $i->customer_name }}</td></tr>
        @if ($i->customer_mobile)<tr><td>Mobile</td><td class="r">{{ Phone::pretty($i->customer_mobile) }}</td></tr>@endif
        @if ($i->car_label)<tr><td>Car</td><td class="r">{{ $i->car_label }}</td></tr>@endif
    </table>
    <div class="rule"></div>
    <table>
        @foreach ($i->items as $item)
            <tr class="item"><td colspan="2" class="m">{{ $item->name }}</td></tr>
            <tr>
                <td>{{ $item->qty }} x {{ $m($item->unitInclPaise()) }}@if ($item->shownMrpPaise()) <s>MRP {{ Money::format($item->shownMrpPaise()) }}</s>@endif
                    @if ($tax)<br><span class="sm">HSN {{ $item->hsn ?: '-' }} · GST {{ $item->rateLabel() }}%</span>@endif
                </td>
                <td class="r b">{{ $m($item->line_total_paise) }}</td>
            </tr>
        @endforeach
    </table>
    <div class="rule"></div>
    <table>
        <tr><td>Taxable value</td><td class="r">{{ $m($i->subtotal_paise) }}</td></tr>
        @if ($tax)
            @foreach ($hsn as $h)
                @if ($h['tax_paise'] > 0)
                    @php $rate = rtrim(rtrim(number_format($h['gst_rate'], 2), '0'), '.'); @endphp
                    @if ($i->igst_paise === 0)
                        <tr class="sm"><td>CGST {{ $rate / 2 }}% + SGST {{ $rate / 2 }}% @if ($h['hsn'])({{ $h['hsn'] }})@endif</td><td class="r">{{ $m($h['tax_paise']) }}</td></tr>
                    @else
                        <tr class="sm"><td>IGST {{ $rate }}% @if ($h['hsn'])({{ $h['hsn'] }})@endif</td><td class="r">{{ $m($h['tax_paise']) }}</td></tr>
                    @endif
                @endif
            @endforeach
        @endif
        @if ($i->round_off_paise)<tr><td>Round off</td><td class="r">{{ ($i->round_off_paise > 0 ? '+' : '').$m($i->round_off_paise) }}</td></tr>@endif
    </table>
    <div class="rule2"></div>
    <table><tr><td class="big">TOTAL</td><td class="r big">{{ Money::format($i->total_paise) }}</td></tr></table>
    @if ($i->savingsLine())
        <div class="save c">You saved {{ Money::format($i->totalSavingsPaise()) }}<br><span class="sm">MRP {{ Money::format($i->mrpValuePaise()) }} → you pay {{ Money::format($i->total_paise) }}</span></div>
    @endif
    <div class="rule"></div>
    <table>
        @foreach ($i->allocations as $a)
            @continue(! $a->payment)
            <tr><td>{{ $a->payment->kind === 'refund' ? 'Refund ' : 'Paid ' }}{{ $a->payment->modeLabel() }}</td><td class="r">{{ $m($a->amount_paise) }}</td></tr>
        @endforeach
        @if ($i->due_paise > 0)<tr class="b"><td>BALANCE DUE</td><td class="r">{{ Money::format($i->due_paise) }}</td></tr>@endif
    </table>
    <div class="rule"></div>
    @if ($shop['footer'])<div class="c sm">{{ $shop['footer'] }}</div>@endif
    <div class="c sm" style="margin-top:4px;">Computer-generated bill · Billed by {{ \Illuminate\Support\Str::before(trim((string) $i->creator?->name), ' ') }}</div>
    <div class="c sm">Thank you. Visit again!</div>
</div>
@unless (request()->boolean('preview'))
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">window.addEventListener('load', () => setTimeout(() => window.print(), 300)); document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));</script>
@endunless
</body>
</html>
