# Jay Ganesh CRM: page-by-page plan and flows

This is what every page does, who sees it, and how each daily job flows from first tap to done. Design and build follow this file. Companion to `CRM-PLAN.md` (what) and `ARCHITECTURE.md` (how). Written 5 Nov 2026.

> **Owner decisions, 5 Nov 2026:**
> 1. **Counter staff work on a desktop PC.** The desktop is the primary design: the one-page POS, keyboard shortcuts, wide tables, and two-pane lists. Phones are for the owner and technicians on the floor.
> 2. **Item search must be instant and easy.** The whole product list is cached in the browser and searched as you type (no server wait). Matching:
>    - word-prefix matching, with tolerance for one typo (e.g. "gleem" finds Gleam);
>    - "Also called" names on every product (e.g. "seat kavar", "music system");
>    - a barcode scan or an exact SKU adds the item at once;
>    - `2*gleam` adds two;
>    - the empty box shows Recent and Popular items;
>    - after each add, the cursor stays in the box, ready for the next item.
> 3. **Marketing stays off the counter.** Ad/WhatsApp leads, offers and audiences belong to a **Marketing** role, or the Owner switches them on per person (Staff → Extra access). Counter (Sales) accounts only see their own quotation follow-ups.
> 4. **Normal price vs our price.** Every product has a **Normal price (MRP)** and **Our price**. The bill shows:
>    - the MRP struck through next to our price;
>    - **"You save ₹X"** on the POS, the bill PDF, the thermal print, quotations and the WhatsApp message.
>    - GST is always on the actual selling price.

---

## 0. Ease-of-use rules (for staff who aren't techy)

These apply on every screen. If a screen breaks one of them, the screen is wrong.

1. **One main job per screen.** It has one big yellow button that says exactly what happens ("Save bill & send WhatsApp"), not "Submit".
2. **The mobile number is the key to everything.** Type 10 digits anywhere and the app finds the customer. Nobody needs to remember an ID.
3. **Three taps for a normal bill.** Customer → items → pay. No forms with 20 fields; extra details are optional and folded away under "More details".
4. **Nothing is lost.** Half-made bills save as drafts automatically. If the internet drops, the screen says so and keeps what was typed.
5. **No dead ends.** Every success screen offers the next obvious step ("New bill", "Print", "Back to home"). Every empty list explains what to do ("No customers yet. Tap + Add customer").
6. **Mistakes are cheap.**
   - Undo for 10 seconds after small actions (remove item, delete draft).
   - Real changes, like cancelling a bill, need a reason and an Admin/Owner. The bill is never deleted.
7. **Plain words.** Use "Bill" not "Invoice", "Due" not "Receivable", "Car in" not "Job card created". Errors are written for humans: "This number already belongs to Rahul Pawar. Open his profile?"
8. **Big targets.** Buttons 48 px tall on phones, with at least 8 px between tap targets. Amount fields open the number keypad, and the mobile field accepts any format (98231 07676, +91…, 098…).
9. **Show money the Indian way:** ₹1,23,450. Totals are always visible at the bottom while building a bill.
10. **Role-aware.** Staff only see the buttons they're allowed to use. No greyed-out walls of "Access denied".
11. **Speed on the counter PC.**
    - Keyboard shortcuts: F2 new bill, `/` search, Ctrl+Enter save, Esc back.
    - The cursor always starts in the right field.
