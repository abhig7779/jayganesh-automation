# Stitch prompts: Jay Ganesh CRM

Paste these into Stitch in Chrome (stitch.withgoogle.com). Pages and flows follow `../UX-FLOWS.md`; tokens and components follow `DESIGN.md`.

**How to use**
1. Open the project "Jay Ganesh CRM" (ID `11246509647416770987`) or start a new one.
2. Paste the **Theme** block below first (as the design system / DESIGN.md, or as the first message).
3. Then paste one prompt per screen, in the order listed. Use **App** mode for M-prompts and **Web** mode for D-prompts.
4. After each result, check it against the "Reject if" line at the end of each prompt and use Stitch's edit box to fix what fails.

Every prompt repeats a short token line, so each one also works on its own.

---

## Theme (paste first)

```
Jay Ganesh CRM: staff app for a car accessories and detailing shop in Pune, India (Jay Ganesh Car Accessories, Kawadipat Toll Naka, Loni Kalbhor). Used all day at a busy counter and in the workshop by staff who are not techy. Premium, calm, utilitarian: Linear, Stripe Dashboard, Square POS quality.

COLOURS (nothing else): ink #111111 for text, icons and dark surfaces; white #FFFFFF cards; canvas #FAFAF9 page background; zinc greys: borders #E4E4E7, input borders #D4D4D8, secondary text #52525B, placeholders #71717A, sunken fills #F4F4F5. Brand yellow #FFD51C ONLY for the single primary button per screen (black text on yellow), the raised centre New bill tab button, the active tab/sidebar indicator, and a total highlight. Semantic only as small badges with a 10% tint: green #15803D paid/sent/read/ready/in stock, amber #B45309 pending/draft/working/low stock, red #DC2626 due/failed/cancelled/out of stock.

TYPE: Inter only. Large titles 34px/700 tight tracking. Section headers 12px/600 uppercase with letter-spacing. Body 15px. All money uses tabular numbers in Indian format: ₹1,23,450.

SHAPE: radius 8px for buttons and inputs, 12px for cards, 16px for bottom-sheet tops. Only chips and badges are fully round. 1px borders instead of shadows. Phone touch targets at least 48px, primary buttons 56px tall.

ICONS: Material Symbols Outlined only, weight 400.

PHONE PATTERNS: bottom tab bar Home · Bills · raised yellow centre "New bill" (+) · Customers · More, solid white with 1px top border, no blur. Large titles with a sticky search under them. Bottom sheets with a grab handle instead of modals. Swipe actions on list rows.

PC PATTERNS: 240px ink #111111 left sidebar with white text and a yellow left bar on the active item; white 64px top bar with global search ("/" hint), yellow "New bill · F2" button and the user menu; tables with 1px borders; detail in a right-side panel.

WORDS: plain short English a counter boy understands: "New bill", "Collect payment", "Due", "Car in", "Save bill & send WhatsApp". Never "Submit", "Invoice", "Receivable", "Initiate".

DATA: Indian and realistic. Customers Rahul Pawar 98231 04512, Sneha Kulkarni 98231 07788, Amit Jadhav 98230 11245, Vikram Shinde, Priya Deshpande. Cars Hyundai Creta MH12 KT 4521, Toyota Innova Hycross MH12 VR 7703, Mahindra Scorpio-N MH14 JD 2290, Maruti Swift MH12 PQ 1188, Tata Nexon MH12 RD 8890. Products DHC Gleam 2.0 LED headlight ₹4,999, Autoform seat covers ₹8,500, 7D floor mats ₹3,200, Garware PPF full body ₹65,000, 3M PPF bonnet + bumper ₹18,000, Ceramic coating 9H ₹14,999, Android stereo 9" ₹11,500, Fitting charge ₹500. GST 18%. Bill numbers like JG-2526-0142. Staff Prakash Jadhav (Owner), Rohit Shinde (Counter), Sagar More (Sales), Sunil Gaikwad (Technician).

BANNED: gradients, gradient text, purple/blue/teal, glassmorphism or backdrop blur, glows, emoji, illustrations, blobs, "Welcome back" heroes, every card the same size, decorative sparklines or donut charts, pill-shaped buttons, thin grey text on grey, lorem ipsum, $ signs, WhatsApp-green filled buttons.
```

---

## Mobile (App mode, 390×844)

