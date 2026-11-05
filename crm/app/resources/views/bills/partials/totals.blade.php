{{-- Live totals: taxable, GST, savings, net total. --}}
<div class="space-y-0.5 text-[13px] text-zinc-700 tabular-nums">
    <div class="flex justify-between"><span>Taxable amount</span><span x-text="money(t.taxable, {decimals: true})"></span></div>
    <div class="flex justify-between" x-show="cfg.gst.registered"><span x-text="gstLabel"></span><span x-text="money(t.tax, {decimals: true})"></span></div>
    <div class="flex justify-between" x-show="!cfg.gst.registered"><span>Bill of supply (shop not GST-registered)</span><span>No GST</span></div>
    <div class="flex justify-between" x-show="t.roundOff !== 0"><span>Round off</span><span x-text="(t.roundOff > 0 ? '+' : '') + money(t.roundOff, {decimals: true})"></span></div>
    <div class="flex justify-between text-green-700" x-show="t.discount > 0"><span>Extra discount (included above)<span x-show="disc.reason" x-text="' · ' + disc.reason"></span></span><span x-text="money(t.discount, {decimals: true})"></span></div>
    <div class="flex justify-between font-semibold text-green-700" x-show="t.savings > 0">
        <span class="flex items-center gap-1"><x-bills.icon name="tag" class="size-3.5" />You save<span class="font-normal" x-show="t.mrpSave > 0" x-text="' (MRP ' + money(t.mrpValue) + ')'"></span></span>
        <span x-text="money(t.savings)"></span>
    </div>
</div>
<div class="mt-1.5 flex items-end justify-between border-t border-zinc-200 pt-1.5">
    <div>
        <div class="text-[15px] font-bold text-ink">Net total</div>
        <div class="text-xs text-zinc-600"><span x-text="items.length"></span> item<span x-show="items.length !== 1">s</span> (<span x-text="pieces"></span> pcs)</div>
    </div>
    <div class="text-[32px] font-bold leading-none tracking-tight text-ink tabular-nums" x-text="money(t.total)"></div>
</div>
