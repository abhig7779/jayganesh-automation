# Jay Ganesh Content Engine

Auto-posts the Jay Ganesh Car Accessories content calendar (26 Oct – 24 Nov 2026, 30 posts) to **Instagram, Facebook and YouTube Shorts**.

- **Runs on GitHub Actions**: nothing runs on anyone's computer.
- **Media lives in Cloudflare R2**: upload a day's video or images, tick it approved, and it posts at the scheduled time.
- **The same video** goes to Instagram Reels, the Facebook Page and YouTube Shorts.

```
Cloudflare R2 (media)  ──►  GitHub Actions, every 30 min  ──►  Instagram · Facebook · YouTube Shorts
content/calendar.json  ──►  (captions, times, approval)    ──►  state/posted.json (log of what went out)
```

## How it works each day

1. **Marketix or the Jay Ganesh team uploads the media** to R2 in that day's folder (see *Media* below).
2. **Someone sets `"approved": true`** for that day in `content/calendar.json`. You can do this from the GitHub website with the pencil icon, then *Commit changes*.
3. **At the post time** (India time), the *Publish posts* workflow posts it and records the links in `state/posted.json`.
4. **Every morning at 09:30 IST**, *Readiness check* emails you if anything due in the next 36 hours is missing media, approval or a price.

If one platform fails (for example YouTube), only that platform is retried on the next run. A post is never published twice: the engine checks the account's recent posts before posting again.

## Workflows

| Workflow | When | What it does |
|---|---|---|
| **Publish posts** | Every 30 min, 06:35–22:05 IST | Posts anything due. Manual run: post one day now, pick platforms, or dry run |
| **Readiness check** | Daily 09:30 IST | Fails and emails you if the next 36 hours aren't ready |
| **Check setup** | By hand, and every Monday | Confirms tokens work; prints the Page ID and Instagram ID |
| **Validate calendar** | On every edit | Catches broken JSON, captions that are too long, too many hashtags |
| **Reply to Google reviews** | Every 2 hours, 07:45–21:45 IST | Auto-replies to 4–5★ reviews; opens an approval issue for 1–3★ |
| **Approve review reply** | When you comment `/approve` or `/reply` on a review issue | Posts the reply to Google and closes the issue |

## Media (Cloudflare R2)

One folder per day: `day-01` … `day-30`.

