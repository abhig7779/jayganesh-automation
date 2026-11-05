# Jay Ganesh CRM: master plan

This is the single source of truth for the CRM (the technical how is in `ARCHITECTURE.md`). Read it before starting any CRM work, and update it whenever a decision changes. Started 5 Nov 2026.

## 1. Why we're building it
- Bills are handwritten, so customer numbers aren't stored. That means no repeat offers, no service reminders and no Meta retargeting.
- There's no shared view of sales, dues or stock for the owner and admin.
- **Goal:** one browser app that records every customer, quote, bill, payment and stock movement, with staff logins and automatic WhatsApp e-bills.

## 2. Fixed decisions (don't change without the owner)
| Decision | Choice | Why |
|---|---|---|
| Where it runs | `crm.jayganeshcaraccessories.com` on the **existing MilesWeb hosting** | Free; the data stays on their own hosting |
| App type | **Browser only** (counter PC, phone, tablet); no app-store app | The owner asked for this |
| Stack | **Laravel (PHP 8.2+) + MySQL** on MilesWeb; Blade + htmx + Alpine.js + Tailwind; mobile-first. Full details in `ARCHITECTURE.md` | Runs on shared hosting with no Node or extra services |
| Cost | Free, except WhatsApp API message charges | The owner asked for free |
| Logins | Separate login per staff member with roles (§4) | "Multi agent" means multi-staff login, not AI |
| E-bill | Bill PDF **auto-sent on WhatsApp** the moment a bill is saved | The owner asked for this |
| WhatsApp number | **+91 98231 07676** (already the ad chat number), using WhatsApp **Cloud API + Business app coexistence** | One number for chats and bills |
| Language | UI in English. Customer messages and invoices in English. | Captions and messages are always in English (owner) |
| Money | All amounts stored as **integer paise**; GST computed per line | No rounding bugs |

## 3. Modules
1. **Customers:** name, mobile (unique key), WhatsApp opt-in, area, cars (model, year, reg no), tags, notes, full history.
2. **Quotations:** pick car, add products/services, apply an offer, make a PDF and send it on WhatsApp, then convert to a bill in one click.
3. **Billing / invoices:**
   - GST invoice or bill of supply (decided by the GST answer in §9);
   - series JG-YYYY-NNNN with no gaps;
   - split payments (cash, UPI, card), a printable A4 PDF and an 80 mm thermal receipt;
   - cancellation only by Admin or Owner, with a reason, and never a hard delete.
4. **WhatsApp e-bill:** on save, generate the PDF, send it as an approved utility template with the PDF as the document, then track sent, delivered and read; retry or resend.
5. **Inventory:** products (SKU, brand, HSN, GST %, MRP, sale price, cost), stock in from purchases, stock out from bills, adjustments with a reason, low-stock alerts, stock value.
6. **Purchases / suppliers:** supplier list, purchase bills, payables.
7. **Job cards:** car in, work items, technician, before/after photos, QC, delivered; links to the bill.
8. **Finance:** payments received, dues and ageing, expenses, daily cash close, monthly P&L summary, GST reports, Excel/Tally export for the CA.
9. **Leads:** WhatsApp/ad leads (source, campaign), status flow New → Quoted → Visit booked → Won/Lost, follow-up dates.
10. **Offers / campaigns:** customer segments (car model, last visit, product bought), WhatsApp template broadcast (paid, optional) or export to a broadcast list (free), opt-out respected.
11. **Retargeting:** export or sync the customer list to **Meta Custom Audiences** (ad account act_25886513611038791), hashed phone numbers.
12. **Dashboards:** daily and monthly sales, salesperson-wise, product-wise, ad leads → walk-ins → sales, dues, stock value.
13. **Audit log:** every create, edit and cancel stored with user, time and before/after.
14. **Service reminders (later):** ceramic/PPF check-ups and similar, from job history.

## 4. Roles
| Role | Access |
|---|---|
| Owner | Everything, including settings, staff and margins |
| Admin | All sales and reports, inventory, offers, staff activity. Can't delete or change prices without the Owner. |
| Finance | Payments, dues, expenses, suppliers, cash close, GST exports |
| Sales / Counter | Customers, quotes, bills, collect payments, leads. Can't see margins or other staff totals. |
| Technician | Job cards only |

Roles are stored as data (a `permissions` table), so new roles can be added later without code changes.

## 5. Data model (core tables)
`users, roles, permissions, customers, customer_cars, products, product_stock_moves, suppliers, purchases, purchase_items, quotations, quotation_items, invoices, invoice_items, payments, expenses, job_cards, job_card_items, leads, offers, message_log (whatsapp), audit_log, settings, invoice_series`

Every table has `id, created_at, created_by, updated_at`. Rows are cancelled rather than deleted, using `status`/`cancelled_at`. A `branch_id` column goes on main tables from day one, for a possible second shop.