### M1. Home, Sales / Counter
```
Mobile app home screen for the Sales/Counter role of "Jay Ganesh" staff CRM, 390x844, native app feel. Tokens: ink #111111, white cards, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C only for the main button and centre tab, Inter, tabular ₹ amounts in Indian format, Material Symbols Outlined, radius 8/12, 1px borders, no shadows.

Top row: a 32px ink rounded square with the white shield + yellow car logo, "Jay Ganesh" 15/600, under it "Counter · Rohit Shinde" 13px #52525B; right a bell icon with a small red dot. Large title "Today" 34/700 and "Thu, 5 Nov" in grey on the same baseline.
Sticky search, 52px, white, 1px border: magnifier, placeholder "Mobile no., name or car number".

Actions in 2 columns, 12px gaps:
- Full-width first: "New bill" yellow #FFD51C, 72px tall, black receipt icon, "New bill" 18/700, sub "Customer → items → pay", chevron right.
- Then five white tiles 88px tall with a 1px border, outline icon top-left, label bottom-left 15/600: "Add customer", "New quotation", "Collect payment" (with "₹18,400 due" in red 12/600 under the label), "Car in (job card)", "Check stock / price". The 5th tile spans both columns as a shorter 56px row.

Section "MY TODAY" (12px uppercase grey): one white card with 3 stats split by 1px dividers: "My bills 9", "My sales ₹48,650", "Cash in hand ₹21,400". All big tabular 22/700.

Section "WAITING ON ME" with count "4": a white card list with dividers, rows 64px:
- "Draft bill · Vikram Shinde" sub "Scorpio-N · 2 items · ₹9,998" with amber badge "Draft", trailing "Open".
- "Quote sent 3 days ago · Priya Deshpande" sub "Garware PPF full body · ₹65,000" with a secondary button "Follow up" (outline, chat icon).
- "Quote sent 5 days ago · Ganesh Kale" sub "Ceramic coating 9H · ₹14,999" with "Follow up".
- "New lead · 97654 32109" sub "Asked for Creta seat covers · Instagram ad" trailing "Call".

Section "RECENT BILLS" with "See all": 5 rows 68px: initials square avatar, name, sub "JG-2526-0142 · 11:42 · Cash", trailing amount and a WhatsApp status line: Rahul Pawar ₹6,148 green double tick "Read"; Sneha Kulkarni ₹2,360 ink double tick "Delivered"; Amit Jadhav ₹14,999 red "Not sent · Resend"; Vikram Shinde ₹3,200 grey single tick "Sent"; Sneha Kulkarni ₹500 "Read". Show the first row half-swiped left revealing "WhatsApp" (ink) and "Print" (zinc-700) actions.

Bottom tab bar, solid white, 1px top border: Home (active: filled icon, ink, 3px yellow bar above), Bills, raised 56px yellow circle with black + and label "New bill", Customers, More. Home indicator.

Reject if: Send offer tile, a Workshop tab, blur on the tab bar, same-size grid with New bill not dominant, emoji, greeting hero.
```

### M2. Home, Owner
```
Mobile app home screen for the Owner role of "Jay Ganesh" staff CRM, 390x844, native app. Tokens: ink #111111, white cards, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C sparingly, green #15803D / amber #B45309 / red #DC2626 only as small tinted badges, Inter, tabular ₹ Indian format, Material Symbols Outlined, 1px borders, no shadows, no charts except plain bars.

Top row: ink square logo, "Jay Ganesh", sub "Owner · Prakash Jadhav", bell. Large title "Today" with segmented control right-aligned under it: Today | Week | Month (Today selected, white segment on #F4F4F5 track).
Sticky search "Mobile no., name or car number".

TODAY'S BUSINESS: a white card. Top: "Sales" label and a big "₹1,42,850" 40/700 tabular, under it in 13px "₹4,200 more than last Thursday" with a small up-arrow in green. Below a 2x2 grid split by 1px lines: "Bills 27 · 3 more than last Thu", "Avg bill ₹5,290", "New customers 8", "Cash / UPI / Card" shown as three numbers ₹38,400 / ₹96,250 / ₹8,200 with one thin 3-part bar in ink, zinc-500, zinc-300.

Action row: 4 square tiles in one row, 80px: "New bill" (yellow), "Collect payment", "Send offer", "Today's report".

NEEDS ATTENTION (red count "5"): white list, each row 56px with a left semantic icon and chevron:
- red: "Dues over 15 days · ₹46,300 from 7 customers"
- red: "2 WhatsApp bills not sent"
- amber: "Low stock · 6 items" (sub: DHC Gleam 2.0 LED 2 left, 7D mats Creta 1 left)
- red: "1 bill cancelled today · by Rohit · Wrong item"
- amber: "Cash close not done yesterday"

STAFF TODAY: rows with name, role, bills count, sales ₹ right-aligned, and a thin ink bar showing share of the top seller: Rohit Shinde 11 bills ₹58,400; Sagar More 9 bills ₹47,950; Prakash Jadhav 7 bills ₹36,500.

CARS IN WORKSHOP: one card with 4 equal counters split by dividers: Waiting 2, Working 3, QC 1, Ready 2 (Ready in green), chevron "Open job board".

THIS MONTH: compact card: Sales ₹18,64,200 · Collected ₹17,02,900 · Dues ₹1,61,300 (red). Top products list of 3 rows with qty and ₹. Funnel as three numbers with arrows: "Ad leads 214 → Walk-ins 61 → Bills 38".

Bottom tab bar: Home active (yellow 3px bar), Bills, raised yellow + "New bill", Customers, More. Solid white.

Reject if: line or donut charts, gradient hero, sparkline on every stat, emoji, blue.
```

