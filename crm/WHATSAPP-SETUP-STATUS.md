# WhatsApp Cloud API: setup status

Last checked: **5 Nov 2026**. Prepared for the owner of Jay Ganesh Car Accessories.

Template wording used by the CRM code lives in `WHATSAPP-TEMPLATES.md`, which is kept separately. This file covers the account, the number and the go-live steps.

---

## 1. What exists right now

| Item | Status |
|---|---|
| Business portfolio "Jay Ganesh Car Accessories" (1728410417676996) | **Verified** |
| Meta app "Automation" (2901906313477061) | Exists. Its system-user token never expires and has `whatsapp_business_management`, `whatsapp_business_messaging`, `whatsapp_business_manage_events` and `business_management`, plus Pages, Instagram and Ads scopes. |
| Tech Provider / Solution Partner status | **No.** Some API fields return the error "requires that the Business that owns this App is a Business Solution Provider". |
| Main WhatsApp account "Jai Ganesh Car Accessories" (1834738223843088) | Approved, active, currency INR, owned by you. **No phone number is attached.** No webhook app is subscribed. |
| Payment on the main account | **Blocked.** Meta reports error 141006: "There is an error with the payment method. This will block business initiated conversations." A working card is needed before any bill can be sent. |
| WhatsApp account "Jay Ganesh Sound 76" (1269873871434500) | Approved and empty. It has the same payment-method error. |
| WhatsApp account "Jayganesh sound 76" (1241803477398150) | Approved and client-owned. It is **connected to AiSensy** (an Indian WhatsApp partner) and has one approved marketing template (`mk_acoustic_image_teaser`). It has no phone number now. It can send, probably on AiSensy's credit line. |
| Number +91 98231 07676 | Runs on the WhatsApp **Business app** only, where the ads send chats. It is not on the API. |

Nothing was changed except the template submissions in §2. No number was added, no setting changed, and nothing was deleted or sent.

## 2. Templates submitted for review (5 Nov 2026, on 1834738223843088)

| Name | Category | Header | Template id | Status |
|---|---|---|---|---|
| `invoice_ebill` | Utility | PDF document | 2660201334476906 | PENDING |
| `payment_receipt` | Utility | none | 1753269575747905 | PENDING |
| `quotation_send` | Utility | PDF document | 4758279151076465 | PENDING |
| `vehicle_ready` | Utility | none | 1167924192354924 | PENDING |
| `followup_reminder` | Marketing | none, with a "Stop promotions" button | 3607578666067269 | PENDING |

- All five are in English (`en`) and carry the footer "Jay Ganesh Car Accessories, Loni Kalbhor, Pune".
- Meta usually reviews them within minutes to 24 hours. Check the result in WhatsApp Manager → Message templates.
- **Variables:**
  - `invoice_ebill`: {{1}} name, {{2}} bill no, {{3}} amount, {{4}} payment status.
  - `payment_receipt`: {{1}} name, {{2}} amount received, {{3}} bill no, {{4}} balance.
  - `quotation_send`: {{1}} name, {{2}} quote no, {{3}} vehicle, {{4}} amount, {{5}} valid-until date.
  - `vehicle_ready`: {{1}} name, {{2}} car model, {{3}} plate.
  - `followup_reminder`: {{1}} name, {{2}} product.
- **Category risk on `quotation_send`:** Meta treats a quote as utility only when the customer asked for it and the message has no selling language. We worded it that way ("as requested…"). Meta can still re-label it **Marketing**, which would cost about ₹0.86 instead of ₹0.115 per send. If that happens, the CRM still works; only the cost changes.
- Templates stay on the WhatsApp account, so they will work with whichever number is attached later.

## 3. Coexistence: what Meta's docs say (checked 5 Nov 2026)

"Coexistence" means one number on both the WhatsApp Business app and the Cloud API.

