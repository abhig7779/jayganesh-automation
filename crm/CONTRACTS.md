# CRM module contracts (read before building any module)

Several modules are built in parallel on separate branches and merged later. To keep them from clashing, every module follows these rules. Written 5 Nov 2026.

## Files each module owns, and files nobody edits
- **Routes:**
  - put them in `routes/modules/<module>.php`, which loads automatically inside `auth` + `active`;
  - public routes (webhooks, signed links) go in `routes/public/<module>.php`;
  - API routes go in `routes/api.php`, appended inside the existing `v1` group, as a single block marked `// --- <module> ---` (we merge these by hand).
- **Do not edit:** `routes/web.php`, `resources/views/partials/nav.php` (menu items switch on by route name, see below), `database/seeders/DatabaseSeeder.php`, `bootstrap/app.php` (if you truly need middleware, note it in your report instead).
- **Demo data:**
  - put it in `database/seeders/Demo/NN_<Module>DemoSeeder.php`, class `Database\Seeders\Demo\<Module>DemoSeeder`;
  - NN is the order: 10 products, 20 bills, 30 quotations, 40 whatsapp;
  - it runs automatically in `local`.
- **Migrations:** use your block of timestamps so the order is fixed:
  - shell / home / customers UI: `2026_11_07_05xxxx`
  - products/stock: `2026_11_07_10xxxx`
  - billing: `2026_11_07_20xxxx`
  - quotations/follow-ups/leads: `2026_11_07_30xxxx`
  - whatsapp: `2026_11_07_40xxxx`
- **Views:**
  - `resources/views/<module>/…` and components under `resources/views/components/<module>/…`;
  - use `<x-layouts.app :title="…">` with an optional `<x-slot:header>`.
  - The shell agent may restyle the layout, but it keeps that API and the utility classes `card`, `btn`, `btn-primary`, `btn-accent`, `btn-ghost`, `label`, `input`, `error`, `badge` working.
- **Permissions:** use the existing ones in `app/Support/Permissions.php`. If you need a new one, add it there in one clearly marked line and say so in your report.
- **Tests:**
  - `tests/Feature/<Module>…Test.php`;
  - the global helper `staff($role)` is in `tests/Pest.php`;
  - tests must create a Branch first and seed `RolesAndPermissionsSeeder`.

## Route names (fixed, because the menu and other modules link to them)
| Name | Module |
|---|---|
| `customers.index`, `customers.show`, `customers.create`, `customers.store`, `customers.edit`, `customers.update` | shell/customers UI |
| `search` (GET `/search?q=`, global search page / htmx partial) | shell |
| `products.index` (stock & price list), `products.show`, `products.create`, `products.store`, `products.edit`, `products.update`, `products.import`, `stock.check` (fast phone lookup) | products |
| `bills.create` (New bill / POS), `bills.store`, `bills.show`, `bills.index`, `bills.print` (`?format=a4\|thermal`), `bills.pdf`, `bills.cancel`, `payments.collect` (collect dues) | billing |
| `quotations.index`, `quotations.create`, `quotations.store`, `quotations.show`, `quotations.convert`, `followups.index`, `followups.log`, `leads.index` | quotations |
| `whatsapp.messages.index` (log), `whatsapp.resend` | whatsapp |

## Shared data (already on `main`)
- **Money:** integer paise everywhere (`*_paise` columns). Use `App\Support\Money::format()`, `::toPaise()` and `::percent()`. No floats.
- **Phones:** `App\Support\Phone::normalize()`, `::pretty()`. Plates: `App\Support\RegNo`.
- **Customers:** `App\Models\Customer` (mobile unique, E.164), `CustomerCar`, and `App\Services\Customers\CustomerService::findOrCreateByMobile()`.
- **Products:**
  - `App\Models\Product`: type product/service, `price_paise` (GST-inclusive by default, see `price_includes_gst`), `gst_rate`, `hsn`, `on_hand` (cached), `sold_count`, scope `search()`;
  - also `Brand`, `Category`, `StockMove` (ledger).
  - **Only `App\Services\Stock\StockService` writes stock.** The products module builds it with: `move(Product $p, int $qty, string $type, ?Model $reference = null, ?User $by = null, ?int $unitCostPaise = null, ?string $note = null): StockMove`, which writes the move and updates `on_hand` in the same transaction. Billing calls `move($p, -$qty, 'sale', $invoice)` and `move($p, +$qty, 'sale_cancel', $invoice)`.
  - **Until products is merged,** billing may include a minimal StockService with exactly that signature. Products replaces it at merge time.
- **Settings:** `App\Models\Setting::get('billing.invoice_prefix')`, etc. Keys are in `app/Support/SettingsSchema.php`. Add new keys there with defaults.

## Cross-module hand-offs
1. **Billing fires `App\Events\InvoiceSaved(Invoice $invoice)`** after a bill commits, and `App\Events\InvoiceCancelled(Invoice $invoice)`. Others listen; billing doesn't call them directly.
   - WhatsApp listens to send the e-bill.
   - Quotations listens to mark quotes/leads **won** (by `invoices.quotation_id`, or by the same customer within 30 days).
2. **Billing exposes `App\Services\Pdf\InvoicePdf::render(Invoice $i): string`,** which returns the absolute path of a saved PDF in `storage/app/private/invoices/…`. WhatsApp uses it.
3. **Quotation → bill prefill:** quotations puts this in the session, then redirects to `route('bills.create')`:
   `session(['bill_prefill' => ['customer_id' => int, 'customer_car_id' => ?int, 'quotation_id' => int, 'items' => [['product_id' => int, 'qty' => int, 'unit_price_paise' => int]]]])`
   Billing reads and forgets `bill_prefill` on `bills.create`, pre-fills the bill, and stores `invoices.quotation_id`.
4. **WhatsApp exposes `App\Services\WhatsApp\WhatsApp`:**
   - `sendTemplate(string $toE164, string $template, array $bodyParams, ?string $documentPath = null, ?string $documentName = null, ?Model $related = null): MessageLog`
   - `manualLink(string $toE164, string $text): string` (a wa.me URL)
   - `isAutomatic(): bool` (Settings `whatsapp.mode`)
   - Billing's "Bill done" screen shows a "Send manually" link built with `manualLink()`. Until WhatsApp is merged, billing builds the `https://wa.me/<digits>?text=` link inline.
5. **Dashboard numbers:** the shell owns `App\Support\Dashboard\HomeData` (per-role home). It uses `class_exists`/`Schema::hasTable` guards so it works before the other modules merge. After the merge, the lead wires in real queries.

## UI rules
Follow `crm/UX-FLOWS.md` (pages and flows) and `crm/design/DESIGN.md` (tokens, components, banned AI-slop list). Reference screens are in `crm/design/stitch/` and the owner's Stitch project. Phone first:
- bottom tabs, full-screen steps, bottom sheets, 48 px targets, sticky totals;
- the PC gets a sidebar and multi-column layouts.

Never ship lorem ipsum. Every list needs empty, loading and error states.