| Post type | Files | Rules |
|---|---|---|
| Reel / video | `day-02/video.mp4` (+ optional `day-02/cover.jpg`) | MP4 (H.264 + AAC), 9:16, 1080×1920, 5–90 s, under 300 MB. Under 3 min so YouTube treats it as a Short |
| Single image | `day-07/1.jpg` | **JPEG only** (Instagram's API rejects PNG), 1080×1350 recommended |
| Carousel | `day-01/1.jpg`, `2.jpg` … up to `10.jpg` | JPEG, all the same ratio, posted in number order |

Upload in the Cloudflare dashboard: **R2 → jayganesh-content → Upload**, typing the folder name (e.g. `day-02/`) before the file name.

## Captions and prices

- All 30 captions and the YouTube titles are written in `content/calendar.json`.
- Anything in `{{DOUBLE_BRACES}}` is filled from:
  - `config/brand.json`: WhatsApp number, address, hashtags, footer
  - `config/offers.json`: prices, PPF bonus, film brand, warranty
  - the post's own `"vars"`: customer name, car and quote for review videos, slots left, cars delivered
- **A post with an empty value is never published.** It waits and the readiness check tells you which value to fill in.
- Customer review videos: add the customer's Instagram handle to `"collaborators": ["their_handle"]` to post it as a collab.

## One-time setup

### 1. GitHub
1. Put this folder in the repository (private is fine). Actions → enable workflows if asked.
2. **Settings → Secrets and variables → Actions** — add:

| Name | Type | Value |
|---|---|---|
| `MEDIA_BASE_URL` | Variable | Public R2 URL, e.g. `https://media.jayganeshcaraccessories.com` |
| `FB_PAGE_ID` | Variable | From *Check setup* (step 3) |
| `IG_USER_ID` | Variable | From *Check setup* (step 3) |
| `GRAPH_VERSION` | Variable | Optional, default `v23.0` |
| `YT_PRIVACY` | Variable | Optional: `public` (default), `unlisted` or `private` |
| `META_TOKEN` | Secret | System-user token (step 3) |
| `YT_CLIENT_ID` | Secret | Google OAuth client ID (step 4) |
| `YT_CLIENT_SECRET` | Secret | Google OAuth client secret (step 4) |
| `YT_REFRESH_TOKEN` | Secret | From OAuth Playground (step 4) |
| `GBP_CLIENT_ID` | Secret | OAuth client ID for reviews (falls back to `YT_CLIENT_ID` if not set) |
| `GBP_CLIENT_SECRET` | Secret | OAuth client secret for reviews (falls back to `YT_CLIENT_SECRET`) |
| `GBP_REFRESH_TOKEN` | Secret | Business Profile token (see *Google review auto-replies*) |
| `ANTHROPIC_API_KEY` | Secret | Claude API key for writing review replies |
| `GBP_LOCATION` | Variable | `accounts/…/locations/…`, printed by the first reviews run |

Paste tokens straight into GitHub secrets. Don't send them over chat or email.

### 2. Cloudflare R2
1. Cloudflare dashboard → **R2 → Create bucket** → name `jayganesh-content`, location Asia-Pacific.
2. Bucket → **Settings → Public access**:
   - **Custom domain** (best): `media.jayganeshcaraccessories.com`, once the domain is on Cloudflare.
   - or **R2.dev subdomain** → *Allow access*. Gives `https://pub-xxxx.r2.dev` (fine to start; Cloudflare rate-limits it).
3. Save that URL as the `MEDIA_BASE_URL` variable. Instagram and Facebook download the media from this URL, so it must be public.

### 3. Meta (Instagram + Facebook): a token that doesn't expire
Requirements: the Instagram account is a **Professional (Business)** account, **linked to the Jay Ganesh Facebook Page**, and the Page is in Jay Ganesh's **Meta Business portfolio** with you (Marketix) as admin.

1. **developers.facebook.com → My Apps → Create app** → use case *Other* → type **Business** → connect it to the Jay Ganesh business portfolio.
2. **business.facebook.com → Settings → Users → System users → Add** → name `content-engine`, role **Admin**.
3. On that system user: **Assign assets** → the Facebook Page (full control) and the Instagram account (full control). **Add assets** → the app.
4. **Generate new token** → app = the one above → expiry **Never** → permissions:
   `instagram_basic`, `instagram_content_publish`, `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `business_management`.
5. Copy the token straight into the GitHub secret `META_TOKEN`.
6. GitHub → **Actions → Check setup → Run workflow**. Its summary lists `FB_PAGE_ID=…` and `IG_USER_ID=…`. Save both as variables and run it again: everything should be ✓.

### 4. YouTube: a refresh token, without running anything locally
1. **console.cloud.google.com** → new project `jayganesh-content` → **APIs & Services → Library → YouTube Data API v3 → Enable**.
2. **OAuth consent screen** → External → app name *Jay Ganesh Content Engine* → add your email → **Publish app (In production)**.
   If it stays in *Testing*, the refresh token expires after 7 days.
3. **Credentials → Create credentials → OAuth client ID** → *Web application* → authorised redirect URI:
   `https://developers.google.com/oauthplayground`
4. Open **developers.google.com/oauthplayground** → ⚙️ → *Use your own OAuth credentials* → paste the client ID and secret.
5. In *Step 1*, enter both scopes:
   `https://www.googleapis.com/auth/youtube.upload` and `https://www.googleapis.com/auth/youtube.readonly` → **Authorize APIs**.
   Sign in with the Google account that **owns or manages the Jay Ganesh YouTube channel** (choose the channel if asked).
6. *Step 2* → **Exchange authorization code for tokens** → copy the **Refresh token**.
7. Save `YT_CLIENT_ID`, `YT_CLIENT_SECRET` and `YT_REFRESH_TOKEN` as secrets, then run *Check setup* again.

> **Important: YouTube's API audit.** Google locks videos uploaded through the API by an *unaudited* project to **private**. Until the project passes YouTube's API compliance audit (free form: *YouTube API Services – Audit and Quota Extension*), Shorts upload as private and someone must switch them to public in YouTube Studio. Apply on day one; it usually takes a few weeks. Instagram and Facebook are not affected.

## Google review auto-replies

The *Reply to Google reviews* workflow runs every 2 hours (07:45–21:45 IST). It reads the Jay Ganesh Business Profile and handles reviews from the last 30 days that have no owner reply:

| Rating | What happens |
|---|---|
| **4–5★** | Claude writes a short, personal reply in the reviewer's language (English, Marathi or Hindi), naming the car or job they mentioned, and it is **posted straight away** |
| **1–3★** | Claude writes a careful draft and the engine opens a **GitHub issue** labelled `review`. Comment `/approve` to post it, or `/reply` with your own text on the next line. Call the customer first if something needs fixing |

- Replies never include prices, offers or links. Negative replies give the shop's WhatsApp number from `config/brand.json`.
- At most 10 replies per run, so a backlog clears over a few runs instead of all at once.
- `state/reviews.json` records every review handled, so nothing is replied to twice.
- Change the auto-reply threshold with the variable `AUTO_REPLY_MIN_STARS` (default `4`), and how far back to look with `REVIEW_SINCE_DAYS` (default `30`).
- First time: run the workflow by hand with **Dry run** ticked (the default for manual runs) to read the replies it would post.

### Setup (one time)
1. **Ask Google for API access** (required: new projects get a quota of 0 for the Business Profile APIs).
   - In the same Google Cloud project, enable *My Business Account Management API*, *My Business Business Information API* and *Google My Business API*.
   - Then submit Google's **Business Profile API access request form** (search "GBP API access request") with the project number. Approval usually takes a few days to two weeks.
   - Until then, runs fail with a quota error. That is expected.
2. **Refresh token**: in the OAuth Playground (with the reviews OAuth client — save its ID and secret as `GBP_CLIENT_ID` / `GBP_CLIENT_SECRET`, or reuse the YouTube client), authorise the scope
   `https://www.googleapis.com/auth/business.manage` while signed in as the Google account that **manages the Jay Ganesh Business Profile**. Save the refresh token as the secret `GBP_REFRESH_TOKEN`.
3. **Claude API key**: create a key at console.anthropic.com and save it as the secret `ANTHROPIC_API_KEY`. Each reply costs well under ₹1.
4. **Location**: run *Reply to Google reviews* once by hand. It lists the profiles the account manages as `GBP_LOCATION=accounts/…/locations/…`. Save the right one as the variable `GBP_LOCATION`.

## Running things by hand (GitHub website)

- **Post day 5 now**: Actions → *Publish posts* → Run workflow → day `5`.
- **Retry only YouTube for day 5**: same, with platforms `youtube`.
- **See what would post**: tick *Dry run*.
- **Pause everything**: Actions → *Publish posts* → ⋯ → *Disable workflow*.
- **Change a time or caption**: edit `content/calendar.json` (times are IST, `HH:MM`).
- **Next month**: replace `posts` in `content/calendar.json` and clear `state/posted.json` to `{}`.

## Limits and good to know

- **Instagram:** 100 API posts per 24 hours. **YouTube:** about 6 uploads a day on the default quota. One post a day is far inside both.
- **GitHub schedules can start a few minutes late** at busy times. Posts still go out in the same half hour.
- **Stories are not automated**: the API can't add stickers (polls, links, Q&A). Post them by hand from the "Stories that day" column in the Excel calendar.
- **On a private repository**, these workflows use roughly 1,000 of GitHub's 2,000 free Actions minutes a month.

## Local test (optional, for developers)

```bash
npm run validate          # check the calendar and list missing values
npm run dry-run           # what would post right now (needs MEDIA_BASE_URL)
NOW=2026-10-27T14:10:00Z npm run dry-run   # pretend it's 19:40 IST on 27 Oct
```