## 6. Future-proofing rules
- **API-first inside the app:** every action goes through PHP endpoints (`/api/...`) that return JSON, so a mobile app, AI helpers or other tools can use the same endpoints later.
- **Settings in the DB**, not in code: GST %, invoice prefix, WhatsApp template names, shop details, footer.
- **Migrations:** numbered SQL files in `crm/db/migrations/` that run in order and are never edited after use.
- **Backups:** a daily MySQL dump, kept for 30 days, plus a copy to Google Drive. A restore is tested once a month.
- **Security:** HTTPS only, hashed passwords (bcrypt), session timeout, rate-limited login, role checks on every endpoint, prepared SQL only, no customer data in URLs.
- **Privacy:** store a WhatsApp opt-in per customer, plus an opt-out word ("STOP") for offers. Bills/utility messages are allowed; marketing only with consent.
- **Data export:** any table to CSV or Excel at any time, so the data is never locked in.
- **Git:** code in a repo (same GitHub account), deployed to the subdomain by FTP/CI. Secrets live in `.env` on the server, never in git.

## 7. WhatsApp setup (one time)
1. Connect 98231 07676 to WhatsApp Cloud API with Business-app coexistence (Meta Business Manager).
   - **Status (5 Nov 2026, read-only API check):** business 1728410417676996 is verified. It has 3 approved WABAs (main: "Jai Ganesh Car Accessories", 1834738223843088), and **no number is on the API yet**, so 98231 07676 is app-only and can be onboarded.
   - **Not yet confirmed:** whether our own Meta app can onboard it with coexistence directly (Embedded Signup → "connect existing WhatsApp Business app"), or whether it needs a Tech Provider/BSP. Check in WhatsApp Manager → Add phone number.
   - Coexistence limits: about 5 messages per second; no delete-for-everyone or disappearing messages; some chat backups change; the business app must be opened at least every ~14 days; messages sent from the app stay free.
   - **Fallback if coexistence isn't offered:** a separate new SIM only for the API (bills and offers). 98231 07676 stays on the app for chats.
2. Add a payment method in Meta Business for per-message charges.
3. Get templates approved:
   - `invoice_ebill` (utility, PDF document): "Hi {{name}}, thank you for visiting Jay Ganesh Car Accessories! Invoice {{no}} · ₹{{amount}} · {{status}}. Your invoice is attached."
   - later: `quotation_send`, `visit_reminder`, `service_reminder`, `review_request`, and an offer template (marketing).
4. Rough costs (check Meta's current rate card): utility about ₹0.12–0.15 per message; free inside the 24-hour window after the customer messages; marketing about ₹0.8–0.9.

## 8. Phases
- **Phase 1, core:** logins and roles, customers, products (basic), quotations, invoices with PDF, payments, WhatsApp auto e-bill, daily report, audit log, backups. **Done when** the shop stops writing bills by hand.
- **Phase 2, operations:** full inventory, purchases and suppliers, job cards, finance (dues, expenses, cash close, GST/Tally export), monthly admin dashboard.
- **Phase 3, growth:** leads from ads, offers/segments, Meta Custom Audience export, service and review reminders, ad lead → walk-in → sale report.
- **Later options:** a second branch, online orders and payment links, an AI WhatsApp assistant, a mobile app on the same API.

## 9. Open questions (answer before or during Phase 1)
1. GST registered? If yes: GSTIN, HSN codes, and whether they use B2B invoices.
2. Number of staff per role, and their names for the logins.
3. Counter devices: PC, tablet or phone? Bill printer: A4 or 80 mm thermal?
4. Product list or price list, even a photo of the notebook.
5. Do they use Tally or a CA's software for export?
6. ~~MilesWeb mPanel~~ checked 5 Nov 2026: PHP up to 8.5, SSH, cron, Redis and an editable subdomain document root are all available ✅ (ARCHITECTURE.md §12).
7. Old customers: is any data to import (phone contacts, old notebooks)?
8. Payment methods: their UPI ID or QR for the invoice.
9. WhatsApp: coexistence on 98231 07676, or a separate API SIM? (see section 7)

## 10. Log
- 5 Nov 2026: plan created. Decisions: browser app on a subdomain, free on MilesWeb, staff logins with roles, automatic WhatsApp e-bills from 98231 07676.
- 5 Nov 2026: full-stack architecture written in `ARCHITECTURE.md` (Laravel, Blade/htmx/Alpine/Tailwind, MySQL, dompdf, database queue via cron, GitHub Actions deploy, Drive backups). WhatsApp coexistence check is on hold.
- 5 Nov 2026: MilesWeb mPanel checked (read-only). Laravel stack confirmed workable; nothing changed on the server.
- 5 Nov 2026: Phase 1, step 1 done. `crm.` subdomain created on MilesWeb (doc root `crm-app/public`). Laravel 12 app in `automation-engine/crm/app`: staff logins (mobile or email), 5 roles, idle sign-out, Staff and Settings pages, audit log, phone/tablet/desktop shell. 24 tests pass. Not deployed yet.
