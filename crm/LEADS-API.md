# Leads API: website form and Meta lead ads

How enquiries get into the CRM's **Leads** list by themselves. Every new lead gets a Day-0 follow-up (call today) and then the usual Day 1 / 3 / 6 / 10 follow-ups, so it shows in **Follow-ups today** with the quotations. Written 6 Nov 2026.

| Way in | Address | Who sets it up |
|---|---|---|
| Website enquiry form | `POST https://crm.jayganeshcaraccessories.com/api/v1/leads` | Website developer, with a key from Owner → API keys |
| Meta (Facebook/Instagram) lead ads | `https://crm.jayganeshcaraccessories.com/webhooks/meta-leads` | Owner/developer in Meta Business, once (steps below) |

---

## 1. Make a key

1. Sign in as the Owner, open **API keys** (`/settings/api-keys`).
2. Type a name ("Website form") and press **Make key**.
3. Copy the key. **It is shown only once.** Put it in the website's server settings as `CRM_LEADS_KEY`.
4. To stop a key (leaked, developer changed), press **Switch off**. Anything using it gets `401` at once.

A key can only create leads (ability `leads:create`). It belongs to a switched-off login with no role ("Website & ads (API)"), so even if it leaks, it can't read customers, bills or prices.

## 2. `POST /api/v1/leads`

Headers: `Authorization: Bearer <key>`, `Content-Type: application/json`, `Accept: application/json`.

| Field | Required | Notes |
|---|---|---|
| `mobile` | yes | Any format: `98231 07676`, `+91 98231 07676`, `098231…`. Must be a 10-digit Indian mobile. |
| `name` | no | Up to 120 characters |
| `interest` | no | What they asked about: "PPF", "Seat covers" (300) |
| `car` | no | "Hyundai Creta" (120) |
| `message` | no | Free text from the form (2,000) |
| `source` | no | `website` (default), `meta_lead_ad`, `instagram`, `other` |
| `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` | no | Copy them from the page URL |
| `page_url` | no | The page the form was on |

Answers:

| Status | Body | Meaning |
|---|---|---|
| `201` | `{"ok":true,"lead_id":12,"merged":false,"message":"…"}` | New lead, Day-0 follow-up made |
| `200` | `{"ok":true,"lead_id":12,"merged":true,…}` | Same mobile already enquired in the last 7 days: added to that lead as a note, no second lead |
| `401` | `{"message":"Unauthenticated."}` | No key, wrong key, or switched off |
| `403` | | Key without `leads:create` |
| `422` | `{"message":"…","errors":{"mobile":["Enter a valid 10-digit Indian mobile number."]}}` | Fix the field and show the message to the visitor |
| `429` | `Retry-After: 42` | Too many: 20 a minute per IP, 300 an hour per key (`LEADS_API_PER_MINUTE`, `LEADS_API_PER_HOUR`) |

Rules:
- **7-day merge.** The same number again within 7 days goes into the open lead ("+ 6 Nov, 4:10 pm (Website): Mats too"). A lead that is already Won or Lost isn't reopened; the new enquiry becomes a new lead.
- If the number belongs to a saved customer, the lead is linked to them. Leads never create customers.
- New leads are unassigned. Owner/Admin see them in Leads and Follow-ups and hand them out.
- Every arrival is also kept in `lead_intakes` with its UTM tags, for the "ad → walk-in → sale" report.

### curl

```bash
curl -X POST https://crm.jayganeshcaraccessories.com/api/v1/leads \
  -H "Authorization: Bearer $CRM_LEADS_KEY" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Rahul Pawar","mobile":"98231 07676","interest":"PPF","car":"Hyundai Creta",
       "message":"Full body price?","source":"website",
       "utm_source":"google","utm_medium":"cpc","utm_campaign":"ppf-pune",
       "page_url":"https://jayganeshcaraccessories.com/ppf"}'
```

Local test (after `php artisan migrate --seed`, key made on `/settings/api-keys`):

```bash
curl -X POST http://127.0.0.1:8000/api/v1/leads -H "Authorization: Bearer 1|abc…" \
  -H "Accept: application/json" -d mobile=9823107676 -d interest=LED
```

## 3. How the website form should send it

The website is Next.js on Vercel. **Send from the server, never from the browser,** so the key stays secret: the form posts to the website's own route, and that route calls the CRM.

