@php
    use App\Support\Money;
    // $count: customers in the audience · $automatic: WhatsApp module present + Cloud API mode
    $selected = old('method', 'broadcast');
@endphp
<div class="space-y-3" role="radiogroup" aria-label="How to send">
    <label class="flex cursor-pointer gap-4 rounded-xl border bg-white p-4 has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink border-zinc-300">
        <input type="radio" name="method" value="broadcast" x-model="method" @checked($selected === 'broadcast') class="mt-1 size-4 accent-ink">
        <span class="flex-1">
            <span class="flex flex-wrap items-center gap-2 font-semibold">Broadcast list <span class="badge border border-green-200 bg-green-50 text-green-700">Free</span> <span class="text-xs font-medium text-zinc-600">Recommended</span></span>
            <span class="mt-1 block text-sm text-zinc-700">Download the names and numbers as a CSV for the WhatsApp Business app, then copy the message into a broadcast. Lists hold up to 256 people, so bigger audiences come in parts.</span>
        </span>
    </label>

    <label class="flex cursor-pointer gap-4 rounded-xl border bg-white p-4 has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink border-zinc-300">
        <input type="radio" name="method" value="assisted" x-model="method" @checked($selected === 'assisted') class="mt-1 size-4 accent-ink">
        <span class="flex-1">
            <span class="flex flex-wrap items-center gap-2 font-semibold">One by one <span class="badge border border-green-200 bg-green-50 text-green-700">Free</span></span>
            <span class="mt-1 block text-sm text-zinc-700">The CRM opens WhatsApp for each customer with their name filled in. You press Send there, then “Sent” here. Progress is saved, so you can stop and carry on later. Best for 20–50 people.</span>
        </span>
    </label>

    @if ($automatic)
        <label class="flex cursor-pointer gap-4 rounded-xl border bg-white p-4 has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink border-zinc-300">
            <input type="radio" name="method" value="automatic" x-model="method" @checked($selected === 'automatic') class="mt-1 size-4 accent-ink">
            <span class="flex-1">
                <span class="flex flex-wrap items-center gap-2 font-semibold">Automatic (paid) <span class="badge border border-amber-200 bg-amber-50 text-amber-700">Meta charges per message</span></span>
                <span class="mt-1 block text-sm text-zinc-700">Sent from the shop number through the WhatsApp Cloud API with an approved marketing template.</span>
            </span>
        </label>

        <div x-show="method === 'automatic'" x-cloak class="rounded-xl border border-zinc-300 bg-white p-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="label" for="template_name">Approved marketing template</label>
                    @if ($templates)
                        <select id="template_name" name="template_name" class="input">
                            <option value="">Choose…</option>
                            @foreach ($templates as $name => $label)
                                <option value="{{ $name }}" @selected(old('template_name') === $name)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @else
                        <input id="template_name" name="template_name" value="{{ old('template_name', 'offer_marketing') }}" class="input" placeholder="offer_marketing">
                        <p class="mt-1 text-xs text-zinc-600">The exact name from WhatsApp Manager. It must be approved in the MARKETING category, with @{{1}} = first name and @{{2}} = the offer text.</p>
                    @endif
                    @error('template_name') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="scheduled_at">When</label>
                    <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="input">
                    <p class="mt-1 text-xs text-zinc-600">Leave empty to send now. Shop time (IST).</p>
                </div>
            </div>
            <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm">
                <div class="font-semibold">Estimated cost</div>
                <div class="mt-1 tabular-nums text-zinc-700"><span id="cost-estimate">{{ number_format($count) }} × {{ Money::format($ratePaise, true) }} = <strong class="text-ink">{{ Money::format($count * $ratePaise, true) }}</strong></span></div>
                <div class="mt-1 text-xs text-zinc-600">Meta's marketing rate from Settings. The final bill comes from Meta and can differ slightly.</div>
            </div>
            <label class="mt-3 flex items-start gap-3 text-sm">
                <input type="checkbox" name="confirm_cost" value="1" class="mt-0.5 size-4 accent-ink" @checked(old('confirm_cost'))>
                <span>I confirm sending this offer to <strong id="send-count">{{ number_format($count) }}</strong> customers and paying Meta's message charges.</span>
            </label>
            @error('confirm_cost') <p class="error">{{ $message }}</p> @enderror
        </div>
    @else
        <p class="px-1 text-sm text-zinc-600">Paid sending through the WhatsApp Cloud API appears here once it is switched on in Settings → WhatsApp.</p>
    @endif
    @error('method') <p class="error">{{ $message }}</p> @enderror
</div>
