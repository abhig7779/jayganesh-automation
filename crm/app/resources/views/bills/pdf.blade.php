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
@include('bills.partials.pdf-fonts')
<style>
    /* Typography after Zoho / Tally invoices: Noto Sans 9.5pt / 1.35, headings 600, labels 7.5pt grey. */
    @page { margin: 0; }
    /* dompdf multiplies line-height by the font's own box (Noto Sans = 1.36 em), so 1.0 here gives the 1.35 leading we want. */
    body { margin: 0; font-family: 'Noto Sans', sans-serif; font-size: 9.5pt; font-weight: normal; color: #111111; line-height: 1.0; }
    b, strong, .strong { font-weight: 600; }
    .med { font-family: 'Noto Sans Medium', 'Noto Sans', sans-serif; font-weight: normal; }
    .page { padding: 0 28pt 72pt; }
    .band { background: #111111; color: #ffffff; padding: 18pt 28pt 16pt; }
    .band table { width: 100%; border-collapse: collapse; }
    .band td { vertical-align: top; }
    .logo { height: 46pt; }
    .shop-name { font-size: 13pt; font-weight: 600; }
    .shop-line { color: #D4D4D8; font-size: 8pt; }
    .doc-title { font-size: 14pt; font-weight: 600; letter-spacing: 0.06em; color: #FFD51C; text-align: right; }
    .doc-meta { text-align: right; color: #ffffff; font-size: 8.5pt; }
    .stripe { height: 4pt; background: #FFD51C; }
    .label { font-size: 7.5pt; letter-spacing: 0.06em; text-transform: uppercase; color: #6B7280; }
    .boxes { width: 100%; border-collapse: collapse; margin-top: 14pt; }
    .boxes td { width: 50%; vertical-align: top; padding: 9pt 11pt; border: 1px solid #E4E4E7; }
    .big { font-size: 11pt; font-weight: 600; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 14pt; }
    table.items th { background: #F4F4F5; font-size: 7.5pt; font-weight: normal; text-transform: uppercase; letter-spacing: 0.06em; color: #6B7280; text-align: right; padding: 6pt 5pt; border-bottom: 1px solid #D4D4D8; }
    table.items th.l, table.items td.l { text-align: left; }
    table.items td { padding: 6pt 5pt; border-bottom: 1px solid #E4E4E7; text-align: right; vertical-align: top; }
    .muted { color: #52525B; }
    .faint { color: #6B7280; font-size: 8pt; }
    .strike { text-decoration: line-through; color: #6B7280; }
    .bottom { width: 100%; border-collapse: collapse; margin-top: 12pt; }
    .bottom > tbody > tr > td { vertical-align: top; }
    table.sum { width: 100%; border-collapse: collapse; }
    table.sum td { padding: 2.5pt 0; }
    table.sum td.r { text-align: right; width: 84pt; }
    .total-row td { border-top: 1.5px solid #111111; padding-top: 6pt; font-size: 13pt; font-weight: 600; }
    .save { background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; padding: 6pt 8pt; font-weight: 600; margin: 6pt 0; font-size: 9pt; }
    .words { border: 1px solid #E4E4E7; padding: 7pt 9pt; margin-bottom: 10pt; }
    table.hsn { width: 100%; border-collapse: collapse; margin-top: 4pt; }
    table.hsn th, table.hsn td { border: 1px solid #E4E4E7; padding: 4pt 5pt; text-align: right; font-size: 8pt; }
    table.hsn th { background: #F4F4F5; color: #6B7280; font-weight: normal; }
    table.hsn .l { text-align: left; }
    table.hsn tr.sumrow td { font-weight: 600; }
    .due { color: #DC2626; font-weight: 600; }
    .qr { border: 1px solid #E4E4E7; padding: 6pt; text-align: center; width: 120pt; }
    .footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 10pt 28pt 14pt; border-top: 1px solid #E4E4E7; font-size: 8pt; color: #52525B; }
    .stamp { position: fixed; top: 300pt; left: 70pt; width: 450pt; text-align: center; font-size: 60pt; font-weight: 600; color: #DC2626; opacity: .22; transform: rotate(-24deg); border: 6pt solid #DC2626; letter-spacing: 0.06em; }
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
                <tr><td style="padding:3pt 0 0; border:0;"><span class="label">Billed by</span><br>{{ \Illuminate\Support\Str::before(trim((string) $i->creator?->name), ' ') ?: '—' }}</td>
                    <td style="padding:3pt 0 0; border:0;"><span class="label">Status</span><br>@if ($i->isCancelled())<span class="due">Cancelled</span>@elseif ($i->due_paise > 0)<span class="due">Due {{ Money::format($i->due_paise) }}</span>@else Paid @endif</td></tr>
            </table>
        </td>
    </tr></table>

    <table class="items">
        @php
            // A4 is 595pt wide, 28pt margins: 539pt for the table. Amount columns are fixed so they line up.
            $w = $tax ? ['n' => 16, 'hsn' => 44, 'qty' => 26, 'mrp' => 50, 'rate' => 56, 'taxable' => 58, 'gst' => 50, 'amt' => 62]
                      : ['n' => 16, 'hsn' => 44, 'qty' => 26, 'mrp' => 56, 'rate' => 64, 'amt' => 70];
            $w['item'] = 539 - array_sum($w);
        @endphp
        <thead><tr>
            <th class="l" style="width: {{ $w['n'] - 10 }}pt;">#</th><th class="l" style="width: {{ $w['item'] - 10 }}pt;">Item</th><th class="l" style="width: {{ $w['hsn'] - 10 }}pt;">HSN/SAC</th>
            <th style="width: {{ $w['qty'] - 10 }}pt;">Qty</th><th style="width: {{ $w['mrp'] - 10 }}pt;">MRP</th><th style="width: {{ $w['rate'] - 10 }}pt;">Rate</th>
            @if ($tax)<th style="width: {{ $w['taxable'] - 10 }}pt;">Taxable</th><th style="width: {{ $w['gst'] - 10 }}pt;">GST</th>@endif
            <th style="width: {{ $w['amt'] - 10 }}pt;">Amount</th>
        </tr></thead>
        <tbody>
            @foreach ($i->items as $n => $item)
                <tr>
                    <td class="l muted">{{ $n + 1 }}</td>
                    <td class="l"><span class="med">{{ $item->name }}</span>@if ($item->returned_qty)<br><span class="faint">{{ $item->returned_qty }} returned (credit note)</span>@endif</td>
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
                    @if (count($hsn) > 1)
                        <tr class="sumrow">
                            <td class="l">Total</td><td></td>
                            <td>{{ $m($i->subtotal_paise) }}</td>
                            @if ($intra)<td>{{ $m($i->cgst_paise) }}</td><td>{{ $m($i->sgst_paise) }}</td>@else<td>{{ $m($i->igst_paise) }}</td>@endif
                            <td>{{ $m($i->taxPaise()) }}</td>
                        </tr>
                    @endif
                </table>
            @else
                <div class="faint">Seller not registered under GST. No tax charged.</div>
            @endif

        </td>
        <td>
            <table class="sum">
                <tr><td>{{ $tax ? 'Taxable value' : 'Value of supply' }}</td><td class="r">{{ $m($i->subtotal_paise) }}</td></tr>
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
                <div class="save">{{ $i->savingsLine(", ") }}</div>
            @endif
            <table class="sum">
                <tr class="total-row"><td>Total</td><td class="r">{{ Money::format($i->total_paise) }}</td></tr>
                <tr><td>Paid</td><td class="r">{{ Money::format($i->paid_paise) }}</td></tr>
                @if ($i->credited_paise)<tr><td>Returned (credit notes)</td><td class="r">{{ $m($i->credited_paise) }}</td></tr>@endif
                @if ($i->due_paise > 0)<tr><td class="due">Balance due</td><td class="r due">{{ Money::format($i->due_paise) }}</td></tr>@endif
            </table>
        </td>
    </tr></table>

    {{-- Payments and the pay-the-due QR: a row of its own, so a long bill can carry it onto page 2. --}}
    <table class="bottom"><tr>
        <td style="width: 56%; padding-right: 14pt;">
            <div>
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
            @if ($qr)
                <table style="width: 100%;"><tr>
                    <td style="width: 92pt;"><img src="{{ $qr }}" style="width: 84pt; height: 84pt;" alt="UPI QR"></td>
                    <td style="vertical-align: middle;"><b>Scan to pay {{ Money::format($i->due_paise) }}</b><br><span class="faint">Any UPI app · {{ $shop['upi_id'] }}</span></td>
                </tr></table>
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