### M3. New bill, step 1: Customer
```
Mobile full-screen flow step for "Jay Ganesh" CRM, 390x844, native app. Tokens: ink #111111, white, canvas #FAFAF9, borders #E4E4E7/#D4D4D8, yellow #FFD51C for the one primary button, red #DC2626 for dues, Inter, tabular ₹, Material Symbols Outlined, radius 8/12, no shadows.

Header: back chevron, title "New bill", right "Step 1 of 3" in grey; under it a 3-segment progress line (first segment ink, others #E4E4E7). No tab bar on this screen.
Large label "Customer mobile". A 56px input with fixed "+91" prefix inside, value "98231 04512" in 22px tabular, ink 2px focus ring, clear x.
Under it, a found-customer card (white, 1px border, radius 12): 48px initials square "RP", "Rahul Pawar" 17/600, "Loni Kalbhor · Customer since Mar 2024 · 6 bills", right a red tinted badge "Due ₹3,500". Line below: "Last visit 12 Oct · Ceramic coating 9H".
"Which car?" label, then horizontal car chips 44px: selected chip ink bg white text "Creta · MH12 KT 4521" with a check; unselected outline "Swift · MH12 PQ 1188"; dashed outline "+ Add car".
Below in grey 13px: "Recent for this car: 7D mats, DHC Gleam 2.0 LED" as a hint row.
Small ghost link at the bottom of content: "Skip, walk-in without number".
Show an iOS numeric keypad occupying the bottom, and above it a sticky bar with a full-width yellow 56px button "Next: Items" with arrow.

Also show (as a second state below or as a variant) the NEW number case: the input shows "97654 32109" and an inline mini form: Name (required), Car model (search field "Search model, e.g. Creta"), Plate (optional, placeholder "MH12 AB 1234"), and "How did they hear about us?" single-select chips: Walk-in, Instagram, WhatsApp ad, Google, Friend.

Reject if: modal pop-up, long form, tab bar visible, more than one yellow element, emoji.
```

### M4. New bill, step 2: Items
```
Mobile full-screen flow step for "Jay Ganesh" CRM, 390x844. Tokens: ink #111111, white, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C only for the Next button, amber #B45309 for "Not in stock", Inter, tabular ₹ Indian format, Material Symbols Outlined.

Header: back, "New bill", "Step 2 of 3", progress line 2/3 ink. Under the header a slim customer strip: "Rahul Pawar · Creta MH12 KT 4521" with "Change".
Search input 52px "Search product or service".
"POPULAR" label: wrapping product chips 40px with name and price in grey: "Gleam 2.0 LED · ₹4,999", "7D mats · ₹3,200", "Ceramic 9H · ₹14,999", "Seat covers · ₹8,500", "Android stereo · ₹11,500", "Fitting · ₹500".
"CRETA OWNERS USUALLY BUY" label: 3 chips: "Autoform seat covers Creta", "3M PPF bonnet + bumper", "Mud flaps".

"IN THIS BILL · 3" label, then a white card list with item rows 76px:
- "DHC Gleam 2.0 LED headlight" sub "DHC · ₹4,999 each" right: qty stepper (− 2 +) 96x40 and line total "₹9,998".
- "7D floor mats (Creta)" sub "₹3,200" with an amber tinted badge "Not in stock", stepper 1, "₹3,200".
- "Fitting charge" sub "Service · ₹500", stepper 1, "₹500".
Show the second row partially swiped left revealing a dark "Remove" action. Under the list: ghost links "+ Discount" and "More details".
A dark snackbar above the bottom bar: "Removed Mud flaps" with "Undo" in yellow.

Sticky bottom bar, white with 1px top border: left "4 items" and "Total incl. GST" with big "₹13,698" 24/700 tabular; right a yellow 56px button "Next: Payment".

Reject if: total not visible, cards with shadows, pill buttons, same-size product tiles with images, emoji.
```

