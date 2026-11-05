# WhatsApp templates and Cloud API go-live

What to submit in **WhatsApp Manager → Message templates**, and the owner's checklist for switching the CRM from manual mode to the Cloud API. Written 7 Nov 2026 for Graph API **v23.0**.

The CRM keeps the same texts in `crm/app/config/whatsapp.php` (`texts`), which manual mode uses to pre-type the message. If you change a template's wording in WhatsApp Manager, change it there too. Template **names** can be changed in Settings → WhatsApp without touching code.

---

## 1. Rules that decide approval and price

**Categories (Meta decides, not us):**
- **Utility:** about a specific transaction or request the customer started: a bill, a payment receipt, a quote they asked for, "your car is ready". No offers, no "visit us again", no discount, no review ask. It costs less (about ₹0.12–0.15 per message in India) and is free inside the 24-hour window after the customer messages us.
- **Marketing:** anything promotional, or not tied to one transaction: offers, reminders to come back, follow-ups on a quote they did not act on, review requests. About ₹0.80–0.90 per message. Customers can block or report these, and Meta may hold some back (error 131049).
- **Meta re-checks categories.** If a "utility" template contains promotional words, Meta approves it as marketing or changes the category later (they email the business). So keep utility templates plain and factual. Check the current rate card in WhatsApp Manager before relying on the prices above.

**Approval tips:**
- Language: **English** (`en`). The CRM sends with the language in Settings → WhatsApp → "Template language code"; it must match what you submit (`en`, not `en_US`).
- Give a realistic **sample value** for every `{{n}}`. Meta reviewers reject empty or vague samples.
- The body must **not start or end with a variable**, and two variables must not sit next to each other with no words between.
- Don't put too many variables in a short message (Meta rejects "too many variables for its length").
- No URL shorteners, no all-caps, no "Dear customer" spam style. Use the shop name in the text.
- For a **document header**, upload a sample PDF when submitting (any one-page bill PDF).
- Marketing templates: add Meta's **"Stop promotions"** quick-reply button. The CRM also understands a typed **STOP / UNSUBSCRIBE** (opt-out) and **START** (opt back in), and never replies to them.
- Approval usually takes minutes to a few hours. A rejection email gives the reason; fix and resubmit under a new name if needed (`invoice_ebill_v2`) and update the name in Settings.

---

## 2. The templates

### 2.1 `invoice_ebill` (Utility, document header) — needed first
Sent automatically after every bill (or by "Send on WhatsApp" in manual mode).

- **Header:** Document (PDF). Sample: any bill PDF.
- **Body:**
  ```
  Hi {{1}}, thank you for visiting Jay Ganesh Car Accessories!

  Your invoice {{2}} for {{3}} is attached. Payment status: {{4}}.

  For any help with your purchase, just reply to this message.
  ```
- **Samples:** {{1}} `Rahul Kale` · {{2}} `JG-2526-0142` · {{3}} `₹12,500` · {{4}} `Paid`
- **Footer (optional):** `Jay Ganesh Car Accessories, Loni Kalbhor, Pune`
- **Why utility:** it confirms a purchase the customer just made and carries their bill. Keep it free of offers.
- {{4}} is filled by the CRM as `Paid`, `Balance due ₹2,000`, `Part paid` or `Payment due`.

### 2.2 `payment_receipt` (Utility)
Sent when dues are collected.
- **Body:**
  ```
  Hi {{1}}, we have received your payment of {{2}} against invoice {{3}}. Balance now: {{4}}.

  Thank you, Jay Ganesh Car Accessories.
  ```
- **Samples:** `Rahul Kale` · `₹2,000` · `JG-2526-0142` · `₹0`
- **Why utility:** confirms a payment transaction.

### 2.3 `quotation_send` (Utility if requested; see note) with a document header
Sent when staff tap "Save & send on WhatsApp" on a quotation.
- **Header:** Document (PDF), sample: a quotation PDF.
- **Body:**
  ```
  Hi {{1}}, here is your quotation {{2}} from Jay Ganesh Car Accessories for {{3}}. Total: {{4}}, valid till {{5}}.

  Reply here if you have any questions.
  ```
- **Samples:** `Rahul Kale` · `JGQ-2526-0031` · `3M PPF for Hyundai Creta` · `₹68,000` · `14 Nov 2026`
- **Category note:** a quote the customer **asked for** is a reply to their request, so submit it as **Utility**. Meta may still mark it Marketing (quotes look "sales-like"); if they do, accept it: the CRM then treats it as marketing and skips opted-out customers. Never add "special price today" or similar to this template, or it will surely become marketing.

### 2.4 `followup_reminder` (Marketing)
Optional nudge on a quote that wasn't converted (off by default, UX-FLOWS §3.2).
- **Body:**
  ```
  Hi {{1}}, this is Jay Ganesh Car Accessories. Your quotation for {{2}} is valid till {{3}}. Would you like to book a time to visit?
  ```
