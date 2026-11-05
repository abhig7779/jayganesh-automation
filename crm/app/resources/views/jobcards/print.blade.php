@php
    /** @var \App\Models\JobCard $card */
    $shop = \App\Models\Setting::get('shop.name') ?: 'Jay Ganesh Car Accessories';
    $address = \App\Models\Setting::get('shop.address');
    $phone = \App\Models\Setting::get('shop.phone');
    $ist = fn ($d) => $d?->timezone('Asia/Kolkata')->format('j M Y, g:i a');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $card->number }} · Job sheet</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 13px/1.45 Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #111; background: #f4f4f5; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 14mm; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 10px; }
        h1 { font-size: 20px; margin: 0; }
        .muted { color: #52525b; }
        .num { font-variant-numeric: tabular-nums; }
        .big { font-size: 22px; font-weight: 700; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px 16px; margin: 14px 0; }
        .grid div span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #52525b; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d4d4d8; padding: 7px 8px; text-align: left; vertical-align: top; }
        th { background: #f4f4f5; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; }
        td.r, th.r { text-align: right; }
        .box { width: 14px; height: 14px; border: 1.5px solid #111; display: inline-block; vertical-align: middle; }
        .box.on { background: #111; }
        .section { margin-top: 18px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
        .lines div { border-bottom: 1px solid #d4d4d8; height: 26px; }
        .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 40px; }
        .sign div { border-top: 1px solid #111; padding-top: 4px; font-size: 11px; color: #52525b; }
        .car { display: flex; gap: 18px; margin-top: 8px; }
        .car svg { width: 220px; height: 110px; }
        .toolbar { text-align: center; margin: 12px; }
        .toolbar button { font: inherit; font-weight: 600; padding: 8px 18px; border-radius: 8px; border: 0; background: #FFD51C; cursor: pointer; }
        @media print { body { background: #fff; } .sheet { margin: 0; padding: 0; width: auto; min-height: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" data-print>Print job sheet</button></div>
<div class="sheet">
    <header>
        <div>
            <h1>{{ $shop }}</h1>
            <div class="muted">{{ $address }}@if ($phone) · {{ $phone }}@endif</div>
        </div>
        <div style="text-align:right">
            <div class="muted" style="font-size:11px;font-weight:600;letter-spacing:.06em">JOB SHEET</div>
            <div class="big num">{{ $card->number }}</div>
            <div class="muted">{{ $ist($card->created_at) }}</div>
        </div>
    </header>

    <div class="grid">
        <div><span>Plate</span><strong class="num" style="font-size:16px">{{ $card->plate() ?? '—' }}</strong></div>
        <div><span>Car</span>{{ $card->carLabel(true) }}@if ($card->car?->colour) · {{ $card->car->colour }}@endif</div>
        <div><span>Customer</span>{{ $card->customer?->name }}<br><span class="num" style="font-size:12px;text-transform:none;letter-spacing:0;color:#111;font-weight:400">{{ \App\Support\Phone::pretty($card->customer?->mobile) }}</span></div>
        <div><span>Promised</span>{{ $ist($card->promised_at) ?? '—' }}</div>
        <div><span>Technician</span>{{ $card->technician?->name ?? '—' }}</div>
        <div><span>Bay</span>{{ $card->bay ?? '—' }}</div>
        <div><span>Odometer</span><span class="num" style="font-size:13px;text-transform:none;letter-spacing:0;color:#111;font-weight:400">{{ $card->odometer ? number_format($card->odometer).' km' : '—' }}</span></div>
        <div><span>Fuel</span>{{ $card->fuel_level ?? '—' }}</div>
    </div>

    <div class="section">Work to do</div>
    <table>
        <thead><tr><th style="width:34px">Done</th><th>Work</th><th class="r" style="width:50px">Qty</th>@if ($withPrices)<th class="r" style="width:110px">Amount</th>@endif<th style="width:150px">Checked by</th></tr></thead>
        <tbody>
        @foreach ($card->items as $item)
            <tr>
                <td><span class="box {{ $item->done ? 'on' : '' }}"></span></td>
                <td>{{ $item->description }}</td>
                <td class="r num">{{ $item->qty }}</td>
                @if ($withPrices)<td class="r num">{{ \App\Support\Money::format($item->qty * $item->price_paise) }}</td>@endif
                <td></td>
            </tr>
        @endforeach
        @for ($i = 0; $i < max(0, 4 - $card->items->count()); $i++)
            <tr><td><span class="box"></span></td><td>&nbsp;</td><td></td>@if ($withPrices)<td></td>@endif<td></td></tr>
        @endfor
        @if ($withPrices)
            <tr><td colspan="3" class="r"><strong>Estimate (incl. GST)</strong></td><td class="r num"><strong>{{ \App\Support\Money::format($card->totalPaise()) }}</strong></td><td></td></tr>
        @endif
        </tbody>
    </table>

    <div class="section">Condition at car in (mark scratches / dents)</div>
    <div class="car">
        <svg viewBox="0 0 220 110" fill="none" stroke="#111" stroke-width="1.5" aria-label="Car outline, side">
            <path d="M10 78h200M22 78c0-10 8-18 18-18s18 8 18 18M162 78c0-10 8-18 18-18s18 8 18 18M14 78V62l18-6 30-22h70l34 22 32 6v16"/>
            <path d="M68 36l-14 20h46V36zM106 36v20h52l-28-20z"/>
        </svg>
        <svg viewBox="0 0 220 110" fill="none" stroke="#111" stroke-width="1.5" aria-label="Car outline, top">
            <rect x="20" y="20" width="180" height="70" rx="22"/>
            <path d="M70 26v58M150 26v58M70 30h80M70 80h80"/>
        </svg>
        <div style="flex:1">
            <div class="muted" style="font-size:11px">Notes</div>
            <div class="lines">
                <div>{{ $card->notes }}</div><div></div><div></div><div></div>
            </div>
        </div>
    </div>

    <div class="sign">
        <div>Customer signature (car in)</div>
        <div>Technician</div>
        <div>Customer signature (delivery)</div>
    </div>
    <p class="muted" style="margin-top:18px;font-size:11px">Please remove valuables from the car. Photos of the car are taken at car in and at delivery.</p>
</div>
<script nonce="{{ Vite::cspNonce() }}">document.querySelector('[data-print]')?.addEventListener('click', () => window.print());</script>
</body>
</html>