12. **Language.** English UI for now (the owner's rule for customer-facing text). Marathi labels can be added later as a setting, without code changes to the screens.

---

## 1. Page map

| # | Page | Phone | Counter PC / tablet | Who sees it |
|---|---|---|---|---|
| 1 | Sign in | ✔ | ✔ | Everyone |
| 2 | **Home** (role-aware) | Tab: Home | Dashboard | Everyone, with different content per role |
| 3 | **New bill** (3 steps) | Centre ➕ button | POS screen (one page) | Owner, Admin, Sales |
| 4 | Bill done | ✔ | ✔ (side panel) | Same |
| 5 | Bills list + bill detail | Tab: Bills | Sidebar: Bills | Owner, Admin, Finance, Sales (own bills only for Sales) |
| 6 | Quotations list + new quotation | More | Sidebar | Owner, Admin, Sales |
| 6a | **Follow-ups** (quotes + leads to chase today) | Home, top section + its own list | Sidebar, with a count badge | Owner, Admin, Sales |
| 7 | Customers list + customer profile | Tab: Customers | Sidebar | Owner, Admin, Sales, Finance (view) |
| 8 | Collect payment (dues) | Home action | Home action | Owner, Admin, Finance, Sales |
| 9 | Job cards (car in → delivered) | More / Home for technician | Sidebar | Owner, Admin, Sales (create), Technician (own) |
| 10 | Stock & prices | More | Sidebar | Everyone except Technician; Sales sees price, not cost |
| 11 | Purchases (stock in) | — | Sidebar | Owner, Admin, Finance |
| 12 | Finance: cash close, expenses, dues | More | Sidebar | Owner, Finance (Admin view only) |
| 13 | Leads (WhatsApp / ad enquiries) | More | Sidebar | Owner, Admin, Sales |
| 14 | Offers (send on WhatsApp) | More | Sidebar | Owner, Admin |
| 15 | Reports | More | Sidebar | Owner, Admin, Finance |
| 16 | Staff & roles | More | Sidebar | Owner, Admin (limited) |
| 17 | Settings | More | Sidebar | Owner |
| 18 | Activity log | More | Sidebar | Owner, Admin |
| 19 | My profile (change password) | More | Top-right menu | Everyone |

**Phone navigation** works like a real app:
- **Bottom tabs:** Home · Bills · **➕ New bill** (raised centre button) · Customers · More.
- **Large titles, with a sticky search under the title.**
- **Bottom sheets instead of pop-ups:** pick car, payment mode, confirm cancel.
- **Swipe a bill row** to resend it on WhatsApp or print it.
- **Pull down to refresh.**

**Counter PC / tablet navigation:**
- **Left sidebar** with all sections.
- **Top bar:**
  - global search (mobile no., name or MH12 plate);
  - a "New bill (F2)" button;
  - the user menu.

**Global search** is on every page. One box finds:
- customers (by number or name);
- cars (by plate);
- bills (by number, e.g. JG-2526-0142);
- products (by name or SKU).

Results are grouped by type, and Enter opens the top result.

---

## 2. Home page per role

### Sales / Counter (most used screen)
1. **Search bar** at the top: "Mobile no., name or car number".
2. **Big action tiles** (2 columns on the phone, a row on the PC), in order of daily use:
   - **New bill** (yellow, biggest)
   - **Add customer**
   - **New quotation**
   - **Collect payment**
   - **Car in (job card)**
   - **Check stock / price**
3. **My today:** my bills count, my sales ₹, cash in hand that I collected.
4. **Follow-ups today** (§3.2), shown *above* the stats when any are due: overdue rows in red first, each with WhatsApp / Call / Log result buttons. The Home tab shows the count as a badge.
5. **Waiting on me:** draft bills to finish.
6. **Recent bills:** last 5, with the WhatsApp tick status (✓ sent, ✓✓ delivered, blue ✓✓ read, ⚠ failed with Resend).

### Owner
1. Search bar.
2. **Today's business strip:** sales ₹, bills, cash vs UPI vs card, new customers, and the average bill value. Each has a small "vs same day last week" in words ("₹4,200 more than last Thursday"), not a fake chart.
3. **Action tiles:** New bill, Collect payment, Send offer, Today's report.
4. **Needs attention:**
   - **overdue follow-ups** (count and ₹ value of open quotes), by staff member;
   - dues over 15 days, with the total and the customer count;
   - failed WhatsApp bills;
   - low stock items;
   - cancelled bills today (who cancelled and why);
   - cash close not done yesterday.
5. **Staff today:** each person's bills and sales.
6. **Cars in workshop:** count by status (Waiting, Working, QC, Ready). Tap to open the job board.
7. **This month:** sales, collected, dues, top 5 products, **quotes → follow-ups on time → won/lost (₹)**, ad leads → walk-ins → bills.

### Admin
Same as the Owner, without margins/profit and without Settings.

### Finance
Search, then tiles: Collect payment, Add expense, Cash close, Supplier payment. Then today's collections by mode, dues ageing (0–15, 15–30, 30+ days), and payables to suppliers.

### Technician
Only **My job cards**: big cards per car with photo, model, plate, the work list as checkboxes, and buttons:
- **Start**
- **Add photo**
- **Mark ready for QC**

No money is shown anywhere.

---

## 3. Key flows, step by step

### 3.1 New bill: the main flow (target under 60 seconds)

**Step 1: Customer**
1. The cursor is in the mobile field and the number keypad opens.
2. The app checks after the 10th digit:
   - **Known customer:** their name, cars (chips like "Creta · MH12 AB 1234"), any pending due (red), and their last visit show up. Tap a car chip, or "+ Add car".
   - **New number:** a small form appears in place with the name (required), car model (pick from a list with search), plate (optional) and source ("How did they hear about us?" chips: Walk-in, Instagram, WhatsApp ad, Google, Friend). The customer is saved as part of the bill.
3. A "Skip, walk-in without number" option exists but asks once, "No WhatsApp bill without a number. Continue?". The owner wants numbers captured, so it shows a soft warning, not a block.

**Step 2: Items**
1. A search box, plus **Popular** chips underneath (the top 12 products, learned from bills) and the **Recent for this car model** (e.g. what Creta owners usually buy).
2. Tapping a product adds one. Each row has a quantity stepper (− 1 +), the price (editable only with the `prices.manage` permission; others can apply a discount up to a limit set in Settings), and a remove swipe with Undo.
3. **Services** (fitting, PPF labour, ceramic coating) are products of type "service" with no stock.
4. An optional **Discount** link: a flat ₹ or % on the bill, with a reason chip ("Festival offer", "Regular customer", "Bargain").
5. A sticky bottom bar always shows the item count, the total with GST, and a **Next: Payment** button.
6. If an item is out of stock, it can still be added with an amber "Not in stock" tag. It never blocks the sale; it warns, and the stock goes negative so the owner sees it.

**Step 3: Payment**
1. The total is shown big.
2. Payment mode chips: **Cash**, **UPI**, **Card**, **Split**, **Pay later (due)**.
   - UPI shows the QR with the amount already filled in, for the customer to scan.
   - Split shows two or three amount fields that must add up; the remaining amount updates live.
   - Pay later needs Admin/Owner permission or a limit from Settings, and records the due.
3. A "Send bill on WhatsApp" toggle, ON by default (OFF if the customer opted out).
4. The big button: **Save bill & send WhatsApp** (Ctrl+Enter on the PC).

**Bill done**
- A green tick, the bill number, the total, and the WhatsApp status updating live (Sending… → Sent ✓ → Delivered ✓✓).
- Buttons: **Print** (A4 or thermal per Settings), **Share** (phone share sheet with the PDF), **New bill**, **Home**.
- If WhatsApp fails: a red line, "Not sent: number not on WhatsApp", with a **Send manually** button that opens WhatsApp with the message and a PDF link.

**Behind the scenes:** the bill number, stock out, payment, customer last-visit and total-spent, and the audit log are saved in one go. The e-bill is sent right after (see ARCHITECTURE §2).

**On the counter PC** all three steps sit on **one POS screen**:
- the left half is product search + popular tiles;
- the right half is the live bill: customer at the top, items, payment at the bottom.

The keyboard can do everything:
- `F2` new;
- `/` product search, ↑↓ Enter to add;
- `+`/`−` changes the quantity of the highlighted line;
- `Ctrl+Enter` saves.

### 3.2 Quotation → follow-up → bill (no more lost quotes)
**The problem (owner, 5 Nov):** we give many quotations and then never follow up, so those customers are lost. From now on, every quotation is a lead with an owner and a follow-up date. The app chases it so staff don't have to remember.

1. **Quick quote** takes under 30 seconds:
   - the customer's mobile (required, because without a number we can't follow up), the car, and the items;
   - "Save & send on WhatsApp" sends the PDF with a validity date (default 7 days).
   - A **phone price enquiry** with no formal quote still gets logged with one tap ("Asked price"): mobile + product + price told. It becomes a follow-up too.
