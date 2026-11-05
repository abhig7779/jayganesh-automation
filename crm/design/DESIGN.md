# Jay Ganesh CRM: design system

The visual and interaction rules for the staff CRM. It follows the page map, roles and flows in `../UX-FLOWS.md`; if the two disagree, UX-FLOWS wins on *what* a page does and this file wins on *how it looks*. Ready-to-paste Stitch prompts are in `STITCH-PROMPTS.md`. Written 5 Nov 2026.

- **Stitch project:** "Jay Ganesh CRM", ID `11246509647416770987` (https://stitch.withgoogle.com/projects/11246509647416770987)
- **Stitch design system asset:** `assets/9062591464773788396` ("Jay Ganesh Counter")
- **Screens made so far:** `stitch/mobile/02-home-counter.html` + `.png` (Sales home, first draft; see "Known gaps" at the end)

---

## 1. Character

A busy counter at a toll-naka shop, not a SaaS landing page. The UI should feel like a good tool: black, white and warm grey, with yellow only where a finger should go. Reference quality: Linear, Stripe Dashboard, Square POS, Zomato Restaurant Partner.

Three tests every screen must pass:
1. **Counter-boy test.** A new staff member knows the one main job of the screen in 2 seconds (UX-FLOWS §0.1).
2. **Rush-day test.** The main action is reachable with a thumb, 48 px or bigger, and never hidden behind a menu.
3. **Slop test.** None of the banned items in §9 appear.

---

## 2. Colour tokens

Nothing outside this table. No blue, no purple, no gradients.

| Token | Hex | Use |
|---|---|---|
| `ink` | `#111111` | Main text, dark surfaces (login, PC sidebar), selected segment text, icons |
| `ink-2` | `#3F3F46` (zinc-700) | Secondary headings, body on white |
| `muted` | `#52525B` (zinc-600) | Secondary text, labels. Never lighter than `#71717A` on white, never grey on grey |
| `faint` | `#71717A` (zinc-500) | Placeholders, timestamps only |
| `line` | `#E4E4E7` (zinc-200) | All 1 px borders and dividers |
| `line-strong` | `#D4D4D8` (zinc-300) | Input borders, stepper borders |
| `canvas` | `#FAFAF9` (stone-50) | Page background |
| `surface` | `#FFFFFF` | Cards, sheets, tables, tab bar |
| `sunken` | `#F4F4F5` (zinc-100) | Segmented-control track, avatar squares, table header row |
| `brand` | `#FFD51C` | Action colour (§2.1) |
| `brand-press` | `#F2C800` | Pressed/hover state of brand |
| `on-brand` | `#111111` | Text and icons on yellow. Never white on yellow |
| `ok` | `#15803D` (text) / `#F0FDF4` (tint) / `#BBF7D0` (border) | Paid, sent, delivered, read, ready, in stock |
| `warn` | `#B45309` (text) / `#FFFBEB` (tint) / `#FDE68A` (border) | Pending, draft, working, low stock, offline bar |
| `bad` | `#DC2626` (text) / `#FEF2F2` (tint) / `#FECACA` (border) | Due, failed, cancelled, out of stock, cash short |

### 2.1 Where yellow is allowed
At most two yellow things per screen:
- the one primary button (New bill, Next: Payment, Save bill & send WhatsApp, Collect, Sign in);
- the raised centre New bill button in the tab bar;
- the active-tab indicator bar (3 px) and the active sidebar item's left bar on PC;
- a key-number highlight (for example the bill total on the payment step) as a yellow underline or a yellow total bar, never yellow text on white.

### 2.2 WhatsApp status ticks
UX-FLOWS asks for "blue ✓✓ read". Blue isn't in the palette, so this design uses:

| State | Mark | Colour |
|---|---|---|
| Sending… | clock outline | `faint` |
| Sent | single tick `done` | `muted` |
| Delivered | double tick `done_all` | `ink` |
| Read | double tick `done_all`, filled weight | `ok` green |
| Failed | `error` outline + "Resend" link | `bad` |

Owner decision needed (see §12): keep green "Read" or allow WhatsApp blue `#53BDEB` for this one mark.

---

## 3. Typography

One family: **Inter** (fallback system-ui). Money and counts always use `font-variant-numeric: tabular-nums`.

| Token | Size / line | Weight | Tracking | Use |
|---|---|---|---|---|
| `display` | 34 / 40 | 700 | -0.02em | Phone large titles ("Today", "Bills", "Customers") |
| `title-lg` | 22 / 28 | 700 | -0.01em | Section titles on PC, sheet titles |
| `title-md` | 17 / 24 | 600 | 0 | Card titles, list-row titles on phone, button text |
| `body` | 15 / 22 | 400 | 0 | Body text, inputs |
| `body-sm` | 13 / 18 | 500 | 0 | Row subtitles, table cells on PC |
| `label` | 12 / 16 | 600 | 0.06em, UPPERCASE | Section headers ("MY TODAY", "NEEDS ATTENTION") |
| `micro` | 11 / 14 | 600 | 0 | Badges, tick status, key hints |
| `money-xl` | 40 / 44 | 700 | -0.02em | Total on payment step and Bill done |
| `money-lg` | 24 / 28 | 700 | -0.01em | Stat strip numbers |
| `money-md` | 15 / 20 | 600 | 0 | Row amounts |

Money format: `₹1,23,450` (Indian grouping), no decimals unless there are paise (`₹4,999.50`). Negative or short amounts use the minus sign and `bad` colour: `−₹350`.

---

## 4. Shape, spacing, elevation

- **Spacing scale (px):** 4, 8, 12, 16, 24, 32, 48. Phone side gutter 16. PC content gutter 24–32.
- **Radius:** controls 8, cards 12, bottom-sheet top corners 16, avatar squares 8. Only chips, badges and the centre tab button are fully round. No pill-shaped buttons.
- **Borders over shadows.** 1 px `line` on every card and table. The only shadows: bottom sheet (`0 -8px 24px rgba(0,0,0,.08)`) and the raised centre tab button (`0 2px 6px rgba(0,0,0,.12)`).
- **Touch targets:** 48 px minimum on phone, 8 px apart. Primary buttons 52–56 px tall on phone, 40 px on PC.
- **Icons:** Material Symbols **Outlined**, weight 400, 22–24 px on phone, 20 px on PC. Filled variant only for the active tab icon. No other icon set, no illustrations.

---

## 5. Components

### Buttons
| Variant | Look | Use |
|---|---|---|
| Primary | `brand` bg, `on-brand` text 17/600, radius 8, 56 px phone / 40 px PC, full-width on phone | The one main job. Label says what happens: "Save bill & send WhatsApp", never "Submit" |
| Secondary | white, 1 px `line-strong`, `ink` text | Print, Share, Add car |
| Ghost | no border, `ink` text, underline on hover | "See all", "More details" |
| Danger | white, 1 px `bad` border, `bad` text | Cancel bill (Owner/Admin only) |
| Icon button | 44×44 hit area, 24 px icon | Call, WhatsApp, more `⋯` |
| WhatsApp button | white, 1 px `line-strong`, ink WhatsApp-style chat icon (`chat` outline), label "WhatsApp" | Never WhatsApp-green fill |

### Inputs
- 52 px phone / 40 px PC, white, 1 px `line-strong`, radius 8, 15 px text. Focus: 2 px `ink` ring. Error: 1 px `bad` border + 13 px `bad` helper text under it ("Mobile number needs 10 digits").
- **Mobile field:** `+91` fixed prefix inside the field, `inputmode="numeric"`, formats as `98231 07676` while typing.
- **Amount field:** `₹` prefix, numeric keypad, right-aligned tabular numbers.
- **Search field:** magnifier left, clear `×` right, placeholder "Mobile no., name or car number". On PC it shows a `/` key hint.

### List row (phone)
64–72 px, white, divider 1 px `line` inset 16 px. Leading 40 px avatar square (`sunken`, initials 13/700) or a car icon; title 15/600; subtitle 13/500 `muted`; trailing amount `money-md` with a badge or tick line under it. **Swipe left** reveals two 72 px actions: "WhatsApp" (ink bg, white text) and "Print" (zinc-700 bg). No red swipe-to-delete anywhere, because bills are never deleted.

### Table (PC)
Header row `sunken`, `label` style. Rows 44 px, 13/500, money right-aligned tabular. Hover row `#FAFAFA`. Sticky header. Sort arrow on sortable columns. Row click opens a right-side detail panel, not a new page.

### Status badge
Fully round, 11/600, 2×8 padding, tint background + matching text + 1 px border. Set: `Paid` ok, `Due` bad, `Part paid` warn, `Draft` warn, `Cancelled` bad, `Sent / Viewed / Won / Expired` (quotations: ink outline, ink, ok, muted), job status `Waiting` (muted outline), `Working` warn, `QC` ink outline, `Ready` ok, `Delivered` muted.

### Money display
- Stat: `label` caption above, `money-lg` number, one line of plain-words comparison under it in 12/500 `muted` ("₹4,200 more than last Thursday"; `ok` or `bad` arrow icon only, no sparkline).
- Bill total bar: left "3 items · incl. GST ₹1,372", right total `money-lg`.

### Bottom tab bar (phone)
White, 1 px top `line`, 64 px + safe area. Five slots: **Home · Bills · (New bill) · Customers · More**. Icons outline, labels 11/600. Active: `ink` icon (filled) + label, a 3 px × 24 px `brand` bar above the icon. Inactive: `muted`. Centre: 56 px `brand` circle, raised 16 px, ink `add` icon 28 px, label "New bill" under it. Solid white, no blur.

### Bottom sheet (phone)
White, 16 px top radius, 36×4 px grab handle in `line-strong`, sheet title `title-lg` left-aligned with a close `×` right, content, then a sticky primary button. Backdrop `rgba(17,17,17,.4)`. Used instead of every modal (list in §8).

### Segmented control
Track `sunken`, radius 8, 4 px inset; selected segment white with 1 px `line` and `ink` 600 text; others `muted`. 44 px tall on phone. Used for: Today / Week / Month, Cash / UPI / Card / Split / Later, Phone list filters.

### Chips
- **Car chip:** 40 px, white, 1 px `line-strong`, radius full, car icon + "Creta · MH12 AB 1234". Selected: `ink` bg, white text.
- **Product chip (Popular / Recent for this car):** 40 px, white, border, name + price in `muted` ("Gleam 2.0 · ₹4,999").
- **Reason chip:** same, single-select.

### Quantity stepper
96×40, 1 px `line-strong`, radius 8: `−` | tabular qty | `+`, each hit area 40 px. When qty would go to 0, the row is removed with a 10-second **Undo** snackbar.

### Snackbar / Undo
Ink bg, white 14/500 text, "Undo" in `brand` on the right, 10 s, above the tab bar.

### Offline bar
Full-width `warn` tint strip under the header: "No internet. Your bill is saved here. We'll save it when you're back online."

### Empty state
One 32 px outline icon in `muted`, one line of 15/500 `ink-2` text that says what to do, one secondary button. Example: icon `group`, "No customers yet. Add the first one from a bill.", button "+ Add customer". No illustrations.

### Loading
Skeleton rows in `sunken` (no shimmer gradient), buttons show a 16 px spinner and keep their label.

---

## 6. Layout shells

### Phone (375–430 px; designs at 390×844)
- Status bar, then a compact top row (logo mark + "Jay Ganesh", role · name, bell), then a **large title** that shrinks into the top row on scroll.
- **Sticky search** directly under the title.
- Content in 16 px gutters, sections separated by 24 px with a `label` header and an optional ghost link on the right.
- Bottom tab bar on all top-level pages; flows (New bill steps, Collect payment, Car in) are **full-screen** with a back chevron, step title "Step 2 of 3", and their own sticky bottom bar instead of the tab bar.

### Counter PC / tablet (1366–1440 px; designs at 1440×900)
- **Left sidebar 240 px, `ink` background**: white logo at top, section links (Home, New bill F2, Bills, Quotations, Customers, Collect payment, Job cards, Stock & prices, Purchases, Finance, Leads, Offers, Reports, Staff & roles, Settings, Activity log) with 20 px outline icons, 14/500 zinc-300 text, active = white text + 3 px `brand` left bar + `#1C1C1C` bg. Role decides which links exist (no greyed links).
- **Top bar 64 px, white, 1 px bottom border:** global search (480 px, `/` hint), a yellow "New bill · F2" button, then the user menu (initials, name, role).
- Content on `canvas`, max width 1200, 32 px gutters. Tables over cards. Detail opens in a 480 px right panel.

---

## 7. Home per role (from UX-FLOWS §2)

| Role | Order of blocks (phone, top to bottom; PC, same order in a 12-column grid) |
|---|---|
| **Sales / Counter** | 1 Search · 2 Action tiles in **2 columns**: New bill (yellow, full-width, 72 px), then Add customer, New quotation, Collect payment (shows "₹18,400 due" in `bad`), Car in (job card), Check stock / price · 3 **My today**: my bills, my sales ₹, cash in hand I collected · 4 **Waiting on me**: drafts, quotations not converted in 7 days with "Follow up" (WhatsApp), leads assigned · 5 **Recent bills**: last 5 with tick status, swipe to resend/print |
| **Owner** | 1 Search · 2 **Today's business strip**: Sales, Bills, Cash / UPI / Card split, New customers, Avg bill, each with a plain-words "vs last Thursday" line · 3 Tiles: New bill, Collect payment, Send offer, Today's report · 4 **Needs attention**: dues over 15 days (₹ + count), failed WhatsApp bills, low stock, cancelled bills today (who + why), cash close missing · 5 **Staff today** (name, bills, sales, bar against the day's top) · 6 **Cars in workshop**: counts by Waiting / Working / QC / Ready, tap → job board · 7 **This month**: sales, collected, dues, top 5 products, Ad leads → walk-ins → bills funnel as three numbers |
| **Admin** | Owner layout without margins/profit and without Settings links |
| **Finance** | Search · tiles: Collect payment, Add expense, Cash close, Supplier payment · today's collections by mode · dues ageing 0–15 / 15–30 / 30+ · payables to suppliers |
| **Technician** | Only **My job cards**: one big card per car (photo, model, plate, work list as checkboxes, promised time) with Start / Add photo / Mark ready for QC. No money anywhere. Tab bar becomes Jobs · Photos · More |

---

## 8. Screens and interactions

### Screen list (Stitch prompts in this order in `STITCH-PROMPTS.md`)
| # | Screen | Device | UX-FLOWS ref | File |
|---|---|---|---|---|
| M1 | Home, Sales / Counter | Phone | §2 Sales | `stitch/mobile/02-home-counter.*` (draft, needs a redo, see §11) |
| M2 | Home, Owner | Phone | §2 Owner | — |
| M3 | New bill step 1: Customer | Phone | §3.1 | — |
| M4 | New bill step 2: Items | Phone | §3.1 | — |
| M5 | New bill step 3: Payment | Phone | §3.1 | — |
| M6 | Bill done | Phone | §3.1 | — |
| M7 | Customer profile | Phone | page 7 | — |
| M8 | Check stock / price | Phone | §3.5 | — |
| M9 | More menu | Phone | §1 | — |
| D1 | Owner dashboard | PC | §2 Owner | — |
| D2 | POS billing (one page) | PC | §3.1 PC | — |
| D3 | Customers list | PC | page 7 | — |
| D4 | Job board | PC | §3.4 | — |
| Extra | Sign in (dark), Technician home, Collect payment, Staff & roles | Both | §1, §2, §3.3, §3.11 | — |

### What each tap does
| Where | Tap / key | Result |
|---|---|---|
| Any page | Search field / `/` | Opens full-screen search (phone) or dropdown (PC). Results grouped Customers · Cars · Bills · Products; Enter opens the top one. 10 digits typed = jump to that customer |
| Tab bar | Centre ➕ / `F2` | New bill step 1 (phone) or POS (PC), cursor in mobile field |
| Home | Collect payment tile | Collect payment flow (§3.3): search customer → unpaid bills oldest first → amount (pre-filled) → mode → "Collect ₹X & send receipt" |
| Home | Car in tile | Car in flow: customer + car → work items → promised time → before photos → assign technician |
| Home | Check stock tile | Stock search, keyboard open |
| Home (Sales) | "Follow up" on a quotation | Opens WhatsApp with the pre-filled polite message |
| Recent bill row | Tap / swipe left | Bill detail / WhatsApp resend + Print |
| Failed tick | "Resend" | Retries; if still failing, "Send manually" opens WhatsApp with message + PDF link |
| Step 1 | 10th digit typed | Known: name, car chips, red due, last visit. New: inline mini form (name, car model, plate, source chips) |
| Step 1 | "Skip, walk-in without number" | Sheet: "No WhatsApp bill without a number. Continue?" |
| Step 2 | Product chip / result | Adds 1; stepper appears; out of stock adds with amber "Not in stock" |
| Step 2 | Swipe row left | Remove, with 10-second Undo |
| Step 2 | "Discount" link | Discount sheet |
| Step 2 | Next: Payment | Step 3 |
| Step 3 | Mode segment | Cash: received + change. UPI: QR with amount. Split: 2–3 amount fields with live "Remaining". Pay later: needs permission, records due |
| Step 3 | Save bill & send WhatsApp / `Ctrl+Enter` | Saves in one go → Bill done |
| Bill done | Print / Share / New bill / Home | A4 or thermal per Settings / phone share sheet with PDF / step 1 / Home |
| Customer profile | Call / WhatsApp | `tel:` / opens WhatsApp chat |
| Customer profile | "Collect ₹X" (when due) | Collect payment with this customer pre-picked |
| Stock result | Tap | Product detail sheet: price, MRP, warranty, compatible cars, "Add to new bill", "Send price on WhatsApp" |
| Bill detail | `⋯ → Cancel bill` (Owner/Admin) | Cancel sheet with reason chips; bill gets red CANCELLED stamp |
| PC job board | Drag card | Moves status; logged with time |

### Bottom sheets (phone)
1. **Pick car / Add car** (model search list, plate, year)
2. **Walk-in without number** confirm
3. **Discount** (₹ / % segmented, amount, reason chips: Festival offer, Regular customer, Bargain; over the limit shows "Needs admin approval")
4. **Edit line price** (only with `prices.manage`)
5. **Payment mode: Split** amounts (when the screen is too short)
6. **UPI QR** full-size for the customer to scan
7. **Product detail** (from stock check)
8. **Cancel bill** (reason chips + text, Owner/Admin)
9. **Job status** change (Waiting / Working / QC / Ready / Delivered)
10. **Filter** for Bills and Customers lists
11. **Share** uses the native phone share sheet, not a custom one

---

## 9. Banned (the AI-slop list)

If any of these show up in a Stitch output, fix it with an edit or regenerate:
- purple, blue or teal accents; gradients of any kind, including gradient text; glassmorphism or `backdrop-blur`; neon glows; coloured drop shadows;
- emoji anywhere in the UI; 3D or flat illustrations; decorative blobs, waves, dotted patterns;
- "Welcome back, Rohit 👋" hero cards or greeting banners;
- a grid where every card is the same size and weight (the New bill action must clearly outrank the rest);
- fake charts with no axis or meaning (line sparklines on every stat, donut "goal" rings); use numbers and plain-words comparisons;
- pill-shaped everything; fully round buttons other than the centre tab button;
- thin grey text on grey backgrounds; text lighter than `#71717A` on white;
- lorem ipsum, "John Doe", "$", "Acme", US phone or date formats;
- vague labels: "Submit", "Initiate transaction", "Manage", "Invoice" (say Bill), "Receivable" (say Due);
- mixed icon sets, filled and outline mixed (except the active tab);
- WhatsApp-green filled buttons.

---

## 10. Realistic content kit

- **Shop:** Jay Ganesh Car Accessories, Kawadipat Toll Naka, Loni Kalbhor, Pune 412201.
- **Staff:** Prakash Jadhav (Owner), Rohit Shinde (Counter), Sagar More (Sales), Pooja Deshmukh (Finance), Sunil Gaikwad and Akash Pawar (Technicians).
- **Customers:** Rahul Pawar 98231 04512, Sneha Kulkarni 98231 07788, Amit Jadhav 98230 11245, Vikram Shinde 97654 32109, Priya Deshpande 99701 23456, Ganesh Kale 98220 45671.
- **Cars:** Hyundai Creta MH12 KT 4521, Toyota Innova Hycross MH12 VR 7703, Mahindra Scorpio-N MH14 JD 2290, Maruti Swift MH12 PQ 1188, Tata Nexon MH12 RD 8890.
- **Products (sale price incl. GST 18%):** DHC Gleam 2.0 LED headlight ₹4,999 · Autoform seat covers (Creta) ₹8,500 · 7D floor mats ₹3,200 · Garware PPF full body ₹65,000 · 3M PPF bonnet + bumper ₹18,000 · Ceramic coating 9H ₹14,999 · Android stereo 9" 2+32 GB ₹11,500 · Fitting charge ₹500 (service).
- **Bill numbers:** `JG-2526-0142` style (financial year 25-26). See §12.
- **UPI ID:** shown as `jayganesh@upi` placeholder until the owner gives the real one.

---

## 11. Known gaps in what Stitch made so far

`stitch/mobile/02-home-counter.html` (Stitch screen `593a90e9f3fb43e0bcfb451060e0e2cf`) was generated before UX-FLOWS.md existed. Good: hierarchy, one yellow button, tabular money, 1 px borders, no emoji or gradients. Wrong against this spec, fix when re-running prompt M1:
- the tab bar says **Workshop** instead of **Customers**, uses `storefront` for Home, and has `backdrop-blur` (banned);
- the tile reads "Collect cash" (should be "Collect payment") with a redundant "Due" badge;
- tiles are 3 columns with a "Send offer" tile; Sales gets 2 columns and no Send offer (that is an Owner tile);
- it shows "In workshop" instead of **Waiting on me** (drafts, quotes to follow up, leads);
- My today lacks "cash in hand I collected";
- the logo mark is a generic car icon; use the real `logo.png` (white shield, yellow car) on an ink square;
- recent bills use `JG-2026-0412` numbers.

---

## 12. Decisions for the owner

1. **Bill number format:** CRM-PLAN says `JG-YYYY-NNNN`, UX-FLOWS shows `JG-2526-0142` (financial year). Financial year is usual for GST; pick one.
2. **"Read" tick colour:** green (stays in palette) or WhatsApp blue for that one mark.
3. **Logo on light screens:** the logo is white-on-dark. Phone headers use it on a small ink square; a dark-ink version of the wordmark would be cleaner for white PC top bars.
4. **Marathi labels later:** layouts leave ~30% spare width on buttons and tiles for it.