### M5. New bill, step 3: Payment
```
Mobile full-screen flow step for "Jay Ganesh" CRM, 390x844. Tokens: ink #111111, white, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C only for the save button, green #15803D small badges, Inter, tabular ₹ Indian format, Material Symbols Outlined, radius 8/12.

Header: back, "New bill", "Step 3 of 3", progress full.
Total block centred-left: label "TO PAY" and "₹13,698" 40/700 tabular, sub "4 items · GST ₹2,090 included · Rahul Pawar".
"How is the customer paying?" then a segmented control 48px with 5 options: Cash | UPI | Card | Split | Later. "Split" selected.
Split state: rows with mode icon, mode name, and a right-aligned ₹ amount input: "Cash ₹5,000", "UPI ₹8,698". Below a line: "Remaining ₹0" in green with a check. Under UPI row, a white card with a UPI QR code (black on white, 160px) labelled "Scan to pay ₹8,698 · jayganesh@upi" and a grey "Waiting for payment…" line, plus a secondary button "Mark UPI received".
"Later (due)" note in grey under the segment: "Needs admin OK above ₹2,000".
A row with a toggle ON (ink track): "Send bill on WhatsApp" sub "+91 98231 04512".
Sticky bottom: full-width yellow 56px button "Save bill & send WhatsApp" with a small "Ctrl+Enter" hint hidden on phone. Above the button in small grey: "Bill no. will be JG-2526-0143".

Reject if: payment logos/brand colours, gradients, a modal, more than one yellow element.
```

### M6. Bill done
```
Mobile success screen for "Jay Ganesh" CRM, 390x844. Tokens: ink #111111, white, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C only for "New bill", green #15803D, red #DC2626, Inter, tabular ₹, Material Symbols Outlined. No confetti, no illustration.

Top area: a 56px green circle outline with a check icon, "Bill saved" 28/700, "JG-2526-0143 · 5 Nov, 12:07" in grey, big "₹13,698" 40/700 tabular, sub "Rahul Pawar · Creta MH12 KT 4521 · Cash ₹5,000 + UPI ₹8,698".
A white card "WhatsApp e-bill" with a 3-step live status line: "Sending" (done) → "Sent ✓" (done) → "Delivered ✓✓" (current, ink) → "Read" (grey, pending); text "To +91 98231 04512 · PDF attached".
Also show the failed variant as a red-tinted row: "Not sent: number not on WhatsApp" with a secondary button "Send manually".
Buttons: a 2-column row of secondary 52px buttons "Print" (print icon, sub "Thermal 80mm") and "Share" (share icon, "PDF"); then full-width yellow 56px "New bill"; then a ghost "Back to home".
Small summary list at the bottom: items with qty and line totals, GST line, total.

Reject if: confetti, emoji, illustration, green filled giant banner, blue.
```

### M7. Customer profile
```
Mobile customer profile for "Jay Ganesh" CRM, 390x844, native app. Tokens: ink #111111, white, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C only for the main action, red #DC2626 dues, green #15803D paid, Inter, tabular ₹ Indian format, Material Symbols Outlined, 1px borders.

Header: back chevron, "Customers" small, right ⋯. Large title "Sneha Kulkarni" 30/700, sub "+91 98231 07788 · Hadapsar · since Jan 2025", tags as small outline chips "Regular", "Instagram". WhatsApp opt-in shown as a small green "WhatsApp OK" badge.
Action row of 3 equal 52px secondary buttons with icon above label: Call, WhatsApp, New bill (this one yellow).
Due banner card (red tint, red text): "Due ₹3,500 · 2 bills · oldest 21 days" with a black button "Collect ₹3,500".
Stat row split by dividers: "Total spent ₹62,480", "Bills 9", "Last visit 28 Oct".
"CARS" section: car rows with a car outline icon: "Tata Nexon · MH12 RD 8890 · 2023" sub "Last: 7D mats + DHC LED · 28 Oct"; "Maruti Swift · MH12 PQ 1188 · 2019" sub "Last: Android stereo · Jun". "+ Add car" ghost.
Segmented control: History | Quotes | Jobs, History selected.
History list rows 64px: "JG-2526-0139 · 28 Oct" sub "7D mats, DHC Gleam 2.0 LED" right "₹8,199" with red "Due ₹2,000"; "JG-2526-0102 · 2 Oct" "Ceramic coating 9H" "₹14,999" green "Paid"; "JG-2526-0061 · 14 Sep" "Garware PPF bonnet" "₹18,000" amber "Part paid"; "JG-2526-0012 · 9 Jun" "Android stereo 9"" "₹11,500" "Paid".
Notes card: "Prefers evening pickup. Ask about PPF for Nexon." with "Edit".
Bottom tab bar with Customers active.

Reject if: avatar photo, gradient header, cover image, emoji, blue links.
```