2. **Who follows up:** the staff member who made the quote. The Owner/Admin can reassign it.
3. **Automatic follow-up schedule** (editable in Settings): **Day 1** (a soft check-in), **Day 3** (answer doubts and send photos or reviews of the work), **Day 6** ("the quote expires tomorrow"), then **Day 10** (last try, with an optional small offer that the Owner allows).
4. **"Follow-ups today"** is the first thing on the Sales home, above the stats, with a count badge on the Home tab. Each row shows:
   - the customer, car, quote value, days since the quote, and the last outcome;
   - **WhatsApp** (opens a pre-filled message for that day: polite, English, the quote PDF link included);
   - **Call** (opens the dialer);
   - **Log result.**
5. **Log result** is one tap from a bottom sheet, nothing to type:
   - **Coming on [date]**, which books a visit; the owner sees expected walk-ins;
   - **Thinking / call later**, which picks the next date: tomorrow, +3 days, or a chosen date;
   - **Wants a better price**, which notifies Admin/Owner to approve a revised quote;
   - **Bought elsewhere**, with an optional price and shop. This is lost, with a reason;
   - **Not interested / wrong number**, which closes it.
   - An optional voice note or text. If no result is logged, the follow-up moves to tomorrow and turns **red** after 1 missed day.
6. **Auto-win:** when this mobile number gets a bill, the quote is marked **Won** automatically and its follow-ups stop. If they buy different items, it is still counted as won, linked to the bill.
7. **Optional automatic WhatsApp nudges** (paid template, about ₹0.8 each, off by default): the day-6 "expiring tomorrow" message is sent automatically if staff haven't contacted the customer.
8. **Owner sees the leak:**
   - quotes this month, follow-ups done on time %, converted %, ₹ won vs ₹ lost;
   - the lost reasons (price, bought elsewhere, no reply);
   - per staff member: quotes, follow-ups done, conversion.
   - A red count on the owner's home for **overdue follow-ups**.