- **Samples:** `Rahul Kale` · `3M PPF for Hyundai Creta` · `14 Nov 2026`
- **Button:** Quick reply "Stop promotions" (Meta's opt-out button).
- **Why marketing:** it asks the customer to come back and buy; it isn't confirming anything they did.

### 2.5 `vehicle_ready` (Utility)
Sent when a job card is marked ready.
- **Body:**
  ```
  Hi {{1}}, your {{2}} is ready for pickup at Jay Ganesh Car Accessories. Job: {{3}}.

  We are open till {{4}} today.
  ```
- **Samples:** `Rahul Kale` · `Hyundai Creta (MH12AB1234)` · `Ceramic coating` · `9:00 PM`
- **Why utility:** a status update on work the customer ordered.

### 2.6 `review_request` (Marketing)
Sent a day after a bill (Phase 3).
- **Body:**
  ```
  Hi {{1}}, thank you for choosing Jay Ganesh Car Accessories for your {{2}}. If you liked our work, please leave us a Google review here: {{3}}

  Thank you!
  ```
- **Samples:** `Rahul Kale` · `Hyundai Creta` · `https://g.page/r/XXXXXXXX/review` (use the real Maps review link)
- **Button:** Quick reply "Stop promotions".
- **Why marketing:** Meta counts review and feedback asks as marketing. Submitting it as utility gets it rejected or re-categorised.

### 2.7 `hello_world` (Meta's sample, already approved)
Every new WhatsApp Business Account has it, in `en_US`. Use it with the CRM's "Send a test template" form (WhatsApp log page, Owner only) to prove the setup works before your own templates are approved.

---

## 3. Owner's go-live checklist (manual → Cloud API)

Until every box is ticked, keep **Settings → WhatsApp → Sending mode = Manual**. Billing works fully in manual mode.

1. **Number decided** (CRM-PLAN §7): coexistence on +91 98231 07676, or a new SIM only for the API.
2. **Meta app:** in developers.facebook.com, a Business-type app owned by business 1728410417676996, with the **WhatsApp** product added. Switch the app to **Live** mode (webhooks for real numbers need it).
3. **Phone number:** WhatsApp Manager → Phone numbers → Add (or Embedded Signup for coexistence). Verify by OTP, set the 6-digit two-step PIN, and wait until the status shows **Connected**. Copy:
   - **Phone number ID** → `WHATSAPP_PHONE_NUMBER_ID`
   - **WhatsApp Business Account ID** → `WHATSAPP_WABA_ID`
4. **Permanent token:** Business Settings → Users → **System users** → add an Admin system user → Assign assets: the app (full control) and the WABA (full control) → **Generate token**, expiry **Never**, permissions **`whatsapp_business_messaging`** and **`whatsapp_business_management`** → `WHATSAPP_TOKEN`. (The 24-hour token from the app dashboard is for testing only.)
5. **App secret:** App → Settings → Basic → App secret → `WHATSAPP_APP_SECRET`.
6. **Payment method:** WhatsApp Manager → Overview → Add payment method (card), currency INR. Without it, only the free service window works (error 131042).
7. **Webhook:** App → WhatsApp → Configuration → Webhook → Edit:
   - Callback URL: `https://crm.jayganeshcaraccessories.com/webhooks/whatsapp`
   - Verify token: any long random string, also saved as `WHATSAPP_VERIFY_TOKEN`
   - (The `.env` values must be on the server **before** you press Verify.)
   - Webhook fields: subscribe to **`messages`** (this one field carries both delivery statuses and customer replies, including STOP/START).
   - Subscribe the app to the WABA once (from a terminal, with the token):
     `curl -X POST "https://graph.facebook.com/v23.0/<WABA_ID>/subscribed_apps" -H "Authorization: Bearer <TOKEN>"`
8. **Templates:** submit §2.1 first (then 2.2, 2.5; the marketing ones when needed). Wait for **Active – Quality pending/High**.
9. **Server `.env`:** fill the six `WHATSAPP_*` values (see `.env.example`), then `php artisan config:cache`. Never paste the token into chat, email or Git.
10. **CRM:** Settings → WhatsApp → Sending mode **Automatic**, template language `en`, template names as approved.
11. **Check:** WhatsApp log page → setup card shows every value **Set**. Send `hello_world` (language `en_US`) to your own phone. Within a minute, "Last webhook from Meta" shows a time and the row gets ✓✓.
12. **Real test:** make a ₹1 test bill to your own number: the PDF arrives within seconds and the ticks update. Reply **STOP**, then check the customer shows opted out; reply **START** to undo. Cancel the test bill.
13. **Cron** (already needed for the CRM): `* * * * * cd ~/crm-app && php artisan schedule:run`, which runs the queue worker that retries failed sends at 1, 5 and 15 minutes.

**If something fails later,** the WhatsApp log shows the reason in plain English: token expired (190: make a new system-user token), template missing (132001: check the name/language in Settings), number not on WhatsApp (131026: send manually or call).