### M8. Check stock / price
```
Mobile stock and price search for "Jay Ganesh" CRM, 390x844, native app. Tokens: ink #111111, white, canvas #FAFAF9, borders #E4E4E7, stock status green #15803D / amber #B45309 / red #DC2626 as tinted badges, Inter, tabular ₹, Material Symbols Outlined.

Large title "Stock & prices". Sticky search with typed text "glea" and clear x, keyboard hidden. Filter chips row: All, LED & lights, Seat covers, Mats, PPF, Coating, Audio.
Result rows 76px, white list with dividers:
- "DHC Gleam 2.0 LED headlight" sub "DHC · H4 · Shelf A3" right big "₹4,999" 17/700 and amber badge "2 left".
- "DHC Gleam 2.0 LED fog lamp" sub "DHC · H11 · Shelf A3" "₹2,499" green "14 in stock".
- "Gleam LED interior kit" sub "Generic · Shelf B1" "₹899" red "Out of stock".
- "DHC Gleam 3.0 LED headlight" sub "DHC · H7" "₹6,499" green "8 in stock".
Sales role sees price but no cost; show MRP struck through small grey beside price only where it differs (₹5,999).
Show a bottom sheet open over the list (16px top radius, grab handle, dim backdrop) for "DHC Gleam 2.0 LED headlight": price ₹4,999, MRP ₹5,999, "2 in stock · Shelf A3", "Warranty 1 year", "Fits: Creta, Nexon, Swift, Scorpio-N (H4)" as small chips, buttons: yellow full-width "Add to new bill" and secondary "Send price on WhatsApp".
Tab bar under the sheet: More active.

Reject if: product photos as big cards, grid of same-size tiles, emoji, blue.
```

### M9. More menu
```
Mobile "More" menu for "Jay Ganesh" CRM, 390x844, native iOS settings-like list. Tokens: ink #111111, white grouped lists on canvas #FAFAF9, 1px #E4E4E7 dividers, Inter, Material Symbols Outlined 24px in ink, chevrons in #71717A. No yellow except the active tab indicator and centre tab button.

Large title "More". Profile row card: 48px initials "RS", "Rohit Shinde", "Counter · 98231 05566", chevron "My profile".
Grouped sections with 12px uppercase grey headers and 56px rows (icon, label, optional right value):
WORK: Quotations (right "3 open"), Job cards (right "8 in"), Collect payment (right red "₹18,400"), Leads (right "4 new").
STOCK: Stock & prices, Purchases (stock in).
MONEY: Cash close (amber badge "Yesterday pending"), Expenses, Reports.
SHOP: Offers on WhatsApp, Staff & roles, Settings, Activity log.
Then a row "Add to home screen" with a small hint "Opens full-screen like an app".
Then a red text row "Sign out". Footer grey 12px: "Jay Ganesh Car Accessories · Kawadipat Toll Naka · v1.0".
Note: show the Counter role, so hide Purchases, Expenses, Staff & roles, Settings and Activity log in this version (only show items this role can use; no greyed-out rows).
Bottom tab bar, More active.

Reject if: grid of coloured icon tiles, coloured icon backgrounds, emoji.
```

---

## Desktop / counter PC (Web mode, 1440×900)