- **India:** supported. The current Meta guide lists no excluded countries, and coexistence went global in 2025. Indian partners such as AiSensy offer it.
- **Who can do it:** only through **Embedded Signup run by a Tech Provider or Solution Partner**. Meta's requirements say: "You must already be a Solution Partner or Tech Provider." Our app is neither today, so we **cannot** connect 98231 07676 from our own app yet. The `featureType: "whatsapp_business_app_onboarding"` setting exists, but only partners can use it. (Embedded Signup v5 turns it on by default.)
- **Becoming a Tech Provider ourselves:**
  - Meta charges **no fee**.
  - The steps are business verification (✅ done), then App Review.
  - App Review needs app icon, privacy policy and category. It also needs two screen videos: sending a message, and creating a template (WhatsApp Manager screen recordings are accepted).
  - The aim is **Advanced access** to `whatsapp_business_messaging` and `whatsapp_business_management`.
  - We would then build a small Embedded Signup page (Facebook Login for Business configuration).
  - Expect 1 to 3 weeks, mostly App Review.
- **App version:** WhatsApp Business app **2.24.17 or newer**.
- **What the owner does in the flow:**
  1. Choose "Connect your existing WhatsApp Business app" and enter the number.
  2. WhatsApp shows a code. Open the Business app, where a message from the official Facebook Business account appears. Tap **Connect** → **Connect to the Business Platform** → **Confirm**.
  3. Choose whether to share chat history: up to the **last 6 months (180 days)** of one-to-one chats, without groups.
  4. Keep the app open while it syncs. The partner must finish the sync within 24 hours, or the number is disconnected and you start again.
- **Limits after connecting:**
  - Speed is fixed at **20 messages/second**, which is plenty for us.
  - **Broadcast lists** are switched off; existing ones become read-only.
  - **Disappearing messages**, **view once** and **live location** are switched off in one-to-one chats.
  - **Group chats** keep working in the app but are not visible to the API.
  - **Linked devices** are all logged out once. You can re-link them, except **WhatsApp for Windows** and WearOS. Use WhatsApp Web in a browser on the counter PC instead.
  - Message edit and delete still work.
  - Calls stay in the app; there is no API calling on coexistence numbers.
  - You must **open the Business app on the main phone at least once every ~14 days**, or Meta disconnects the API link (`PRIMARY_INACTIVITY`).
- **Pricing on coexistence:** messages typed in the Business app stay **free**. Only messages sent by the CRM through the API are charged.

### Message prices in India (Meta list rates, 2026, plus 18% GST)
| Type | Rate per delivered message |
|---|---|
| Utility (bill, receipt, car ready, quote) | about **₹0.115** |
| Marketing (follow-up, offers) | about **₹0.863** (raised 1 Jan 2026) |
| Service (free-form reply after the customer writes) | first **1,000 per number per month free**, charged after that (from 1 Oct 2026) |

**Important 2026 changes:**
- **Since 1 Oct 2026, utility messages are charged even inside the 24-hour window.** They were free from July 2025 to September 2026.
- Everything stays free for 72 hours (up to 7 days per the developer docs) after a customer messages you from a **Click-to-WhatsApp ad**. That covers many of our ad leads.
- India accounts must bill in INR by 31 Dec 2026. The main account is already INR.

Exact paise rates come from Meta's INR rate card. Check it again before budgeting.

## 4. Recommended route

**Recommendation: coexistence on 98231 07676 through our own app as a free Tech Provider.**
- It keeps the one-number decision in CRM-PLAN §2. Customers get bills from the same number they chat with, and Meta charges no partner fee.
- Until it is live, the CRM runs in **manual mode** (a "Send on WhatsApp" button with a PDF link), so billing is not blocked.
- **Fallback:** if App Review is refused or takes longer than about 3 weeks, use **Option B (new SIM)**. It can be live in a day.

### Option A: coexistence on 98231 07676 (recommended)
- **Pros:**
  - One number, with chats and bills in the same thread.
  - App messages stay free.
  - No new SIM to look after.
  - Ad chats and bills live together.
