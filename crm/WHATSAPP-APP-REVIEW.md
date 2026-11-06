# Meta App Review: app "Automation" (2901906313477061)

Written 6 Nov 2026. This file holds the texts and screencast scripts for the App Review submission, and the owner's checklist. **Nothing is submitted until the owner has read the final list and texts and says yes.**

## 1. Where things stand (checked in the dashboard on 6 Nov 2026)

| Item | State |
|---|---|
| Business verification (portfolio 1728410417676996) | Approved |
| App icon, app domains | Done on 5–6 Nov: generic icon uploaded; domains `jayganeshcaraccessories.com` and `jayganesh-website.pages.dev` |
| Privacy policy / terms / data deletion URLs | Switched on 6 Nov to `https://jayganeshcaraccessories.com/privacy-policy/`, `/terms/` and `/privacy-policy/#delete-your-data` (all live, checked with curl) |
| App Review request | **Not submitted.** 37 items in the request (list in §3). Nothing was removed; "Business Asset User Profile Access" was added on 6 Nov. |
| Facebook Login for Business configuration | Created 6 Nov: **"WA coexistence - CRM", config id `4202556123367996`**. WhatsApp Embedded Signup; products WhatsApp Cloud API + Marketing Messages API for WhatsApp; system-user token that **never expires**; asset WhatsApp accounts (Manage); permissions `whatsapp_business_messaging`, `whatsapp_business_management`, `whatsapp_business_manage_events`. |
| Tech Provider onboarding | Step 1 (business verification) done. Step 2 is this App Review: advanced access to `whatsapp_business_messaging` and `whatsapp_business_management`. Coexistence onboarding of 98231 07676 from our own app needs it. |

## 2. The plan in one paragraph

The shop number **+91 98231 07676** (where the Click-to-WhatsApp ads send chats) stays on the WhatsApp Business app on the shop phone **and** comes onto the Cloud API for the CRM. This is "coexistence", done through Embedded Signup on the CRM's owner-only page **WhatsApp → Connect** (`/whatsapp/connect`). After that:
- The CRM sends e-bills, receipts, quotations and reminders from the same number.
- New customer messages (by default only chats that start from our Click-to-WhatsApp ads) get an **AI-written reply**. The AI asks for the car and the need, never quotes prices or offers that are not in the ad, never confirms bookings, and hands the chat to a person for prices and bookings.
- When anyone replies from the phone app, the AI stays quiet in that chat for 12 hours. Every message, the phone's included, is logged in the CRM.

## 3. Use-case texts, one per item (paste into App Review)

Status key:
- **Used:** working today, and the screencast can show it.
- **Built:** code is on branch `whatsapp-coexistence`; works once the CRM is live.
- **Planned:** not built yet. Meta asks for a screencast of a *working* feature, so these will probably be rejected if submitted now.
- **No use yet:** nothing in our code uses it.

Rejected items can be requested again later. Whether to include Planned and No-use items in this first submission is the **owner's decision**. They stay in the app either way; nothing gets deleted.

### WhatsApp (the reason for this submission)

**whatsapp_business_messaging** (Built)
> Jay Ganesh Car Accessories is a car accessories shop and detailing studio in Pune. Our in-house CRM uses the WhatsApp Cloud API on the shop's own number to send customers their invoice (PDF), payment receipts, quotations and "vehicle ready" messages through approved templates, and to answer customers who message us. Replies to new chats, mainly from our Click-to-WhatsApp ads, are written by an AI assistant that says it is automated, answers only about our shop's products and services, and hands the chat to our staff for prices and bookings (Human handoff). Staff can also reply from the WhatsApp Business app on the same number (coexistence). We only message customers who bought from us or wrote to us first, and STOP opts them out.

**whatsapp_business_management** (Built)
> We use this to connect the shop's WhatsApp Business account to our CRM through Embedded Signup (coexistence with the WhatsApp Business app), subscribe our app to the account's webhooks, read the phone number's status, start the one-time contacts and chat-history sync, and create and check our message templates (invoice_ebill, payment_receipt, quotation_send, vehicle_ready, followup_reminder, review_request).

**whatsapp_business_manage_events** (Planned)
> When a WhatsApp chat that started from our Click-to-WhatsApp ad turns into a booking or a bill in the CRM, we send a LeadSubmitted / Purchase event for that conversation, so our ads are optimised for customers who actually buy.

