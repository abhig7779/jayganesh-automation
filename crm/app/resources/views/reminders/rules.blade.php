<x-layouts.app title="Reminder rules">
    <x-ui.page-header title="Reminder rules" subtitle="Which bill lines start a check-up reminder, after how many days, and what the WhatsApp message says." :back="route('reminders.index')" back-label="Reminders" />

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_440px]">
        <x-ui.panel class="self-start" title="Rules" :subtitle="$rules->where('is_active', true)->count().' on · new bills use them; reminders already made keep their dates'" flush>
            @if ($rules->isEmpty())
                <x-ui.empty-state compact icon="bell" text="No rules yet. Add the first one on the right, for example Ceramic coating after 180 and 365 days." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left text-[13px]">
                        <thead class="bg-sunken text-xs font-semibold tracking-[0.06em] text-muted uppercase">
                            <tr><th class="px-5 py-3">Rule</th><th class="px-3 py-3">Matches</th><th class="px-3 py-3">Remind after</th><th class="px-5 py-3 text-right"><span class="sr-only">Edit</span></th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rules as $r)
                                <tr @class(['align-top', 'bg-sunken/50' => $rule->id === $r->id])>
                                    <td class="px-5 py-3">
                                        <div class="font-semibold text-ink">{{ $r->name }}</div>
                                        @unless ($r->is_active)<x-ui.status-badge status="off" class="mt-1" />@endunless
                                    </td>
                                    <td class="px-3 py-3 text-ink-2">{{ $r->matchLabel() }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap tnum">{{ $r->offsetsLabel() }}</td>
                                    <td class="px-5 py-3 text-right"><a href="{{ route('reminders.rules.edit', $r) }}" class="btn-quiet lg:min-h-8">Edit</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.panel>

        <x-ui.panel :title="$rule->exists ? 'Edit: '.$rule->name : 'Add a rule'">
            <form method="POST" action="{{ $rule->exists ? route('reminders.rules.update', $rule) : route('reminders.rules.store') }}"
                  class="space-y-4" x-data="{ match: @js(old('match_type', $rule->match_type ?? 'name')) }">
                @csrf
                @if ($rule->exists) @method('PUT') @endif

                <div>
                    <label class="label" for="name">Name</label>
                    <input class="input" id="name" name="name" value="{{ old('name', $rule->name) }}" placeholder="Ceramic coating check-up" required maxlength="80">
                    @error('name')<p class="error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label" for="match_type">Start it when a bill has</label>
                    <select class="input" id="match_type" name="match_type" x-model="match">
                        @foreach ($matchTypes as $key => $label)
                            <option value="{{ $key }}" @selected(old('match_type', $rule->match_type) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="match === 'name'">
                    <label class="label" for="keywords">Words in the item or service name (comma separated)</label>
                    <input class="input" id="keywords" name="keywords" value="{{ old('keywords', $rule->keywords) }}" placeholder="ceramic coating, graphene coating">
                    @error('keywords')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div x-show="match === 'category'" x-cloak>
                    <label class="label" for="category_id">Category</label>
                    <select class="input" id="category_id" name="category_id">
                        <option value="">Pick a category</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) old('category_id', $rule->category_id) === $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                    @error('category_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div x-show="match === 'product'" x-cloak>
                    <label class="label" for="product_id">Product</label>
                    <select class="input" id="product_id" name="product_id">
                        <option value="">Pick a product</option>
                        @foreach ($products as $p)<option value="{{ $p->id }}" @selected((int) old('product_id', $rule->product_id) === $p->id)>{{ $p->name }}</option>@endforeach
                    </select>
                    @error('product_id')<p class="error">{{ $message }}</p>@enderror
                </div>

                <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-ink-2">
                    <input type="checkbox" name="services_only" value="1" class="size-5 accent-ink" @checked(old('services_only', $rule->services_only))>
                    Only services (skip DIY products with the same name)
                </label>

                <div>
                    <label class="label" for="offsets">Remind after (days from the bill, comma separated)</label>
                    <input class="input tnum" id="offsets" name="offsets" inputmode="numeric" value="{{ old('offsets', implode(', ', $rule->offsets())) }}" placeholder="180, 365" required>
                    @error('offsets')<p class="error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label" for="message">WhatsApp message</label>
                    <textarea class="input min-h-36 py-2.5" id="message" name="message" rows="6" required maxlength="1000">{{ old('message', $rule->message) }}</textarea>
                    <p class="mt-1.5 text-xs text-muted">Fills in: {name} {car} {service} {date} {shop}. Polite English, one short request.</p>
                    @error('message')<p class="error">{{ $message }}</p>@enderror
                </div>

                <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-ink-2">
                    <input type="checkbox" name="is_active" value="1" class="size-5 accent-ink" @checked(old('is_active', $rule->exists ? $rule->is_active : true))>
                    Rule is on
                </label>

                <div class="flex flex-wrap gap-2 pt-1">
                    <button class="btn-accent">{{ $rule->exists ? 'Save rule' : 'Add rule' }}</button>
                    @if ($rule->exists)<a href="{{ route('reminders.rules.index') }}" class="btn-ghost">Cancel</a>@endif
                </div>
            </form>
        </x-ui.panel>
    </div>
</x-layouts.app>
