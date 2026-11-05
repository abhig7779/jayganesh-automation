# Jay Ganesh CRM: Owner guide

For the Owner. Daily counter jobs are in `STAFF-GUIDE.md`. This file covers staff, settings, price list, reports, offers, ads and backups.

---

## 1. Adding staff and roles

**Steps**
1. Click **Staff**, then **Add staff**.
2. Fill **Full name**, **Mobile number** (this is their login), optional **Email**, **Role**, **Branch**.
3. Type a **Password** (at least 8 characters) and repeat it. Tell them the password in person. They change it later.
4. Click save.

**The roles**

| Role | What they can do |
|---|---|
| Owner | Everything, including Settings, margins and profit |
| Admin | Bills, cancel bills, quotations, customers, stock, job cards, leads, offers, reports, staff. No Settings, no margins, can't change prices |
| Finance | Collect payments, finance, cash close, expenses, reports, view bills and stock |
| Sales / Counter | Customers, quotations and follow-ups, bills, collect payments, view stock, job cards (car in). Sees only their own follow-ups |
| Marketing | Leads, offers, reports, view customers and quotations |
| Technician | Job cards only. Sees no money |

The **Role access** panel on the staff page shows the ticks for the chosen role.

**Extra access (per person)**
Only the Owner sees this panel, on a staff member's edit page. Switch on a single power for one person, on top of their role:
- Ad & WhatsApp leads
- Send offers to customers
- Reports
- Finance (view)
- Cancel bills
- Change prices
- Edit products & stock

A line saying **In role** means the role already has it.

**Other staff jobs**
- *Reset a password:* open the person, type **New password**, save. (Leave empty to keep the old one.)
- *Someone leaves:* untick **Login is switched on** and save. Their history stays.
- You can't change your own role.

---

## 2. Settings

Click **Settings** (Owner only). Changes apply to new bills and quotations.

| Group | What to set |
|---|---|
| **Shop** | Shop name, address (printed on bills), phone, email, Google Maps link |
| **Tax & billing** | **GSTIN** (leave empty if not registered; the app then makes a Bill of Supply with no GST), shop state code (27), default GST % for new products, **invoice prefix** (JG), quotation prefix (JGQ), quotation valid days (7), invoice footer, **UPI ID** (for the QR) and payee name, **pay-later limit for Sales** (in paise: 200000 = ₹2,000), **highest discount % Sales can give** (10), and whether **Print** gives A4 or Thermal 80 mm |
| **WhatsApp** | Sending mode (**Manual** or **Automatic**), business number, template names, "send the e-bill automatically", and "also stop bills to customers who sent STOP" |
| **Offers** | Marketing rate per message in paise (88 = ₹0.88), used only for the cost estimate |
| **Finance** | Opening cash in the drawer each morning (in paise: ₹2,000 = 200000), owner WhatsApp number for the day-close summary |
| **Quotations & follow-ups** | **Follow-up days** after a quote (default `1, 3, 6, 10`), the six WhatsApp texts for Day 1, Day 3, Day 6, Day 10, visit day and price enquiry, and the terms printed on quotes |

**Texts can use blanks** like `{name}`, `{car}`, `{items}`, `{amount}`, `{valid_until}`, `{pdf_link}`, `{shop}`. Leave a text empty to use the built-in one.

**Good to know**
- Money fields are in **paise**. Multiply rupees by 100.
- WhatsApp must stay on **Manual** until the number is connected to Meta (see `MORNING-CHECKLIST.md`).
- An empty UPI ID means no QR on bills.

---

## 3. Importing the price list

**When:** first set-up, and whenever prices change in bulk.

**Steps**
1. Click **Stock**, then **Import price list**.
2. Click **Download a blank template (.csv)** if you need one. Columns: Name, Brand, Category, Selling price, MRP, GST %, HSN, Stock, SKU, Also called, Type, Unit, Shelf, Reorder level, Warranty.
3. Fill it in Excel. Any column names work. The app guesses (Item, Brand, Rate, Code).
4. **Step 1:** choose the file (.xlsx or .csv, up to 5,000 rows, first sheet is read).
5. **Step 2:** check **Which column is which?** and the header row. Look at the preview. Red rows have problems and will be skipped.
6. Tick "the stock column is today's count" only if you want stock corrected for items we already have.
7. **Step 3:** confirm. The result shows new items added, updated, unchanged and skipped (with the reason).

**Good to know**
- Items match by **SKU**, or by name plus brand if there is no SKU. Importing the same file twice changes nothing.
- Stock for new items is saved as opening stock.
- "Also called" names (seat kavar, music system) make counter search easier.
- Add or edit one product by hand with **Add product**. Fix a single stock count on the product page.

---

## 4. Reading Home: "Needs attention"

The **Needs attention** box on your Home page lists what to act on. Click a line to open it. It shows only what applies:

| Line | What it means | What to do |
|---|---|---|
| Overdue follow-ups | Quotes going cold | Open Follow-ups. Ask staff to log results |
| Quotations open for over a week | Old quotes | Chase or close them |
| Dues over 15 days | Customers owing for a long time | Open Collect payment and call them |
| WhatsApp bills not sent | The e-bill failed | Open the WhatsApp log, **Resend** or send manually |
| Bills cancelled today | Who cancelled and why | Check the reason |
| Items low on stock | At or under reorder level | Reorder |
| Draft bills to finish | Bills left half-made | Open and finish or discard |
| Cash close not done | Yesterday has no cash close | Do it now (Finance, Cash close) |

Below it you also see today's business, follow-ups, staff today, and the cars in the workshop. If it says "Nothing needs you right now", you are clear.

---

## 5. Reports and Excel for the CA

**Steps**
1. Click **Reports**.
2. Pick the report: **Daily sales, Sales by staff, Top products, GST summary, Monthly P&L, CA / Tally export**.
3. Pick the period: Today, Yesterday, This week, This month, Last month, or Custom (From and To).
4. Click **Print**, or **Export to Excel**.

**For the CA each month**
1. Open **CA / Tally export**, set **Last month**, click **Export to Excel**.
2. Also open **GST summary** (Last month) and export it.
3. Use **Download sales register (.xlsx)** (one row per bill with CGST, SGST, IGST, total, paid, due) and **Download expense register (.xlsx)**.
4. Email the files to the CA.

**Good to know**
- **Monthly P&L** and margins are for the Owner. **Sales by staff** is for roles with "All staff numbers".
- There is no direct Tally file. The CA gets Excel.

---

## 6. Offers to customers

Click **Offers** (Owner, Admin and Marketing). Customers who sent STOP are never included.

**Steps**
1. Click **New offer**.
2. **Who gets it:** choose filters (car make and model, last visit, total spent, area, how they came, tags, a product or category bought). The live count shows how many customers. To reuse the same group, click **Save list**.
3. **Message:** write the offer name (only for you) and the message.
4. **How to send:** pick one.
   - **Make broadcast list (free):** download the list and import it to Google Contacts on the shop phone. In WhatsApp Business use Chats, **⋮**, **New broadcast** and paste the message. Only customers who saved the shop number receive broadcasts.
   - **Start sending one by one (free):** the app opens WhatsApp for each customer. Press Send there, come back and press **Sent**. Progress is saved.
   - **Send offer (paid, automatic):** only after WhatsApp is switched to Automatic. The page shows the estimated cost. A marketing message costs about ₹0.86 each.
5. Use **Save as draft** to finish later.

**Free broadcast list vs paid automatic**

| | Free list | Paid automatic |
|---|---|---|
| Cost | ₹0 | about ₹0.86 per message |
| Effort | You press Send per chat or paste into a broadcast | One click |
| Works today | Yes | Not until the number is connected |

---

## 7. Meta audience export for ads

**Steps**
1. Click **Offers**, then **Meta ads audience**.
2. **Which customers:** pick a saved list (build lists in New offer first).
3. **Extra columns:** tick more columns for a better match rate. The phone is always included.
4. Click **Download hashed CSV** (safer, scrambled in your browser) or **Download plain CSV**. Delete the plain file after upload.
5. In Meta **Ads Manager, Audiences, Create audience, Custom audience, Customer list**, upload the file. Name it with the date (for example "JG customers Nov 2026").
6. Make a **Lookalike** from it, location India, and narrow the ad set to Pune.
7. Exclude this list from "new customer" ads.

**Good to know:** opted-out customers are left out. Every download is logged at the bottom of the page. Lists under about 100 matched people are too small for Meta.

---

## 8. Backups

You don't do anything daily. This is automatic:
- Every night at 23:30 the CRM makes an encrypted backup on the server and keeps 30 days.
- At 01:00 a copy goes to Google Drive (folder **CRM Backups**, newest 30).
- If a backup is missing or old, you get an email.

**You must keep safe:** the **backup password** (`BACKUP_ARCHIVE_PASSWORD`) and the **APP_KEY** line. Keep both in your password manager. Without the backup password, old backups can't be opened.

**Check health:** open `/health` on the CRM (Owner only). Everything should be green. Red lines are explained in `DEPLOY.md`.

**Once a month** the developer restores last night's backup into a test copy to prove it works (`RESTORE.md`). In a real emergency, call the developer. Don't try to fix the server yourself.

---

## 9. Who to contact

| Problem | Who |
|---|---|
| A staff member forgot the password | You or an Admin (Staff, New password) |
| The CRM is down, red on /health, or a deploy broke it | The developer (Marketix) |
| WhatsApp payment, number or template question | Meta Business support, then the developer |
| Hosting, database, SSL, email box | MilesWeb support |
| GST or filing | Your CA |

Developer contact: add the name and number here: ____________________

---

## Coming later (not built yet)

- Supplier purchases, stock-in and supplier payables.
- Activity log page and My profile (staff change their own password).
- Automatic WhatsApp bills, receipts and offers (needs the WhatsApp number connected).
- Meta lead ads flowing in automatically (leads are typed in by hand today).
- Service reminders and review requests.
- Direct Tally export (Excel only today).
- Marathi screens.
