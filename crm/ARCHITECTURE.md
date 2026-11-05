# Jay Ganesh CRM: technical architecture (full stack)

This is the companion to `CRM-PLAN.md`: the plan covers *what* we build, and this file covers *how*. Written 5 Nov 2026. Update it whenever a technical choice changes.

---

## 1. Stack at a glance

| Layer | Choice | Why this one |
|---|---|---|
| Hosting | Existing **MilesWeb shared hosting**, subdomain `crm.jayganeshcaraccessories.com`, free SSL (AutoSSL / Let's Encrypt) | Already paid for. No new bills. |
| Language | **PHP 8.2+** | MilesWeb runs it natively; nothing extra to install on the server |
| Backend framework | **Laravel** (current major at build start, 12.x or newer) | Free and open source. Gives login, roles, migrations, queues, validation, PDF and Excel packages, scheduler and tests. Easy for any future developer to pick up. |
| Database | **MySQL 8 / MariaDB 10.6+** (MilesWeb) | Included in the hosting. Transactions and row locks for invoice numbers. |
| Frontend | **Server-rendered Blade pages + htmx + Alpine.js + Tailwind CSS** | Fast on cheap phones and the counter PC. No separate SPA to maintain. Tailwind is compiled on our Mac or in CI, so the server needs no Node. |
| Icons / UI kit | Heroicons (SVG) + our own Tailwind components in brand colours (black, white, Jay Ganesh yellow `#FFD51C`) | Free; matches the brand |
| PDF | **dompdf** (`barryvdh/laravel-dompdf`) for the A4 invoice and quotation; print CSS for the 80 mm thermal receipt | Pure PHP, works on shared hosting (no Chrome or wkhtmltopdf needed) |
| Excel / CSV | **OpenSpout** | Light, fast exports for the CA and Tally |
| Roles & permissions | **spatie/laravel-permission** | Roles and permissions stored in the DB, as the plan requires |
| Audit log | **spatie/laravel-activitylog** | Records who changed what, with before/after values |
| API auth (future mobile app or integrations) | **Laravel Sanctum** tokens | Same endpoints, no rewrite later |
| Background jobs | Laravel **database queue**, run by cron every minute, plus `dispatchAfterResponse` for the instant e-bill | Shared hosting has no Redis or supervisor; this works within those limits |
| WhatsApp | **Meta WhatsApp Cloud API** (Graph API, direct; no BSP fee) | Free platform; we pay only Meta's per-message charge |
| Backups | **spatie/laravel-backup** (DB + uploads, daily, 30 days) plus a copy to Google Drive pulled by GitHub Actions | Two places; restore tested monthly |
| Code & CI/CD | **GitHub** (private repo) + **GitHub Actions**: test, build CSS, deploy by FTPS (SSH if MilesWeb allows) | Free tier is enough; same setup we already use for the posting automation |
| Tests | **Pest** (PHPUnit) with SQLite in memory | Billing and GST maths must never break silently |
| Local dev | **Laravel Herd** (free) or Homebrew PHP + MySQL on the Mac, in `Jaygnesh car/crm/app` | Matches production closely |
| Error tracking | Laravel log to file + daily email digest of errors to the admin (free). Sentry free tier optional. | We know when something breaks |

**MilesWeb was checked on 5 Nov 2026 and runs Laravel fine (see §12).** Old fallback note: (for example, PHP below 8.2 or `proc_open` disabled): the same structure on **Slim 4** micro-framework + the same packages where they work. Check this before writing any code (open question §12).

---

## 2. How the pieces talk

```
 Staff browser (counter PC / phone / tablet)
        │  HTTPS (session cookie, CSRF)
        ▼
 crm.jayganeshcaraccessories.com  ──►  public/index.php (Laravel)
        │
        ├── Web routes  (Blade pages, htmx partials)     ─┐
        ├── API routes  /api/v1/... (JSON, Sanctum)      ─┤──► Services (Billing, Stock, GST, WhatsApp, Pdf)
        └── Webhook     /webhooks/whatsapp (Meta → us)   ─┘          │
                                                                     ├──► MySQL (all data, money in paise)
                                                                     ├──► storage/app/private (PDFs, photos)
                                                                     └──► Jobs table (queue)
 cron (every minute):  php artisan schedule:run
        ├── queue:work --stop-when-empty --max-time=50   (send WhatsApp, retries)
        ├── daily 23:30 backup:run, cash-close reminder
        └── daily 09:00 low-stock + dues digest
 Meta WhatsApp Cloud API  ◄── send template + PDF;  ──► status webhooks (sent/delivered/read/failed)
 GitHub Actions  ── deploy ──► MilesWeb (FTPS/SSH);  nightly pull of backup ──► Google Drive
```

### The instant e-bill flow
1. The counter user saves a bill. In one DB transaction: lock `invoice_series`, take the next number, write `invoices`, `invoice_items` and `payments`, write stock-out moves, commit.
2. The response goes back to the browser at once (the bill shows on screen and is ready to print).
3. `SendInvoiceOnWhatsApp` runs **after the response** in the same PHP process:
   - render the PDF with dompdf into `storage/app/private/invoices/2026/JG-2026-0001.pdf`;
   - upload it to the WhatsApp media endpoint;
   - send the `invoice_ebill` utility template with the document header;
   - write a row to `message_log`.
   - The customer usually gets it within 2–5 seconds.
4. If any step fails, the job goes to the database queue, and cron retries it at 1, 5 and 15 minutes. After that it is marked **failed**, and a red "Resend" button shows on the bill.
5. Meta's webhook updates the `message_log` status to sent → delivered → read, shown as ticks on the bill.

---

## 3. Repository and folder structure

```
automation-engine/crm/   (inside the jayganesh-automation git repo)
├── CRM-PLAN.md                 # what we build (master plan)
├── ARCHITECTURE.md             # this file
└── app/                        # the Laravel project (same repo as automation/ and website/)
    ├── app/
    │   ├── Models/             # Customer, CustomerCar, Product, StockMove, Supplier, Purchase,
    │   │                       # Quotation, Invoice, InvoiceItem, Payment, Expense, JobCard,
    │   │                       # Lead, Offer, MessageLog, Setting, Branch, User
    │   ├── Http/
    │   │   ├── Controllers/Web/    # page controllers (thin)
    │   │   ├── Controllers/Api/V1/ # JSON controllers (same services underneath)
    │   │   ├── Controllers/Webhooks/WhatsAppController.php
    │   │   ├── Requests/           # validation rules per form
    │   │   └── Middleware/         # role checks, branch scope, force HTTPS
    │   ├── Services/
    │   │   ├── Billing/            # InvoiceService (create, cancel), NumberSeries, Totals
    │   │   ├── Gst/                # GstCalculator (CGST+SGST vs IGST, HSN summary)
    │   │   ├── Stock/              # StockService (in/out/adjust, valuation)
    │   │   ├── Pdf/                # InvoicePdf, QuotationPdf, ThermalReceipt
    │   │   ├── WhatsApp/           # CloudApiClient, TemplateSender, MediaUploader, WebhookVerifier
    │   │   ├── Audience/           # Meta Custom Audience export (hashed phones)
    │   │   └── Reports/            # daily sales, dues ageing, P&L, GST export
    │   ├── Support/Money.php       # integer-paise value object + ₹ formatting (en-IN)
    │   ├── Jobs/                   # SendInvoiceOnWhatsApp, SendQuotation, SendOfferBatch
    │   ├── Policies/               # who can view/edit/cancel each record
    │   ├── Observers/              # audit + branch_id stamping
    │   └── Console/                # scheduled commands (digests, cleanup)
    ├── database/
    │   ├── migrations/             # numbered, never edited after they run in production
    │   └── seeders/                # roles, permissions, settings, demo data (local only)
    ├── resources/
    │   ├── views/
    │   │   ├── layouts/            # app shell: sidebar (desktop), bottom nav (phone)
    │   │   ├── components/         # buttons, inputs, money, status badges, modal, table
    │   │   ├── customers/ quotations/ invoices/ inventory/ purchases/
    │   │   ├── jobcards/ finance/ leads/ offers/ reports/ settings/ staff/
    │   │   └── pdf/                # invoice-a4, quotation-a4, receipt-80mm
    │   ├── css/app.css             # Tailwind entry
    │   └── js/app.js               # htmx + Alpine setup, keyboard shortcuts for the counter
    ├── routes/ web.php api.php webhooks.php console.php
    ├── public/                     # ONLY this folder is web-visible (index.php, built css/js, logo)
    ├── storage/                    # private PDFs, photos, logs, backups (never web-visible)
    ├── tests/ Feature/ Unit/       # billing, GST, stock, permissions, webhook tests
    ├── .github/workflows/ ci.yml deploy.yml backup-to-drive.yml
    ├── .env.example                # every setting listed, with no secrets
    └── composer.json  package.json (Tailwind build only)  tailwind.config.js
```

**On the server (MilesWeb):**
- Laravel goes in `/home/<user>/crm-app/`, **outside** `public_html`.
- The subdomain's document root points to `/home/<user>/crm-app/public`.
- If mPanel won't let us set that, `public_html/crm/` holds only the public files, and `index.php` points up to `../../crm-app`.
- So `.env`, the code and the stored PDFs can never be opened from a browser.

---

## 4. Backend details

### Data rules
- **Money:** a `BIGINT` count of paise everywhere (`price_paise`, `total_paise`), and a `Money` class for maths and display (₹1,23,456.00). No floats, ever.
- **GST:**
  - each line stores HSN, rate %, taxable value, CGST, SGST and IGST;
  - the place of supply decides CGST+SGST (Maharashtra, state code 27) versus IGST;
  - line totals are rounded to the paise, and the invoice-level round-off is stored as its own field.
- **Invoice numbers:**
  - `invoice_series` has one row per branch, per financial year and per type;
  - the next number is taken with `SELECT … FOR UPDATE` inside the bill transaction;
  - the format is `JG-2526-0001`, and numbers restart each April (Indian financial year);
  - there are never gaps, and a cancelled bill keeps its number.
- **No hard deletes** of business records: `status` + `cancelled_at` + `cancel_reason`.
- **Every main table** has `id, branch_id, created_by, created_at, updated_at`. A global scope filters by the user's branch.
- **Customers:**
  - the mobile number is stored as E.164 (`+919823107676`) and is unique;
  - typing a number on the bill screen finds the customer or creates a new one in place.
- **Stock** is never edited directly. It is the sum of `stock_moves` (purchase in, sale out, return, adjustment with a reason), with a cached `on_hand` column kept in step by `StockService`.
- **Settings** (shop name, GSTIN, address, UPI ID, invoice footer, WhatsApp template names, tax defaults) live in the `settings` table and are edited from the Settings page.

### Security
- Laravel sessions with bcrypt passwords, `HttpOnly` + `Secure` + `SameSite=Lax` cookies, CSRF on every form.
- Login is limited to 5 attempts per minute per IP and user. Optional 2-step OTP later.
- Sessions expire after 8 hours of idle time on the counter and 30 minutes for the Finance and Owner roles.
- A Policy check sits on every controller action, and a test proves each role can't reach screens it shouldn't.
- Only Eloquent / prepared queries are used, and Blade escapes output by default.
- Uploads (job photos, supplier bills) are checked by type and size, renamed, and kept in private storage served through a permission-checked route.
- The WhatsApp webhook checks the `X-Hub-Signature-256` header with the app secret and rejects everything else.
- No customer data goes in URLs (records use IDs only) or in logs (phone numbers are masked in logs).
- Secrets (DB password, WhatsApp token, app key) are kept only in the server `.env` and in GitHub Actions secrets.

### API (built from day one, used later)
- `/api/v1/customers`, `/products`, `/quotations`, `/invoices`, `/payments`, `/leads`, `/reports/daily`, …
- These are the same services the web pages use. Sanctum tokens with abilities per role.
- Later uses:
  - a mobile app;
  - the website's "book a visit" form posting leads straight into the CRM;
  - Meta lead ads → CRM;
  - an AI WhatsApp assistant reading the product list.

### WhatsApp module
- `CloudApiClient` wraps Graph API calls: send template, upload media, mark read.
- `message_log` stores each message (customer, template, related record, WhatsApp message id, status, error, cost category).
- Templates are named in Settings, so renaming one in Meta doesn't need a code change.
- Marketing sends check `whatsapp_opt_in = 1` and `opted_out_at IS NULL`. An inbound "STOP" sets `opted_out_at` through the webhook.
- If the phone number isn't connected yet, the module runs in **"manual mode"**: it shows a "Send on WhatsApp" button that opens `wa.me/<number>?text=<message with PDF link>`. The PDF link is a signed, expiring URL. So billing works from day one, even before API approval.

---

## 5. Frontend details

- **Server-rendered pages** with **htmx**: forms and tables swap only the changed part, so it feels like an app without a heavy JS framework. **Alpine.js** handles small interactions (line items on the bill, modals, dropdowns).
- **Mobile-first layout:**
  - phone: a bottom tab bar (Home, New Bill, Customers, Stock, More);
  - tablet/PC: a left sidebar;
  - tap targets are 44 px or larger, and number inputs open the numeric keypad.
- **Counter speed:**
  - the New Bill screen is the home screen for the Sales role;
  - keyboard shortcuts: `F2` new bill, `/` search product, `Ctrl+Enter` save and send;
  - product search is MySQL FULLTEXT plus SKU prefix match;
  - the car model picker remembers recent choices.
- **Printing:**
  - A4 invoice as a dompdf PDF;
  - 80 mm thermal as an HTML print view with `@page { size: 80mm auto }`, so any thermal printer driver works.
- **Installable (PWA-lite):**
  - a web manifest and icon, so staff can "Add to Home Screen" on Android;
  - a small service worker caches the app shell only;
  - bills always need the internet, so no offline billing in v1.
- **Formats:** Indian number format (₹1,23,456), dates as `05 Nov 2026`, timezone `Asia/Kolkata`.
- **Accessibility and readability:** high-contrast black/white UI with yellow accents. Status badges use colour *and* text.
- **Build:**
  - `npm run build` compiles Tailwind and bundles htmx/Alpine into `public/build/`, in CI or on the Mac;
  - the server only serves the built files.

---

## 6. Database (first migration set)

| Group | Tables |
|---|---|
| Access | `users`, `roles`, `permissions`, `model_has_roles`, `role_has_permissions` (Spatie), `personal_access_tokens` (Sanctum), `sessions` |
| Shop | `branches`, `settings`, `invoice_series` |
| Customers | `customers`, `customer_cars` (make, model, year, reg no, colour) |
| Catalogue & stock | `brands`, `categories`, `products` (sku, hsn, gst_rate, mrp_paise, price_paise, cost_paise, reorder_level, is_service), `stock_moves`, `stock_counts` |
| Buying | `suppliers`, `purchases`, `purchase_items`, `supplier_payments` |
| Selling | `quotations`, `quotation_items`, `invoices`, `invoice_items`, `payments` (mode: cash/upi/card/bank, ref no), `credit_notes` (returns) |
| Workshop | `job_cards`, `job_card_items`, `job_card_photos` |
| Money | `expenses`, `expense_categories`, `cash_closes` |
| Growth | `leads`, `lead_activities`, `offers`, `offer_sends`, `message_log`, `audience_exports` |
| System | `activity_log` (audit), `jobs`, `failed_jobs`, `backups_log` |

Indexes: `customers.mobile` (unique), `invoices (branch_id, number)` (unique), `invoices.created_at`, `stock_moves (product_id, created_at)`, FULLTEXT on `products(name, sku)` and `customers(name)`.

---

## 7. Hosting, cron and deploy

**One-time server setup (MilesWeb mPanel):**
1. Create the subdomain `crm` with its document root set to `crm-app/public`, and turn on SSL.
2. Create a MySQL database and user with a strong password.
3. Set PHP to 8.2 or newer, with the extensions `pdo_mysql, mbstring, openssl, gd, zip, intl, bcmath, fileinfo, curl`. Set `memory_limit` to at least 256M for PDFs.
4. Add a cron job, every minute: `cd ~/crm-app && php artisan schedule:run >> /dev/null 2>&1`.
5. Get SSH access if MilesWeb offers it on the plan (it makes deploys and migrations much easier).

**Deploy pipeline (GitHub Actions, on push to `main`):**
1. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, and `pest` (it stops if a test fails).
2. Upload the changed files to `~/crm-app` over SSH (rsync) or FTPS. `.env` and `storage/` are never overwritten.
3. Run `php artisan migrate --force && php artisan optimize` over SSH. Without SSH, call a one-time deploy URL protected by a long secret token that runs the same commands, then lock it again.
4. Smoke test: `GET /up` must return 200.

**Environments:**
- `local` on the Mac;
- `staging` (optional, `crm-test.` subdomain with its own database) for trying changes with the owner;
- `production`.

**Backups:**
- Every night at 23:30, `backup:run` zips a DB dump plus `storage/app/private` into `storage/backups`, and keeps 30 days.
- At 01:00, GitHub Actions pulls the newest zip over FTPS and uploads it to the Jay Ganesh Google Drive folder `CRM Backups`.
- On the first Monday of each month, restore it to staging to prove it works.

---

## 8. Integrations (now and later)

| Integration | When | How |
|---|---|---|
| WhatsApp Cloud API e-bills | Phase 1 | Template + PDF document; webhook for statuses (manual `wa.me` mode until approved) |
| UPI on the invoice | Phase 1 | A UPI QR (`upi://pay?pa=…&am=…`) printed on the PDF and shown on screen |
| Google review request | Phase 3 | A WhatsApp template sent 1 day after a bill, with the Maps review link |
| Meta Custom Audiences | Phase 3 | SHA-256 hashed phones, exported as CSV first, synced via Marketing API later once access exists |
| Website leads | Phase 3 | The website contact form POSTs to `/api/v1/leads` |
| Tally / CA | Phase 2 | Excel export of sales, purchases and the GST summary (Tally XML later if needed) |
| Payment links / online orders | Later | Razorpay or similar (decide when needed) |
| AI WhatsApp assistant | Later | Reads products and offers through the API, replies within the 24 h window |

---

## 9. Testing and quality

- **Unit tests:** `Money`, `GstCalculator` (intra/inter-state, mixed rates, round-off), `NumberSeries` (no gaps, even with two bills saved at the same moment), `StockService`.
- **Feature tests:**
  - create a bill and check totals, stock, payment, audit row and a queued WhatsApp job;
  - cancel a bill and check stock goes back and its number stays;
  - each role's access matrix;
  - webhook signature check.
- **CI** runs every test on each push; deploy is blocked if any fail.
- **Before each phase goes live:** a run-through on staging with the owner using real products and a test customer number.

---

## 10. Build order (Phase 1 in detail)

1. ~~Check MilesWeb~~ Done 5 Nov 2026 ✅ (see §12). Next: create the `crm.` subdomain (doc root `crm-app/public`), the MySQL database, PHP 8.4, and an SSH key for deploys.
2. Laravel skeleton, Tailwind/htmx/Alpine, app shell, login, roles and seeders, settings page.
3. Customers + cars; products (basic) + a CSV import of the price list.
4. Quotations → PDF → convert to bill.
5. Invoices: series, GST maths, split payments, A4 PDF + 80 mm print, cancel.
6. WhatsApp e-bill (manual `wa.me` mode first, then Cloud API once the number is connected).
7. Daily sales report, audit log screen, backups and the Drive copy.
8. Staging run-through with the owner, then go live. Paper bills stop.

Then Phase 2 (inventory and purchases, job cards, finance) and Phase 3 (leads, offers, audiences, reminders) as in `CRM-PLAN.md` §8.

---

## 11. Coding conventions

- PSR-12 (Laravel Pint runs in CI). Controllers stay thin and the logic lives in `Services`.
- One migration per change. Never edit a migration that has run in production.
- Every money column is named `*_paise`; every date-time is stored in UTC and shown in IST.
- Every new screen needs a Policy and a role test.
- Commits in plain English. `main` is always deployable; work happens on feature branches.

## 12. MilesWeb check (done 5 Nov 2026, read-only in mPanel): Laravel can run ✅
- **Plan:** "Premium" shared hosting, active until 19 Oct 2027. Disk use 362 MB of 25 GB, unlimited bandwidth, 0 of 350 databases used, 2 of 10 email accounts used.
- **PHP:**
  - currently 8.2; 8.3, 8.4 and 8.5 are available. Switch the CRM subdomain to 8.4 at setup.
  - all needed extensions are built in: pdo_mysql, mbstring, openssl, gd, imagick, zip, intl, bcmath, fileinfo, curl, sodium, redis, pcntl, opcache;
  - `memory_limit` is 512M and `post_max_size` is 128M;
  - `disable_functions` does **not** block `proc_open`/`exec`, which Laravel needs (only `system`, `passthru`, `popen` and `show_source` are blocked).
- **SSH:** available (password or key), so deploys can use rsync + `php artisan migrate` over SSH; no deploy-URL hack is needed.
- **Cron:** Cron Jobs in Developer Tools (none created yet).
- **Redis:** can be enabled (127.0.0.1:6379 inside the PHP container). We can use it later for cache, sessions and the queue instead of the database queue.
- **Subdomains:** each website has an editable **Document Root**, so `crm.` can point straight at `crm-app/public` ✅. Existing subdomains: `api.` and `media.` (automation).
- **Also in mPanel:** a GitHub integration, Node.js (paid upgrade, not needed), daily backups by MilesWeb, WAF.
- Server IP 45.199.139.29 (website, MySQL and mail).

## 13. Open technical questions
1. MySQL/MariaDB version (check in Databases when creating the CRM database).
2. WhatsApp: coexistence on 98231 07676, or a separate SIM for the API (see `CRM-PLAN.md` §7).
3. Thermal printer model (USB/Bluetooth), or A4 only?
4. ~~GitHub repo~~ Decided 5 Nov 2026: the CRM lives in the existing `jayganesh-automation` repo, folder `crm/`. That repo is **public**, so make it private before pushing CRM code, or confirm that's OK.