9. **Quotation list** status chips: **Open** (follow-up due date shown), **Visit booked**, **Won**, **Lost**, **Expired**, with filters for "Overdue", "Mine" and "This week".
10. **Convert to bill:** when the customer walks in (search by mobile shows "Open quote ₹32,500 from 3 Nov"), tap **Convert to bill**. It goes to Step 3 (Payment) with the items copied, and the prices are still editable.

Leads from WhatsApp/ads (§3.10) use the **same follow-up engine and the same "Follow-ups today" list**, so staff have one list of people to chase, not two.

### 3.3 Collect payment (customer pays an old due)
1. Tap **Collect payment**, then search the customer.
2. Their unpaid bills show oldest first, with a total due.
3. Enter the amount received (pre-filled with the full due) and the mode (Cash/UPI/Card).
4. The money is applied to the oldest bills first automatically. Staff can untick a bill to skip it.
5. **Save** sends a WhatsApp receipt ("Received ₹2,000. Balance ₹1,500.").

### 3.4 Car in → job card → delivered
1. **Car in:** customer + car (same as bill step 1). Then the work items (from products/services) and the promised time.
2. **Photos:** "Take before photos" opens the camera, takes several shots, and uploads them in the background (works on slow internet, with a retry).
3. **Assign the technician.** The job appears on the technician's home.
4. **Status board** (Waiting → Working → QC → Ready → Delivered): drag on the PC, a status button on the phone. Each change is timed and logged.
5. **Ready:** the customer gets an optional WhatsApp: "Your Creta is ready for pickup."
6. **Delivered → Make bill:** the job's items go into a bill at Step 3. After-photos are attached to the customer's history.

### 3.5 Check stock / price (customer at the counter asks "how much?")
1. Tap **Check stock**, then type 2–3 letters ("glea", "creta mat").
2. Each result shows the name, brand, selling price (big), stock count (green, amber when low, red at 0), and the shelf location if it's set.
3. Tap a result for the product detail: price, MRP, warranty, compatible cars, and **Add to new bill** / **Send price on WhatsApp** buttons.

### 3.6 Stock in (purchase from a supplier): Owner / Admin / Finance
1. **Purchases → New purchase:** pick the supplier, the bill number and date, and attach a photo of the supplier bill.
2. Add items with quantity and cost price, either by scanning a barcode with the phone camera (later) or by search.
3. **Save:** stock goes up, the cost is updated, and the supplier balance is updated.
4. Low-stock alerts clear automatically.

### 3.7 Cancel or return a bill (protected)
1. Open the bill, then **⋯ → Cancel bill**. This is only visible to Owner/Admin.
2. A reason is required (chips: Wrong item, Customer returned, Duplicate, Other plus text).
3. Effects:
   - the stock goes back;
   - the payment is reversed or a refund is recorded;
   - the bill keeps its number and shows a big red **CANCELLED** stamp;
   - the owner sees it on their home under Needs attention.
4. A **partial return** makes a credit note for the selected items only.

### 3.8 End of day: cash close (Finance / Owner)
1. **Cash close:** the app shows the expected cash (cash bills + cash due collections − cash expenses).
2. Staff count the drawer and type the actual amount. The difference shows in green or red, and any difference needs a note.
3. **Save:** the owner gets a WhatsApp summary: sales, mode split, bills, dues collected, cash difference.
4. If yesterday's close is missing, it shows on the owner's home next morning.