### D1. Owner dashboard
```
Desktop web app dashboard (1440x900) for the Owner of "Jay Ganesh" car accessories CRM. Tokens: ink #111111, white surfaces, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C only for the New bill button and active sidebar bar, semantic green #15803D / amber #B45309 / red #DC2626 as small tinted badges, Inter, tabular ₹ Indian format, Material Symbols Outlined 20px, radius 8/12, 1px borders, no shadows.

Left sidebar 240px, ink #111111: white shield logo + "JAYGANESH" wordmark at top, then links with outline icons, 14px zinc-300 text: Home (active: white text, #1C1C1C bg, 3px yellow left bar), New bill F2, Bills, Quotations, Customers, Collect payment, Job cards, Stock & prices, Purchases, Finance, Leads, Offers, Reports, Staff & roles, Settings, Activity log. Bottom: "Prakash Jadhav · Owner".
Top bar 64px white with 1px bottom border: global search 480px "Search mobile, name, MH12…, bill no." with a "/" key hint; yellow button "New bill · F2"; bell; user initials.

Content: title "Today" 28/700 with "Thursday, 5 Nov 2026" and a segmented control Today | Week | Month on the right.
Row 1, business strip, one white card split into 5 columns by 1px dividers: Sales ₹1,42,850 (sub "₹4,200 more than last Thursday" with green up arrow), Bills 27 ("3 more"), Avg bill ₹5,290, New customers 8, Cash / UPI / Card ₹38,400 / ₹96,250 / ₹8,200 with one thin 3-part bar.
Row 2 left (8 cols): "Needs attention" table-like list with semantic left icons: Dues over 15 days ₹46,300 · 7 customers (View); 2 WhatsApp bills not sent (Resend all); Low stock 6 items; 1 bill cancelled today by Rohit Shinde, reason "Wrong item" (JG-2526-0138); Cash close not done yesterday (Do it now).
Row 2 right (4 cols): "Staff today" list: name, bills, sales, thin ink share bar: Rohit Shinde 11 · ₹58,400; Sagar More 9 · ₹47,950; Prakash Jadhav 7 · ₹36,500.
Row 3 left: "Recent bills" table: Bill no., Time, Customer, Car, Mode, Amount (right), WhatsApp (green "Read", ink "Delivered", red "Not sent"), Staff. 6 rows using Rahul Pawar, Sneha Kulkarni, Amit Jadhav, Vikram Shinde, Priya Deshpande, Ganesh Kale with Creta, Nexon, Scorpio-N, Hycross, Swift and amounts ₹6,148, ₹2,360, ₹14,999, ₹65,000, ₹3,200, ₹11,500.
Row 3 right: "Cars in workshop" 4 counters Waiting 2 · Working 3 · QC 1 · Ready 2 with "Open job board"; below "This month": Sales ₹18,64,200, Collected ₹17,02,900, Dues ₹1,61,300 (red), top 5 products with qty, and "Ad leads 214 → Walk-ins 61 → Bills 38".

Reject if: line/area/donut charts, gradient cards, glass, same-size KPI cards with icons in coloured circles, emoji, blue.
```

### D2. POS billing (one page, keyboard first)
```
Desktop counter POS billing screen (1440x900) for "Jay Ganesh" CRM, keyboard-first, like Square POS. Tokens: ink #111111, white, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C only for the Save button, amber #B45309 "Not in stock", red #DC2626 due, Inter, tabular ₹ Indian format, Material Symbols Outlined, radius 8, 1px borders. Small grey keycap hints (1px border, 11px mono-like Inter) next to actions.

Collapsed ink sidebar 64px with icons only (New bill active with yellow bar). Top bar: "New bill" title, draft note "Draft saved 12:06", right "Esc Back".

Left half (product side, canvas bg):
- Search input 48px focused with ink ring, text "/" keycap, placeholder "Search product, SKU or service". Results dropdown open for "gleam": 3 rows with name, brand, stock badge, price; the first row highlighted with hint "↑↓ Enter to add".
- "Popular" grid of 12 product tiles in 4 columns, 72px tall, white, 1px border, name 14/600 and price 13 grey, small stock count right: Gleam 2.0 LED ₹4,999; 7D mats ₹3,200; Seat covers ₹8,500; Ceramic 9H ₹14,999; Garware PPF full ₹65,000; 3M PPF bonnet ₹18,000; Android stereo ₹11,500; Fitting ₹500; Mud flaps ₹650; Steering cover ₹799; Dashcam ₹4,500; Perfume ₹349. Tiles are text-only, no images.
- Under it "Creta owners usually buy" row of 3 chips.

Right half (live bill, white panel, 1px left border):
- Customer block: mobile field "+91 98231 04512" with "F3" keycap, result "Rahul Pawar · Loni Kalbhor" and red badge "Due ₹3,500", car chips "Creta · MH12 KT 4521" (selected ink) and "Swift · MH12 PQ 1188".
- Items table: Item, Qty (stepper with +/− keycaps), Price, Total. Rows: DHC Gleam 2.0 LED headlight 2 × ₹4,999 = ₹9,998 (highlighted row, hint "+ / − qty"); 7D floor mats (Creta) 1 × ₹3,200 with amber "Not in stock"; Fitting charge 1 × ₹500. Link "+ Discount (D)".
- Totals: Subtotal ₹11,608, GST 18% ₹2,090, big Total ₹13,698 40/700.
- Payment: segmented Cash | UPI | Card | Split | Later with keycaps 1–5, Split selected: Cash ₹5,000, UPI ₹8,698, "Remaining ₹0" green, small UPI QR 120px with "Show to customer (Q)".
- Toggle on "Send bill on WhatsApp".
- Full-width yellow 52px button "Save bill & send WhatsApp" with "Ctrl+Enter" keycap.
Footer strip of shortcuts in grey: F2 New · / Search · F3 Customer · + − Qty · Del Remove · Ctrl+Enter Save · Esc Back.

Reject if: product photos, gradient, cards with shadows, more than one yellow, wizard steps on PC.
```