- **Cons:**
  - Needs Tech Provider App Review (1 to 3 weeks, free).
  - Broadcast lists, disappearing messages and the WhatsApp Windows app stop working.
  - The phone must open the app every 14 days.
  - The CRM webhook must be live **before** connecting, to take the history sync within 24 hours.

**Steps:**
1. *(Owner)* Fix billing: Meta Business Settings → **Billing & payments** → WhatsApp account "Jai Ganesh Car Accessories" → add a working card or UPI autopay. This clears error 141006.
2. *(Developer)* In developers.facebook.com → app "Automation" → **Use cases → WhatsApp → Customize → Tech Provider onboarding**:
   - fill app icon, privacy policy URL (website `/privacy`) and category;
   - record the two videos;
   - request Advanced access for `whatsapp_business_messaging` and `whatsapp_business_management`;
   - submit.
3. *(Developer)* Build the Embedded Signup page on the CRM (`/settings/whatsapp/connect`), with v5 or `featureType: whatsapp_business_app_onboarding`.
4. *(Developer)* Deploy the webhook (see §6). Subscribe the app to the WhatsApp account.
5. *(Owner, about 10 minutes, phone in hand)*:
   1. Update the WhatsApp Business app to the latest version.
   2. Open the CRM connect page and log in with Facebook.
   3. Pick business "Jay Ganesh Car Accessories" and "Connect existing WhatsApp Business app".
   4. Enter 98231 07676.
   5. In the app, tap **Connect** → **Connect to the Business Platform** → **Confirm**.
   6. Choose **Share chat history** (recommended).
   7. Keep the app open until it says it is connected.
6. *(Owner)* Re-link the counter PC with WhatsApp **Web** (browser), not the Windows app.
7. *(Developer)* Copy the phone number id into `.env` and send a test bill to the owner's own number.

**If we would rather not wait for App Review:** a partner such as **AiSensy** can run the coexistence signup for us. AiSensy is already linked to WhatsApp account 1241803477398150. Ask the owner who set this up and which number it used. Partners charge a monthly plan, typically around ₹1,000–3,500/month for Indian partners; check current prices. After that, our own app can still send through the Cloud API if the account is shared with it. **Do not** connect 98231 07676 to two partners: switching partners on coexistence is not supported without disconnecting first.

### Option B: a new SIM only for the API
- **Pros:**
  - No App Review needed; live in about one day.
  - The Business app on 98231 07676 is untouched (broadcast lists, Windows app, no 14-day rule).
- **Cons:**
  - Customers get bills from a second number they don't know.
  - Replies to that number go to the CRM, not the shop phone.
  - The prepaid SIM costs about ₹200–300/month to keep alive.
  - Two numbers to explain on the bill.

**Steps:**
1. *(Owner)* Buy a new prepaid SIM. It must **never** have been on WhatsApp, and it must be able to receive an SMS or call once.
2. *(Owner)* Fix billing (same as A1).
3. *(Owner, with the developer)* WhatsApp Manager → account "Jai Ganesh Car Accessories" → **Phone numbers → Add phone number**:
   1. Display name "Jay Ganesh Car Accessories".
   2. Category Automotive.
   3. Verify by SMS code.
   4. Set a 6-digit two-step PIN.
4. *(Developer)* Register the number for Cloud API, subscribe the app, and copy the phone number id into `.env`.
5. *(Owner)* Display-name approval comes from Meta within about a day.

### Monthly cost at about 40 bills/day (approximately 26 working days ≈ 1,040 bills)

| Line | Option A (coexistence) | Option B (new SIM) |
|---|---|---|
| 1,040 `invoice_ebill` × ₹0.115 | ₹120 | ₹120 |
| About 300 receipts / car-ready × ₹0.115 | ₹35 | ₹35 |
| About 100 follow-ups (marketing) × ₹0.863 | ₹86 | ₹86 |
| Free replies (under 1,000 service messages) | ₹0 | ₹0 |
| GST 18% | ₹43 | ₹43 |
| SIM recharge | ₹0 | about ₹250 |
| Partner plan | ₹0 (own Tech Provider) or about ₹1,000–3,500 if a partner is used | ₹0 |
| **Total** | **about ₹285/month** | **about ₹535/month** |