### 3.9 Send an offer on WhatsApp (Owner / Admin)
1. **Offers → New offer:** choose who gets it with simple filters (car model, last visit before/after, bought product X, area) and see the live count ("142 customers").
2. Pick an approved WhatsApp marketing template and fill its blanks (offer text, valid till).
3. The send preview shows the **estimated cost in ₹** (Meta's marketing rate × count) and that opted-out customers are excluded.
4. **Send now** or **Schedule**. The results show sent, delivered, read and replies.
5. A free option: **Export as a broadcast list** (CSV of numbers) for the WhatsApp Business app.

### 3.10 Leads (enquiries from ads)
1. New WhatsApp/ad enquiries are added by hand at first (later automatically from Meta lead ads and the website form). Enter the mobile number, what they asked for, and the source.
2. Status chips: New → Contacted → Quote sent → Visit booked → Won / Lost (with a reason). Every new lead gets a Day-0 follow-up and joins the same **Follow-ups today** list as quotations.
3. When a lead's number later appears on a bill, it is marked **Won** automatically. This gives the owner the "ad → walk-in → sale" report.

### 3.11 Staff management (Owner)
- Add staff with the name, mobile (this is their login), role and a temporary password.
- **Switch off** a login when someone leaves. Their history stays.
- "Reset password" sets a new temporary password; the person changes it from My profile.

---

## 4. What happens when things go wrong

| Situation | What the staff member sees | What the app does |
|---|---|---|
| Internet drops while making a bill | An amber bar: "No internet. Your bill is saved here. We'll save it when you're back online." | Keeps the draft in the browser and retries; never double-saves (each draft has a unique key) |
| WhatsApp send fails | Red "Not sent" on the bill + a **Send manually** button | Retries at 1, 5 and 15 min; lists it on the owner's home |
| Mobile number typed wrong (9 digits) | "Mobile number needs 10 digits" under the field, as they type | Save is disabled until it's fixed |
| Same customer added twice | "This number already belongs to Rahul Pawar. Open his profile?" | Prevents duplicates (the mobile is unique) |
| Two counters bill at the same moment | Nothing | Bill numbers are locked in the database, so there are never gaps or duplicates |
| Price change needed at the counter by Sales | A "Ask admin" note if over the discount limit | Discounts over the limit need an Admin PIN/approval |
| Someone tries a screen they can't use | The button isn't shown at all | Server blocks it anyway (403) and logs it |
| Forgot password | "Ask the owner or admin to reset it" | Owner resets from Staff |
| Session idle | Signed out with "You were signed out after being idle" | Drafts are kept |

---

## 5. Phone vs counter PC differences

| | Phone (app feel) | Counter PC / tablet |
|---|---|---|
| Layout | Bottom tabs, large titles, bottom sheets, full-screen steps | Sidebar, multi-column, everything on one screen |
| New bill | 3 steps, one per screen | One POS screen |
| Input | Number keypad, camera for photos (and later barcodes), share sheet | Keyboard shortcuts, USB barcode scanner (later), thermal printer |
| Lists | Card rows with swipe actions | Tables with sorting and filters |
| Install | "Add to Home screen" icon (opens full-screen like an app) | Pinned browser tab |

---

## 6. Build order (maps to `CRM-PLAN.md` phases)

| Step | Pages / features | Status |
|---|---|---|
| 1 | Sign in, roles, Staff, Settings, app shell | ✅ Done (5 Nov) |
| 2 | **Design system + mobile/desktop designs** (Stitch), then rebuild the shell + Home per role | In progress |
| 3 | Customers + cars (backend done by agent; screens next) | In progress |
| 4 | Products & stock list (basic), price list import from CSV/Excel | Next |
| 5 | New bill (phone 3-step + PC POS), Bill done, Bills list/detail, print A4 + thermal | Next |
| 6 | WhatsApp e-bill (manual mode first, then Cloud API) | Next |
| 7 | Quotations + **follow-up engine** (schedule, Follow-ups today, Log result, auto-win, owner leak report) + convert to bill; Collect payment | Next (top priority after billing; the owner reports quotes are being lost) |
| 8 | Daily report, cash close, backups → **go live; paper bills stop** | Phase 1 end |
| 9 | Job cards + technician home, purchases, expenses, finance pages | Phase 2 |
| 10 | Leads, Offers, Meta audience export, reminders, full reports | Phase 3 |

**Done means** for every page:
- it works on a 375 px phone and a 1366 px counter screen;
- it has role tests;
- it has empty, error and loading states;
- it takes no more than 3 taps for its main job;
- the owner has tried it on staging with a real product and a test number.