### D3. Customers list
```
Desktop customers list (1440x900) for "Jay Ganesh" CRM. Tokens: ink #111111, white, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C only for "+ Add customer" and the active sidebar bar, red #DC2626 dues, green #15803D, Inter, tabular ₹ Indian format, Material Symbols Outlined, 1px borders.

Ink sidebar 240px with Customers active (yellow left bar). Top bar with global search and "New bill · F2" outline (secondary here because the page action is Add customer).
Page header: "Customers" 28/700, "1,284 customers · 312 with WhatsApp offers on", right yellow button "+ Add customer" and secondary "Export CSV".
Filter bar: search "Mobile, name or car number", then filter dropdown buttons with 1px borders: Car model (Creta selected → shows "Car: Creta ×" chip), Last visit (Any), Has due (toggle), Source (Instagram, WhatsApp ad, Google, Walk-in, Friend), Tags. Right: "Sort: Last visit".
Table, sticky header in #F4F4F5, 44px rows, 12 rows: columns Name (initials square + name + mobile under it), Cars (e.g. "Creta MH12 KT 4521 +1"), Last visit, Bills, Total spent (right), Due (right, red when > 0), WhatsApp (green "Opted in" / grey "Off"), Source. Realistic Pune names: Rahul Pawar, Sneha Kulkarni, Amit Jadhav, Vikram Shinde, Priya Deshpande, Ganesh Kale, Swapnil Bhosale, Kiran Jagtap, Neha Patil, Omkar Gaikwad, Sachin Chavan, Rupali Kadam; cars Creta, Hycross, Scorpio-N, Swift, Nexon, Brezza, XUV700; amounts like ₹62,480, ₹1,18,900, ₹3,200.
One row selected opens a 480px right panel: Sneha Kulkarni profile summary (Call, WhatsApp, New bill buttons; due ₹3,500 with "Collect"; cars; last 4 bills).
Pagination "1–50 of 1,284".
Empty-state note not needed here.

Reject if: avatar photos, coloured avatar circles, card grid instead of table, emoji, blue links.
```

### D4. Job board
```
Desktop workshop job board (1440x900) for "Jay Ganesh" CRM. Tokens: ink #111111, white cards, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C only for "+ Car in" and the active sidebar bar, amber #B45309 working/late, green #15803D ready, Inter, tabular numbers, Material Symbols Outlined, radius 12 cards, 1px borders, no shadows except a lifted card being dragged.

Ink sidebar with Job cards active. Top bar with search.
Header: "Job board" 28/700, "Thursday, 5 Nov · 10 cars in", filter chips: All technicians, Sunil Gaikwad, Akash Pawar; yellow button "+ Car in".
Five columns with header label + count: Waiting (2), Working (3), QC (1), Ready (2), Delivered today (4, collapsed list).
Job cards (white, 1px border, 12px radius): small 64x48 before-photo thumbnail (neutral grey placeholder with camera icon, not stock imagery), plate "MH12 KT 4521" 15/700, "Hyundai Creta · Rahul Pawar", work list as 2-3 lines ("Ceramic coating 9H", "Interior detailing"), technician initials chip "SG", promised time "Ready by 5:30 pm" (amber "Late 20 min" on one card), and a checklist progress "2/3 done".
Examples: Waiting: Innova Hycross MH12 VR 7703 "Garware PPF full body", Swift MH12 PQ 1188 "Android stereo 9" + fitting". Working: Creta (above), Scorpio-N MH14 JD 2290 "3M PPF bonnet + bumper" (Late), Brezza MH12 SN 6620 "7D mats + seat covers". QC: Nexon MH12 RD 8890 "DHC Gleam 2.0 LED". Ready: XUV700 MH12 WX 3345 "Ceramic 9H" with button "Send ready on WhatsApp", City MH12 HJ 9012 "Dashcam" with "Make bill".
Show one card mid-drag between Working and QC with a slight lift shadow and a dashed drop slot.

Reject if: colourful column headers, emoji, avatars with photos, gradient, Trello-like coloured labels.
```