```ts
// website: app/api/enquiry/route.ts (sketch for the website developer; not in this repo's CRM)
export async function POST(req: Request) {
  const form = await req.json();
  const res = await fetch(`${process.env.CRM_URL}/api/v1/leads`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${process.env.CRM_LEADS_KEY}`,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      name: form.name, mobile: form.mobile, interest: form.interest, car: form.car,
      message: form.message, source: 'website', page_url: form.pageUrl,
      utm_source: form.utm_source, utm_medium: form.utm_medium, utm_campaign: form.utm_campaign,
      utm_term: form.utm_term, utm_content: form.utm_content,
    }),
  });
  const data = await res.json().catch(() => ({}));
  // 422: show data.errors.mobile[0] next to the mobile field. 429/5xx: ask them to WhatsApp instead.
  return Response.json(data, { status: res.status });
}
```

- Read the `utm_*` values from the landing URL and keep them (cookie or sessionStorage) until the form is sent.
- On success show "Thanks! We'll call or WhatsApp you today." If the CRM is down, still show the WhatsApp button so the enquiry isn't lost.
- Vercel env vars: `CRM_URL=https://crm.jayganeshcaraccessories.com`, `CRM_LEADS_KEY=<key from step 1>`.

## 4. Meta lead ads (optional)

Off until `META_LEADS_ENABLED=true`; while off, `/webhooks/meta-leads` answers 404.

1. **Meta app.** Use the same Meta app as WhatsApp (its App secret is reused when `META_LEADS_APP_SECRET` is empty), or a new Business app.
2. **Token.** Business settings → System users → the CRM system user → Add assets → the Facebook **Page** (full control). Generate a token with `leads_retrieval`, `pages_show_list`, `pages_read_engagement`, `pages_manage_metadata`. Put it in `META_LEADS_PAGE_TOKEN`.
3. **Server `.env`:**
   ```
   META_LEADS_ENABLED=true
   META_LEADS_APP_SECRET=        # empty = WHATSAPP_APP_SECRET
   META_LEADS_VERIFY_TOKEN=<long random text>
   META_LEADS_PAGE_TOKEN=<token from step 2>
   META_GRAPH_VERSION=v23.0
   ```
   Then `php artisan config:cache`.
4. **Webhook.** Meta app → Webhooks → **Page** → Subscribe: callback `https://crm.jayganeshcaraccessories.com/webhooks/meta-leads`, verify token = `META_LEADS_VERIFY_TOKEN`. Tick the **leadgen** field.
5. **Connect the page to the app** (once):
   ```bash
   curl -X POST "https://graph.facebook.com/v23.0/<PAGE_ID>/subscribed_apps?subscribed_fields=leadgen&access_token=<PAGE_TOKEN>"
   ```
6. **Lead access.** Meta Business Suite → Settings → Leads access → allow the CRM app (and the system user).
7. **Test** with Meta's Lead Ads Testing Tool (developers.facebook.com/tools/lead-ads-testing): make a test lead, and it shows in CRM Leads within seconds with source "Meta lead ad".

What happens: Meta posts `{"object":"page","entry":[{"changes":[{"field":"leadgen","value":{"leadgen_id":"…"}}]}]}` signed with `X-Hub-Signature-256`. The CRM checks the signature, reads the answers from `GET /{leadgen_id}`, and creates the lead (same 7-day merge, Day-0 follow-up). The form's phone question becomes the mobile; questions with "car/vehicle/model" in them become the car; "service/interest/looking" becomes the interest; everything else goes into the note. Campaign, ad set and ad names are saved as UTM tags. A leadgen id is only processed once. If the Graph API call fails, the CRM answers 500 so Meta retries later.

**Form tip:** in Instant Forms, keep the phone question as Meta's "Phone number" (prefilled) and add two short-answer questions: "Which car do you drive?" and "What are you looking for?".

## 5. Files

- `app/Http/Controllers/Api/V1/LeadIntakeController.php`, `app/Services/Leads/LeadIntakeService.php`
- `app/Http/Controllers/Leads/MetaLeadsWebhookController.php`, `app/Services/Leads/MetaLeadFetcher.php`, `routes/public/meta-leads.php`
- `app/Http/Controllers/Imports/ApiTokenController.php` (Owner → API keys), `app/Services/Leads/IntegrationUser.php`
- `config/leads.php`; tests in `tests/Feature/LeadsIntakeTest.php`