**paid_marketing_messages** (Planned)
> Our Embedded Signup configuration includes the Marketing Messages API for WhatsApp. We plan to send opted-in customers occasional offers (e.g. festive PPF or coating offers) as marketing templates, respecting STOP opt-outs.

**manage_app_solution** (No use yet)
> Only for multi-partner solutions between two Tech Providers. We do not use one today.

### AI replies and handoff (Messenger / Instagram)

**Human Agent** (Planned)
> Our AI assistant answers first; when a customer asks for a price, a booking or a person, a member of staff takes over. Staff often reply after the shop opens the next day, so we need the 7-day human-agent window to answer Messenger and Instagram customers.

**Business Asset User Profile Access** (Planned)
> Lets our AI assistant and staff greet Messenger/Instagram customers by name in replies.

**pages_messaging** (Planned)
> Answer customer messages to our Facebook Page from the same inbox, with AI-written replies and a human handoff.

**instagram_manage_messages**, **instagram_business_manage_messages** (Planned)
> Answer customer DMs to our Instagram business account with AI-written replies and a human handoff.

**pages_utility_messaging** (Planned)
> Send Messenger utility messages (order or appointment updates) to customers who message our Page.

### Publishing our content (already running)

**pages_show_list**, **pages_read_engagement**, **pages_manage_posts** (Used)
> Our automation publishes the shop's approved posts and reels (one content calendar) to our own Facebook Page on schedule, and reads back the post link for our log.

**instagram_basic**, **instagram_content_publish**, **instagram_manage_comments** (Used)
> The same automation publishes our approved photos and reels to our Instagram business account, adds our first comment, and reads our recent reels for the "Watch our work" section on our website.

**instagram_business_basic**, **instagram_business_content_publish** (Used: reading reels / same publishing through Instagram Login)
> Reads our own latest reels and captions to show them on our website by category (PPF, coating, interior, lights, accessories), and publishes our scheduled posts.

**business_management** (Used)
> Reads the Pages, Instagram account, ad account and WhatsApp account that belong to our business portfolio, so the automation and CRM use the right assets.

**public_profile** (Used, granted to every app automatically)

**pages_manage_metadata** (Planned)
> Subscribe our app to Page webhooks so new Messenger messages and comments reach our inbox.

**pages_manage_engagement**, **pages_read_user_content** (Planned)
> Read and answer customer comments and recommendations on our Facebook Page posts.

**read_insights**, **instagram_manage_insights** (Planned)
> Show reach and engagement of our own posts and reels in our monthly report.

**instagram_manage_contents** (No use yet)

### Ads (run by hand in Ads Manager today)

**ads_read**, **ads_management**, **pages_manage_ads**, **Marketing API Access Tier** (Planned)
> Read results of our Click-to-WhatsApp and reel ads for the monthly report, and later create and pause our own ads from the automation.

### No use in our code yet

**facebook_branded_content_ads_brand**, **instagram_branded_content_ads_brand**, **instagram_branded_content_brand**, **threads_business_basic**, **catalog_management**, **instagram_shopping_tag_products**

## 4. Screencast scripts

Record in English, at 1080p, with the browser's address bar visible. Log in as the owner. Show the Meta permission screen when it appears. No phone numbers of real customers: use the owner's own second number. Blur nothing that Meta needs to see (app name, permission names).

### 4.1 whatsapp_business_messaging (about 90 s)
1. Open `https://crm.jayganeshcaraccessories.com/whatsapp/connect`. It shows the shop number connected, "Also on the phone app: Yes".
2. Open a test bill for the owner's second number and save it. The "Bill done" screen shows "Sent on WhatsApp".
3. Cut to the second phone: the e-bill template with the PDF arrives from Jay Ganesh Car Accessories.
4. From the second phone, reply "Hi, I saw your ad for PPF, what car do you do?" (ideally by tapping our test Click-to-WhatsApp ad).
5. Within a minute the AI reply arrives. It says it is the shop's automated assistant and asks for the car model.
6. Type "What's the price for Creta?". The AI says a team member will share the price here (handoff).
7. Back in the CRM, open **WhatsApp log**: the bill, the customer's messages and the AI replies (marked AI), all with ticks.
8. On the shop phone (WhatsApp Business app) the same chat is visible. Reply "Hi, this is the shop team" there. The log shows it as sent from the Business app, and the AI no longer replies in that chat.

