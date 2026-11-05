@php use App\Support\Money; use App\Support\Phone; use App\Support\RegNo; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Quotation {{ $q->number }}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111111; line-height: 1.45; }
    .band { background: #111111; color: #fff; padding: 22px 36px 18px; }
    .band table { width: 100%; border-collapse: collapse; }
    .band img { height: 58px; }
    .band .title { text-align: right; vertical-align: bottom; }
    .band .title .word { font-size: 22px; font-weight: bold; letter-spacing: 3px; }
    .band .title .no { font-size: 11px; color: #D4D4D8; margin-top: 2px; }
    .yellow { height: 4px; background: #FFD51C; }
    .wrap { padding: 22px 36px 0; }
    .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .meta td { vertical-align: top; padding: 0; }
    .lbl { font-size: 8px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; color: #52525B; margin-bottom: 4px; }
    .big { font-size: 13px; font-weight: bold; }
    .muted { color: #52525B; }
    .box { border: 1px solid #E4E4E7; border-radius: 6px; padding: 10px 12px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.items th { background: #F4F4F5; font-size: 8px; letter-spacing: 1px; text-transform: uppercase; color: #3F3F46; text-align: left; padding: 7px 8px; border-bottom: 1px solid #E4E4E7; }
    table.items td { padding: 9px 8px; border-bottom: 1px solid #E4E4E7; vertical-align: top; }
    .r { text-align: right; }
    table.items th.r { text-align: right; } table.items th.c { text-align: center; }
    .c { text-align: center; }
    .strike { text-decoration: line-through; color: #71717A; font-size: 9px; }
    .save { color: #15803D; font-size: 9px; }
    .totals { width: 46%; margin-left: 54%; border-collapse: collapse; margin-top: 12px; }
    .totals td { padding: 4px 8px; }
    .totals .grand td { border-top: 2px solid #111111; padding-top: 8px; font-size: 15px; font-weight: bold; }
    .grand .bar { border-bottom: 3px solid #FFD51C; }
    .savebox { margin-top: 12px; border: 1px solid #BBF7D0; background: #F0FDF4; color: #15803D; border-radius: 6px; padding: 8px 12px; font-weight: bold; }
    .valid { margin-top: 16px; border: 1px solid #111111; border-radius: 6px; padding: 8px 12px; font-weight: bold; }
    .terms { margin-top: 18px; }
    .terms li { margin-bottom: 2px; color: #3F3F46; }
    .foot { position: fixed; bottom: 0; left: 0; right: 0; padding: 10px 36px; border-top: 1px solid #E4E4E7; font-size: 8.5px; color: #52525B; }
</style>
</head>
<body>
<div class="band">
    <table><tr>
        <td>@if ($logo)<img src="{{ $logo }}" alt="{{ $shop['name'] }}">@else<span class="big">{{ $shop['name'] }}</span>@endif</td>
        <td class="title">
            <div class="word">QUOTATION</div>
            <div class="no">{{ $q->number }}@if ($q->version > 1) · Revision {{ $q->version }}@endif</div>
        </td>
    </tr></table>
</div>
<div class="yellow"></div>

<div class="wrap">
    <table class="meta"><tr>
        <td style="width:50%; padding-right:10px;">
            <div class="box">
                <div class="lbl">Quotation for</div>
                <div class="big">{{ $q->customer->name }}</div>
                <div>{{ Phone::pretty($q->customer->mobile) }}</div>
                @if ($q->car)
                    <div style="margin-top:4px;"><strong>{{ trim($q->car->make.' '.$q->car->model.' '.$q->car->variant) }}</strong>@if ($q->car->reg_no) · {{ RegNo::pretty($q->car->reg_no) }}@endif</div>
                @endif
            </div>
        </td>
        <td style="width:50%; padding-left:10px;">
            <div class="box">
                <table style="width:100%; border-collapse:collapse;">
                    <tr><td class="muted">Date</td><td class="r"><strong>{{ $q->created_at->timezone('Asia/Kolkata')->format('j M Y') }}</strong></td></tr>
                    <tr><td class="muted">Valid until</td><td class="r"><strong>{{ $q->valid_until->format('j M Y') }}</strong></td></tr>
                    <tr><td class="muted">Prepared by</td><td class="r">{{ $q->assignee?->name ?? '—' }}</td></tr>
                    @if ($shop['gstin'])<tr><td class="muted">GSTIN</td><td class="r">{{ $shop['gstin'] }}</td></tr>@endif
                </table>
            </div>
        </td>
    </tr></table>

    <table class="items">
        <thead><tr>
            <th style="width:4%;">#</th>
            <th>Item</th>
            <th class="c" style="width:8%;">Qty</th>
            <th class="r" style="width:18%;">Price (incl. GST)</th>
            <th class="r" style="width:18%;">Amount</th>
        </tr></thead>
        <tbody>
        @foreach ($q->items as $i => $item)
            @php $saving = \App\Services\Quotations\QuoteTotals::lineSavings($item->qty, $item->unit_price_paise, $item->mrp_paise); @endphp
            <tr>
                <td class="muted">{{ $i + 1 }}</td>
                <td><strong>{{ $item->name }}</strong>
                    <div class="muted" style="font-size:8.5px;">GST {{ rtrim(rtrim((string) $item->gst_rate, '0'), '.') }}%@if ($item->hsn) · HSN {{ $item->hsn }}@endif</div>
                </td>
                <td class="c">{{ $item->qty }}</td>
                <td class="r">
                    @if ($saving > 0)<div class="strike">MRP {{ Money::format($item->mrp_paise) }}</div>@endif
                    {{ Money::format($item->unit_price_paise) }}
                </td>
                <td class="r"><strong>{{ Money::format($item->line_total_paise) }}</strong>
                    @if ($saving > 0)<div class="save">You save {{ Money::format($saving) }}</div>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ($q->savings_paise > 0)
            <tr><td class="muted">MRP total</td><td class="r"><span class="strike" style="font-size:10px;">{{ Money::format($q->total_paise + $q->savings_paise) }}</span></td></tr>
        @endif
        <tr><td class="muted">Taxable value</td><td class="r">{{ Money::format($q->taxable_paise, true) }}</td></tr>
        <tr><td class="muted">GST</td><td class="r">{{ Money::format($q->gst_paise, true) }}</td></tr>
        <tr class="grand"><td>Total (incl. GST)</td><td class="r"><span class="bar">{{ Money::format($q->total_paise) }}</span></td></tr>
    </table>

    @if ($q->savings_paise > 0)
        <div class="savebox">MRP {{ Money::format($q->total_paise + $q->savings_paise) }} → our price {{ Money::format($q->total_paise) }}. You save {{ Money::format($q->savings_paise) }} on this quote.</div>
    @endif

    <div class="valid">This quotation is valid until {{ $q->valid_until->format('l, j F Y') }}.</div>

    @if ($q->notes)
        <div style="margin-top:12px;"><div class="lbl">Note</div>{{ $q->notes }}</div>
    @endif

    @if ($terms)
        <div class="terms">
            <div class="lbl">Terms</div>
            <ul style="margin:0; padding-left:14px;">@foreach ($terms as $t)<li>{{ $t }}</li>@endforeach</ul>
        </div>
    @endif
</div>

<div class="foot">
    <strong style="color:#111111;">{{ $shop['name'] }}</strong> · {{ $shop['address'] }}@if ($shop['phone']) · {{ $shop['phone'] }}@endif @if ($shop['email']) · {{ $shop['email'] }}@endif
</div>
</body>
</html>