Bills to customers who came from a Click-to-WhatsApp ad in the last 72 hours are free, so the real bill will be a bit lower.

## 5. `.env` values the CRM needs (names only)

```
WHATSAPP_TOKEN=            # permanent system-user token with whatsapp_business_messaging + whatsapp_business_management
WHATSAPP_PHONE_NUMBER_ID=  # appears after the number is connected (Option A) or registered (Option B)
WHATSAPP_WABA_ID=          # the main WhatsApp account id
WHATSAPP_APP_SECRET=       # app "Automation" → Settings → Basic → App secret (used to check webhook signatures)
WHATSAPP_VERIFY_TOKEN=     # any long random string we choose; entered in the app's webhook settings
```

Use a dedicated system user for the CRM instead of reusing the marketing token in `automation/.env`, so the CRM token cannot touch ads.

## 6. Webhook

- **Callback URL:** `https://crm.jayganeshcaraccessories.com/webhooks/whatsapp`
- **Verify token:** the value of `WHATSAPP_VERIFY_TOKEN`
- **Fields to subscribe:**
  - `messages`: incoming chats, STOP opt-outs, and delivery/read status of bills;
  - `message_template_status_update`, `message_template_quality_update`, `template_category_update`;
  - `account_update`: includes disconnection alerts such as `PRIMARY_INACTIVITY`;
  - `phone_number_quality_update`, `account_alerts`;
  - **coexistence only:** `history`, `smb_message_echoes`, `smb_app_state_sync`.
- Then subscribe the app to the WhatsApp account (`POST /1834738223843088/subscribed_apps`). This is a developer step at go-live and was not done yet.

## 7. Checklist

- [x] Business verified
- [x] Main WhatsApp account approved (INR)
- [x] 5 templates submitted (pending review)
- [ ] **OWNER:** add a working payment method to WhatsApp account "Jai Ganesh Car Accessories" (error 141006)
- [ ] **OWNER:** choose Option A (same number, wait 1 to 3 weeks) or Option B (new SIM, about 1 day)
- [ ] **OWNER:** tell us who set up AiSensy on "Jayganesh sound 76", and whether it is still in use or paid for
- [ ] **OWNER:** update the WhatsApp Business app on the 98231 07676 phone (2.24.17 or newer)
- [ ] Developer: Tech Provider App Review (Option A)
- [ ] Developer: deploy CRM webhook + Embedded Signup page
- [ ] **OWNER:** do the 10-minute connect on the phone (Option A) or buy and verify the SIM (Option B)
- [ ] **OWNER:** open the Business app at least every 14 days (Option A)
- [ ] Developer: fill `.env`, subscribe webhook, send a test bill to the owner

## Sources
- Coexistence guide: https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/onboarding-business-app-users (older URL: https://developers.facebook.com/docs/whatsapp/embedded-signup/custom-flows/onboarding-business-app-users)
- Become a Tech Provider: https://developers.facebook.com/documentation/business-messaging/whatsapp/solution-providers/get-started-for-tech-providers
- Tech Provider vs Solution Partner: https://developers.facebook.com/documentation/business-messaging/whatsapp/solution-providers/overview
- Pricing and 2026 changes: https://developers.facebook.com/docs/whatsapp/pricing and https://developers.facebook.com/docs/whatsapp/pricing/updates-to-pricing
- Rate cards (INR): https://whatsappbusiness.com/products/platform-pricing/
- India list rates (third-party summary, check against Meta's INR card): https://ecorpit.com/whatsapp-business-api-cost-india-d2c-2026/
- 14-day rule (partner docs): https://respond.io/help/whatsapp/whatsapp-coexistence, https://documentation.qiscus.com/omnichannel-chat/whatsapp-coexistence