### 4.2 whatsapp_business_management (about 60 s)
1. Settings → WhatsApp → Connect page → "Connect WhatsApp". Show the Facebook popup (Embedded Signup) with "Connect your existing WhatsApp Business app", the business portfolio and the WhatsApp account selected, and the permissions screen. A test portfolio is fine for the recording; do not redo the real shop number.
2. Back in the CRM: "Connected +91 …, Also on the phone app: Yes, Contacts & chat history: Sync started".
3. Graph API Explorer with app "Automation" selected: `POST /{waba-id}/message_templates` creating a sample utility template (e.g. `vehicle_ready_test`). Then `GET /{waba-id}/message_templates?name=vehicle_ready_test` showing it in review. Meta asks for this template step separately.

### 4.3 Human Agent / Messenger / Instagram items
Record only after the Messenger/Instagram inbox is built. Until then, leave them out of this submission (owner's call).

### 4.4 Publishing items (pages_manage_posts, instagram_content_publish, …)
1. Show the content calendar sheet with an approved post.
2. Show the GitHub "publish" run posting it (or run it once by hand).
3. Show the post on the Facebook Page and on Instagram with the first comment.
4. Show the website's "Watch our work" section with the latest reels.

## 5. Owner checklist (in order)

- [ ] **Add a payment method** in Meta Business for WhatsApp account 1834738223843088 (error 141006 blocks paid messages).
- [ ] **Choose the AI model for WhatsApp replies:** Claude (paid per message, chats stay private) or OpenAI's free daily tokens (free, but those tokens are only free because the conversations are shared with OpenAI; customer chats would be shared too). Then put the key on the CRM server.
- [ ] **Read §3 and decide which items go in this first submission.** Recommended: the WhatsApp two + the Used items. The developer submits only after you say yes.
- [ ] Record the screencasts (§4) with the developer once the CRM is live.
- [ ] **Connect day** (after App Review approves the WhatsApp permissions):
  - update WhatsApp Business on the 98231 07676 phone to 2.24.17 or newer;
  - open `/whatsapp/connect`, choose "Connect your existing WhatsApp Business app", scan the code with the shop phone;
  - keep the app open until the sync finishes (it must finish within 24 h).
- [ ] Click **"Allow access"** in the content calendar Google Sheet so the preview images show.

## 6. Developer notes for go-live

- **Webhook fields** to subscribe in the app (WhatsApp → Configuration, callback `https://crm.jayganeshcaraccessories.com/webhooks/whatsapp`):
  - `messages` (carries the Click-to-WhatsApp `referral`);
  - `smb_message_echoes` (phone app replies; the AI pauses);
  - `history`, `smb_app_state_sync`, `account_update`, `message_template_status_update`, `phone_number_quality_update`, `account_alerts`.
  - They can only be saved once the CRM is live, because Meta verifies the URL.
- **Config keys** the code reads (they need adding to `config/whatsapp.php`; defaults are built in where shown):
  - `app_id` (default 2901906313477061);
  - `embedded_signup_config_id` (default 4202556123367996);
  - `ai.anthropic_key` / `ai.openai_key` (or `services.anthropic.key` / `services.openai.key`);
  - `ai.claude_model` (default `claude-sonnet-5-5`); `ai.openai_model` (default `gpt-5.4`);
  - `ai.debounce_seconds` (20); `ai.max_replies_per_day` (8); `ai.pause_hours_after_owner` (12); `ai.pause_hours_after_handoff` (24).
- **Security headers** must allow Meta on the Connect page only (see the branch report): `connect.facebook.net` for scripts, `*.facebook.com` frames and connections, and `Cross-Origin-Opener-Policy: same-origin-allow-popups`. Otherwise the popup cannot report back.
- **Coexistence limits:**
  - no `/register` call, and we never request codes or OTPs;
  - the contacts and history syncs each run once, within 24 h;
  - calls stay on the phone;
  - a group chat or broadcast list from the phone is not mirrored.
- **Service window:** free-form replies (the AI's) only work within 24 h of the customer's last message, or 72 h after an ad click. After that, only templates.