---

## Extra screens (after the priority list)

### X1. Sign in (phone, App mode)
```
Mobile sign-in screen for "Jay Ganesh" staff CRM, 390x844, dark. Background ink #111111, white text, Inter, yellow #FFD51C only for the button. Centered upper third: the white shield logo with a yellow car and the white "JAYGANESH" wordmark, under it "Staff sign in" 15px zinc-400. Form on dark: "Mobile number" input 56px with +91 prefix, #1C1C1C fill, 1px #3F3F46 border, white text; "Password" with show/hide eye. Yellow 56px button "Sign in". Ghost text "Forgot password? Ask the owner or admin to reset it." in zinc-400. Bottom: "Jay Ganesh Car Accessories · Kawadipat Toll Naka, Loni Kalbhor". Also show an amber notice strip at top: "You were signed out after being idle. Drafts are kept." No illustration, no gradient, no glow behind the logo.
```

### X2. Technician home (phone, App mode)
```
Mobile home for the Technician role of "Jay Ganesh" CRM, 390x844. Ink #111111, white cards, canvas #FAFAF9, borders #E4E4E7, yellow #FFD51C for the one main button per card, amber working, green ready, Inter, Material Symbols Outlined. NO money anywhere. Large title "My jobs", sub "Sunil Gaikwad · 3 cars today". Big job cards (full width): 4:3 before-photo area (neutral grey placeholder with camera icon), plate "MH12 KT 4521" 22/700, "Hyundai Creta · Ready by 5:30 pm", status badge Working (amber), work list as 48px checkbox rows ("Ceramic coating 9H" checked, "Interior detailing" unchecked, "Glass coating" unchecked), buttons: secondary "Add photo" (camera), yellow "Mark ready for QC". Second card Waiting with yellow "Start". Tab bar: Jobs (active), Photos, More. No emoji.
```

### X3. Collect payment (phone, App mode)
```
Mobile Collect payment flow for "Jay Ganesh" CRM, 390x844. Ink #111111, white, canvas #FAFAF9, borders #E4E4E7, yellow only for the save button, red #DC2626 dues, Inter, tabular ₹. Header back + "Collect payment". Customer strip: "Sneha Kulkarni · +91 98231 07788" with "Change". "TOTAL DUE ₹3,500" in red 28/700. Unpaid bills oldest first, each with a checkbox (all ticked): "JG-2526-0061 · 14 Sep · 52 days · ₹1,500", "JG-2526-0139 · 28 Oct · 8 days · ₹2,000". "Amount received" ₹ input pre-filled 3,500, numeric. Segmented Cash | UPI | Card (UPI selected) with a small QR. Line: "Applied to oldest bills first. Balance after: ₹0". Sticky yellow button "Collect ₹3,500 & send receipt". No modal, no emoji.
```

### X4. Staff & roles (PC, Web mode)
```
Desktop Staff & roles page (1440x900) for "Jay Ganesh" CRM. Ink sidebar with Staff & roles active, white top bar. Header "Staff & roles", yellow "+ Add staff". Segmented: Staff | Roles. Staff table: Name (initials square), Mobile (login), Role badge (Owner ink, Admin, Finance, Sales, Technician as outline badges), Last active, Today's bills, Status (green "Active" / grey "Switched off"), actions "Reset password", "Switch off". Rows: Prakash Jadhav Owner; Rohit Shinde Counter/Sales; Sagar More Sales; Pooja Deshmukh Finance; Sunil Gaikwad Technician; Akash Pawar Technician (Switched off). Right panel "Role: Sales" permission matrix with ink checkboxes grouped Customers, Bills, Quotations, Payments, Stock (view price, not cost), Job cards (create), with "Discount limit ₹500 without admin". Tokens: ink #111111, white, borders #E4E4E7, Inter, tabular, Material Symbols Outlined, no shadows, no emoji, no blue.
```
