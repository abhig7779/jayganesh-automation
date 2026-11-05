@php
    use App\Support\Money;
    use App\Support\Phone;
    $i = $invoice;
    $intra = $i->igst_paise === 0;
    $tax = $i->isTaxInvoice();
    $m = fn (int $p) => Money::format($p, true);
    $states = ['27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa', '24' => 'Gujarat', '36' => 'Telangana', '23' => 'Madhya Pradesh', '07' => 'Delhi', '33' => 'Tamil Nadu'];
    $pos = $i->place_of_supply.(isset($states[$i->place_of_supply]) ? ' – '.$states[$i->place_of_supply] : '');
    $title = $tax ? 'TAX INVOICE' : 'BILL OF SUPPLY';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }} {{ $i->number }}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 8.6pt; color: #111; line-height: 1.35; }
    .page { padding: 0 26pt 70pt; }
    .band { background: #111; color: #fff; padding: 18pt 26pt 16pt; }
    .band table { width: 100%; border-collapse: collapse; }
    .band td { vertical-align: top; }
    .logo { height: 46pt; }
    .shop-name { font-size: 13pt; font-weight: bold; letter-spacing: .2pt; }
    .shop-line { color: #D4D4D8; font-size: 7.8pt; }
    .doc-title { font-size: 15pt; font-weight: bold; letter-spacing: 1.5pt; color: #FFD51C; text-align: right; }
    .doc-meta { text-align: right; color: #fff; font-size: 8.4pt; }
    .stripe { height: 4pt; background: #FFD51C; }
    .label { font-size: 6.8pt; font-weight: bold; letter-spacing: .8pt; text-transform: uppercase; color: #52525B; }
    .boxes { width: 100%; border-collapse: collapse; margin-top: 14pt; }
    .boxes td { width: 50%; vertical-align: top; padding: 9pt 11pt; border: 1px solid #E4E4E7; }
    .big { font-size: 11pt; font-weight: bold; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 14pt; }
    table.items th { background: #F4F4F5; font-size: 6.8pt; text-transform: uppercase; letter-spacing: .5pt; color: #3F3F46; text-align: right; padding: 6pt 5pt; border-bottom: 1px solid #D4D4D8; }
    table.items th.l, table.items td.l { text-align: left; }
    table.items td { padding: 6pt 5pt; border-bottom: 1px solid #E4E4E7; text-align: right; vertical-align: top; }
    .muted { color: #52525B; }
    .faint { color: #71717A; font-size: 7.4pt; }
    .strike { text-decoration: line-through; color: #71717A; }
    .bottom { width: 100%; border-collapse: collapse; margin-top: 12pt; }
    .bottom > tbody > tr > td { vertical-align: top; }
    table.sum { width: 100%; border-collapse: collapse; }
    table.sum td { padding: 3pt 0; }
    table.sum td.r { text-align: right; }
    .total-row td { border-top: 1.5px solid #111; padding-top: 6pt; font-size: 12.5pt; font-weight: bold; }
    .save { background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; padding: 6pt 8pt; font-weight: bold; margin: 6pt 0; }
    .words { border: 1px solid #E4E4E7; padding: 7pt 9pt; margin-bottom: 9pt; }
    table.hsn { width: 100%; border-collapse: collapse; margin-top: 4pt; }
    table.hsn th, table.hsn td { border: 1px solid #E4E4E7; padding: 4pt 5pt; text-align: right; font-size: 7.6pt; }
    table.hsn th { background: #F4F4F5; color: #3F3F46; }
    table.hsn .l { text-align: left; }
    .due { color: #DC2626; font-weight: bold; }
    .qr { border: 1px solid #E4E4E7; padding: 6pt; text-align: center; width: 112pt; }
    .footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 10pt 26pt 14pt; border-top: 1px solid #E4E4E7; font-size: 7.4pt; color: #52525B; }
    .stamp { position: fixed; top: 300pt; left: 70pt; width: 450pt; text-align: center; font-size: 64pt; font-weight: bold; color: #DC2626; opacity: .22; transform: rotate(-24deg); border: 6pt solid #DC2626; letter-spacing: 6pt; }
</style>
</head>
<body>
@if ($i->isCancelled())<div class="stamp">CANCELLED</div>@endif

<div class="band">
    <table><tr>
        <td style="width: 62pt;">@if ($logo)<img src="{{ $logo }}" class="logo" alt="">@endif</td>
        <td style="padding-left: 8pt;">
            <div class="shop-name">{{ $shop['name'] }}</div>
            <div class="shop-line">{{ $shop['address'] }}</div>
            <div class="shop-line">@if ($shop['phone'])Phone {{ $shop['phone'] }}@endif @if ($shop['email']) · {{ $shop['email'] }}@endif</div>
            @if ($shop['gstin'])<div class="shop-line" style="color:#fff;">GSTIN <b>{{ $shop['gstin'] }}</b> · State code {{ $shop['state_code'] }}</div>@endif
        </td>
        <td style="width: 170pt;">
            <div class="doc-title">{{ $title }}</div>
            <div class="doc-meta" style="margin-top: 4pt;"><b style="font-size: 11pt;">{{ $i->number }}</b></div>
            <div class="doc-meta">{{ $i->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</div>
            <div class="doc-meta" style="color:#D4D4D8;">Original for recipient</div>
        </td>
    </tr></table>
</div>
<div class="stripe"></div>

<div class="page">
    <table class="boxes"><tr>
        <td>
            <div class="label">Bill to</div>
            <div class="big" style="margin-top: 3pt;">{{ $i->customer_name }}</div>
            @if ($i->customer_mobile)<div>{{ Phone::pretty($i->customer_mobile) }}</div>@endif
            @if ($i->customer?->area)<div class="muted">{{ $i->customer->area }}</div>@endif
            @if ($i->customer_gstin)<div>GSTIN {{ $i->customer_gstin }}</div>@endif
        </td>
        <td>
            <table style="width:100%; border-collapse: collapse;">
                <tr><td style="padding:0 0 3pt; border:0; width:50%;"><span class="label">Car</span><br>{{ $i->car_label ?: '—' }}</td>
                    <td style="padding:0 0 3pt; border:0;"><span class="label">Place of supply</span><br>{{ $pos }}</td></tr>
                <tr><td style="padding:3pt 0 0; border:0;"><span class="label">Billed by</span><br>{{ $i->creator?->name }}</td>
                    <td style="padding:3pt 0 0; border:0;"><span class="label">Status</span><br>@if ($i->isCancelled())<span class="due">Cancelled</span>@elseif ($i->due_paise > 0)<span class="due">Due {{ Money::format($i->due_paise) }}</span>@else Paid @endif</td></tr>
            </table>
        </td>
    </tr></table>

    <table class="items">
        <thead><tr>
            <th class="l" style="width: 14pt;">#</th>
            <th class="l">Item</th>
            <th class="l">HSN/SAC</th>
            <th>Qty</th>
            <th>MRP</th>
            <th>Rate</th>
            @if ($tax)<th>Taxable</th><th>GST</th>@endif
            <th>Amount</th>
        </tr></thead>
        <tbody>
            @foreach ($i->items as $n => $item)
                <tr>
                    <td class="l muted">{{ $n + 1 }}</td>
                    <td class="l"><b>{{ $item->name }}</b>@if ($item->discount_paise)<br><span class="faint">Discount {{ $m($item->discount_paise) }}</span>@endif @if ($item->returned_qty)<br><span class="faint">{{ $item->returned_qty }} returned (credit note)</span>@endif</td>
                    <td class="l muted">{{ $item->hsn ?: '—' }}</td>
                    <td>{{ $item->qty }} {{ $item->unit !== 'pcs' ? $item->unit : '' }}</td>
                    <td>@if ($item->shownMrpPaise())<span class="strike">{{ Money::format($item->shownMrpPaise()) }}</span>@else<span class="faint">—</span>@endif</td>
                    <td>{{ $m($item->unitInclPaise()) }}</td>
                    @if ($tax)
                        <td>{{ $m($item->taxable_paise) }}</td>
                        <td>{{ $item->rateLabel() }}%<br><span class="faint">{{ $m($item->taxPaise()) }}</span></td>
                    @endif
                    <td><b>{{ $m($item->line_total_paise) }}</b></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="bottom"><tr>
        <td style="width: 56%; padding-right: 14pt;">
            <div class="words"><span class="label">Amount in words</span><br><b>{{ $words }}</b></div>

            @if ($tax)
                <span class="label">GST summary</span>
                <table class="hsn">
                    <tr><th class="l">HSN/SAC</th><th>Rate</th><th>Taxable</th>@if ($intra)<th>CGST</th><th>SGST</th>@else<th>IGST</th>@endif<th>Total tax</th></tr>
                    @foreach ($hsn as $h)
                        <tr>
                            <td class="l">{{ $h['hsn'] ?: '—' }}</td>
                            <td>{{ rtrim(rtrim(number_format($h['gst_rate'], 2), '0'), '.') }}%</td>
                            <td>{{ $m($h['taxable_paise']) }}</td>
                            @if ($intra)<td>{{ $m($h['cgst_paise']) }}</td><td>{{ $m($h['sgst_paise']) }}</td>@else<td>{{ $m($h['igst_paise']) }}</td>@endif
                            <td>{{ $m($h['tax_paise']) }}</td>
                        </tr>
                    @endforeach
                </table>
            @else
                <div class="faint">Seller not registered under GST. No tax charged.</div>
            @endif

            <div style="margin-top: 10pt;">
                <span class="label">Payments</span>
                @if ($payments->isEmpty())
                    <div class="muted">Nothing paid yet.</div>
                @else
                    <table class="sum">
                        @foreach ($payments as $p)
                            <tr><td>{{ $p['date']->timezone('Asia/Kolkata')->format('d M') }} · {{ $p['mode'] }}@if ($p['reference']) · Ref {{ $p['reference'] }}@endif</td><td class="r"><b>{{ $m($p['amount']) }}</b></td></tr>
                        @endforeach
                    </table>
                @endif
            </div>
        </td>
        <td>
            <table class="sum">
                <tr><td>Taxable value</td><td class="r">{{ $m($i->subtotal_paise) }}</td></tr>
                @if ($tax)
                    @if ($intra)
                        <tr><td>CGST</td><td class="r">{{ $m($i->cgst_paise) }}</td></tr>
                        <tr><td>SGST</td><td class="r">{{ $m($i->sgst_paise) }}</td></tr>
                    @else
                        <tr><td>IGST</td><td class="r">{{ $m($i->igst_paise) }}</td></tr>
                    @endif
                @endif
                @if ($i->discount_paise)<tr><td class="muted">Discount given (included)</td><td class="r muted">{{ $m($i->discount_paise) }}</td></tr>@endif
                @if ($i->round_off_paise)<tr><td>Round off</td><td class="r">{{ ($i->round_off_paise > 0 ? '+' : '').$m($i->round_off_paise) }}</td></tr>@endif
            </table>
            @if ($i->savingsLine())
                <div class="save">{{ $i->savingsLine() }}</div>
            @endif
            <table class="sum">
                <tr class="total-row"><td>Total</td><td class="r">{{ Money::format($i->total_paise) }}</td></tr>
                <tr><td>Paid</td><td class="r">{{ Money::format($i->paid_paise) }}</td></tr>
                @if ($i->credited_paise)<tr><td>Returned (credit notes)</td><td class="r">{{ $m($i->credited_paise) }}</td></tr>@endif
                @if ($i->due_paise > 0)<tr><td class="due">Balance due</td><td class="r due">{{ Money::format($i->due_paise) }}</td></tr>@endif
            </table>
            @if ($qr)
                <table style="margin-top: 10pt; width: 100%;"><tr><td class="qr">
                    <img src="{{ $qr }}" style="width: 98pt; height: 98pt;" alt="UPI QR"><br>
                    <b>Scan to pay {{ Money::format($i->due_paise) }}</b><br><span class="faint">{{ $shop['upi_id'] }}</span>
                </td></tr></table>
            @endif
        </td>
    </tr></table>
</div>

<div class="footer">
    @if ($shop['footer'])<div style="color:#3F3F46;">{{ $shop['footer'] }}</div>@endif
    <div style="margin-top: 4pt;">This is a computer-generated {{ $tax ? 'invoice' : 'bill' }} and needs no signature. · {{ $i->number }}</div>
</div>
</body>
</html>
