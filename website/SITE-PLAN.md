# Jay Ganesh Car Accessories: website plan

The structure, page templates, content and keyword targets for `jayganeshcaraccessories.com`. Build from this file. Brand rules (colours, fonts, voice, facts) are in the brand system: `Jaygnesh car/Brand/design-system/project/README.md`.

**Goal:** rank in Google for three groups of searches in eastern Pune, and turn visitors into WhatsApp enquiries.

1. **Detailing studio:** PPF, ceramic coating, car detailing, new-car protection
2. **Car accessories:** seat covers, lights, Android stereos, dashcams, mats, plus "car accessories near me / Pune / Loni Kalbhor / Hadapsar"
3. **Products by name:** a brand or model someone types into Google, for example "Hypersonic projector headlight" or "Sony subwoofer for Creta". Every product gets its own page with a Click-to-WhatsApp button.

**Facts rule:** use only confirmed facts (see the brand book). Prices, PPF film brand, warranty years and product brands are `{{PLACEHOLDERS}}` until the shop confirms them. Never invent a price, rating, brand or award.

---

## 1. What people search (evidence we have)

From the Google Business Profile, May – 17 Oct 2026:

| Search | Searches |
|---|---|
| car accessories pune | 1,813 |
| car accessories near me | 1,096 |
| car accessories | 863 |
| jai ganesh car accessories | 671 |
| car decor near me | 633 |
| ganesh car accessories | 535 |
| car accessories shop near me | 500 |
| car modification in pune | 481 |
| jay ganesh car accessories | 479 |
| best car accessories shop in pune | 309 |
| car accessories wholesale market in pune | 246 |

Rankings checked 18 Oct: #1 for "car accessories" and "car seat cover" around the shop and Manjri; **not in the top 40 for "car detailing"; not listed for "ppf"**. PPF specialists nearby (Crankit, Autoz Ville) all have websites; most accessory shops don't. The website is the main way to win PPF and detailing searches.

Car models that appear most in real enquiries (Leads sheet, Oct 2026): Innova Crysta, Scorpio / Scorpio N, Endeavour, Fortuner, Creta, Thar Roxx, Swift, WagonR, Ertiga, Grand Vitara, Kia Sonet, Tata Safari, Verna, Celerio, i20. Captions also feature Nexon, Brezza and XUV 3XO.

---

## 2. Site map

```
/                                   Home
/detailing-studio/                  Detailing studio (hub)
  /ppf-pune/                        Paint protection film (PPF)
  /ceramic-coating-pune/            Ceramic coating
  /graphene-coating-pune/           Graphene coating            (only if the studio offers it)
  /car-detailing-pune/              Car detailing: foam wash, interior deep clean, exterior decontamination
  /new-car-protection/              New-car packages (book before delivery)
/accessories/                       Accessories (hub)
  /seat-covers/                     Seat covers
    /seat-covers/{car}/             e.g. /seat-covers/toyota-innova-crysta/
  /car-lights/                      Lights
    /car-lights/headlights/         LED and projector headlights
    /car-lights/fog-lamps/
    /car-lights/ambient-lighting/
  /audio-screens/                   Audio and screens
    /audio-screens/android-stereo/
    /audio-screens/speakers-subwoofers/
    /audio-screens/360-camera/
  /dashcams/
  /floor-mats-interior/             Mats, steering covers, interior
  /exterior-accessories/            Body kits, sport kits, guards (only what the shop sells)
/products/                          All products (filter by category, brand, car)
  /products/{brand}-{product}/      e.g. /products/hypersonic-led-projector-headlight/
/cars/                              Shop by car
  /cars/{make}-{model}/             e.g. /cars/hyundai-creta/  (accessories + PPF for that car)
/our-work/                          Gallery of real jobs
  /our-work/{slug}/                 e.g. /our-work/fortuner-full-body-ppf/
/reviews/                           Google reviews and customer videos
/offers/                            Current offers (Diwali, post-Diwali)
/car-care-guide/                    Articles (from the car-tip reels)
  /car-care-guide/{slug}/
/areas/                             Areas we serve
  /areas/hadapsar/ …                (6 pages max, see §6)
/about/
/visit-us/                          Address, map, hours, directions, WhatsApp
/privacy-policy/   /terms/          (already live)
```

URL rules: lowercase, hyphens, no dates, no IDs. `/ppf-pune/` and `/ceramic-coating-pune/` carry the city because those are the words people type; other URLs stay short. Keep URLs stable once published.

---

## 3. Header and menus

**Top bar** (thin, dark): `📍 Kawadipat Toll Naka, Pune–Solapur Road · Open every day 10 am – 8 pm · ⭐ 4.9 on Google`

**Main header** (sticky, dark): logo left · menu centre · **WhatsApp** (yellow button) and **Call** (outline) right. On mobile: logo, WhatsApp icon button, menu button; a fixed bottom bar with **WhatsApp · Call · Directions**.

| Menu item | Sub-menu (mega menu on desktop) |
|---|---|
| **Detailing Studio** | PPF · Ceramic coating · Graphene coating · Car detailing · New-car protection · *Card: "Book a free paint inspection"* |
| **Accessories** | Column 1 *Interior*: Seat covers · Mats & interior · Ambient lighting · Column 2 *Lights*: Headlights · Fog lamps · Column 3 *Tech*: Android stereo · Speakers & subwoofers · 360° camera · Dashcams · Column 4 *Exterior*: Exterior accessories · *Card: "Shop all products"* |
| **Shop by Car** | Logo or name grid of the 12–16 most common models (list in §1) · "Don't see your car? WhatsApp us" |
| **Our Work** | — |
| **Offers** | — (shows a red "New" badge when an offer is live) |
| **Visit Us** | — |

Reviews, About and the car care guide sit in the footer and are linked from the home page, so the main menu stays short.

**Footer** (dark): logo + one-line description · address, phone, hours (same text everywhere) · map link · columns: Detailing Studio / Accessories / Shop by car / Company (About, Reviews, Car care guide, Offers) · Instagram, YouTube, Facebook · Privacy · Terms.

---

## 4. Page templates

Every page: one H1, a breadcrumb, a WhatsApp CTA above the fold and at the end, real photos from the shop, a short FAQ, and the visit-us strip. Text is English, with short Marathi or Hinglish lines the way the brand voice allows.

### 4.1 Home

1. **Hero** (dark, full-bleed photo or short muted video of the studio):
   - Eyebrow: `Loni Kalbhor · Pune–Solapur Road`
   - H1: **Car accessories and a PPF & detailing studio, under one roof**
   - Sub: *Custom seat covers, lights, Android stereos, and now paint protection film, ceramic coating and detailing. Fitted by the team eastern Pune already trusts.*
   - Buttons: **WhatsApp for a price** (yellow) · **See our work** (outline)
   - Trust strip: `⭐ 4.9 · 697 Google reviews` · `8,000+ customers` · `Open 7 days, 10–8` · `Fitting at our shop`
2. **Two doors:** two large cards, *Detailing Studio* (PPF, ceramic, detailing) and *Accessories* (seat covers, lights, audio, dashcams), each with 4 links.
3. **Shop by car:** a grid of car models, linking to `/cars/{model}/`.
4. **Popular products:** 8 product cards (image, name, "from ₹{{PRICE}}", fits these cars, **WhatsApp** button).
5. **Before and after:** slider with 3–4 real jobs → Our work.
6. **Why Jay Ganesh:** 4 points, all true and checkable. Ideas: fitting done at our own shop; product matched to your car model; neat wiring, tested before handover; one shop for accessories and paint protection. **Confirm each with the shop before publishing.**
7. **Reviews:** 3–6 real Google reviews with names and car, link to Google.
8. **Current offer:** banner from `/offers/` (hidden when there is none).
9. **Car care guide:** 3 latest articles.
10. **FAQ:** 5 questions (PPF vs ceramic, how long fitting takes, do you fit at home, payment, warranty).
11. **Visit us:** map, address, hours, directions button.

### 4.2 Service page (PPF, ceramic, detailing, new-car protection)

1. Hero: H1 with the service and city ("PPF in Pune: paint protection film for your car"), one-line promise, WhatsApp button, 1 photo
2. What it is, in plain words (3–4 sentences)
3. Who it's for (new cars, highway drivers, dark colours …)
4. What's included: packages as cards (Front kit · Full body · …) with "from ₹{{PRICE}}" and time needed
5. Film / product and warranty: `{{FILM_BRAND}}`, `{{PPF_WARRANTY_YEARS}}` years, warranty card shown
6. The process: 4–6 real steps with photos (inspection → wash → decontamination → fitting → curing → handover)
7. Comparison table (PPF vs ceramic vs wax), linked to the article
8. Our work for this service: 6 photos
9. Price by car size: small hatchback / sedan / SUV, `{{PRICES}}`, "exact price on WhatsApp"
10. FAQ (6–8)
11. CTA strip + visit us

### 4.3 Accessory category page (seat covers, lights, audio …)

1. Hero: H1 ("Car seat covers in Pune, made for your car model"), short intro, WhatsApp button
2. Types or materials as cards (for seat covers: material, colour options, `{{OPTIONS}}`)
3. **Product grid** from the product list (filters: car, brand, price band)
4. Shop by car for this category (links to `/seat-covers/{car}/`)
5. How fitting works (time, at our shop, what to bring)
6. Our work for this category · FAQ · CTA

### 4.4 Product page (the ones that rank for brand and model searches)

The page title and H1 use the exact product name people search: **{Brand} {Product name} {key spec}**, for example "Hypersonic LED Projector Headlight". Content:

1. Photo gallery (shop photos, not only stock images)
2. H1 · price "from ₹{{PRICE}}" or "price on WhatsApp" · "fitting included / extra ₹{{FIT}}"
3. **Click-to-WhatsApp button** (big, yellow). It opens WhatsApp with a ready message:
   `Hi, I want the {Product name} for my ____ (car model). Please share the price.`
   Link format: `https://wa.me/919981397676?text=` + URL-encoded message. Track clicks as an event.
4. Fits these cars (chips linking to `/cars/{model}/`)
5. Key specs (brand, type, wattage or size, warranty `{{WARRANTY}}`)
6. Why choose it (3 bullets, true)
7. Fitting at our shop: time needed, what's checked
8. Related products · FAQ (2–4) · reviews if any

**Product data lives in one place** so pages can be added without code: a **Products** tab in the *Jay Ganesh Leads* Google Sheet, with columns `slug, name, brand, category, sub-category, fits_cars, price_from, fitting, warranty, short_description, specs, photos, status`. The site reads this at build time. Each product row becomes a page, a card in its category, and an entry in the sitemap.

### 4.5 Car model page (`/cars/hyundai-creta/`)

H1 "Hyundai Creta accessories and PPF in Pune". Sections: hero with the car · seat covers for the Creta · lights · audio and screens · PPF and ceramic price for the Creta · Creta jobs from Our work · FAQ · CTA. Each section pulls products where `fits_cars` contains the model. These pages rank for "creta accessories", "creta seat cover", "creta ppf price", and answer the question customers ask first: *what fits my car?*

### 4.6 Our work (job page)

Title "{Car} {job}", for example "Toyota Fortuner full-body PPF". Before and after photos, what was done, products used, time taken, customer quote if allowed, link to the service page. These come straight from the reels already being made, so the content cost is low.

### 4.7 Car care guide (article)

One article per car-tip reel: embed the reel, then 300–600 words of the same advice written out. Examples already in the calendar: *Why a new car needs PPF in its first week* · *PPF vs ceramic vs wax* · *5 checks before taking delivery* · *Cleaning firecracker ash without scratches* · *Driving safely in fog* · *First 7 days after a coating*. Each ends with a link to the matching service.

### 4.8 Area page (`/areas/hadapsar/`)

Only for areas customers really come from: **Hadapsar, Manjri, Uruli Kanchan, Wagholi, Kharadi, Fursungi** (the Meta ads radius). Each page must be genuinely different: distance and route from that area, jobs done for customers from there, reviews mentioning it, and the services most asked for. No copy-paste pages with only the area name changed; Google treats those as spam.

### 4.9 Visit us

Map embed (link out), address exactly as on Google, hours, phone and WhatsApp, landmark directions ("at Kawadipat Toll Naka on the Pune–Solapur road"), parking, photos of the shop front, and the studio entrance if it is separate.

---

## 5. Keyword map

One main keyword per page, so pages don't compete with each other.

| Page | Main keyword | Also targets |
|---|---|---|
| Home | car accessories pune | car accessories near me, car accessories shop, jay ganesh car accessories, car decor near me |
| Detailing studio | car detailing studio pune | detailing studio near me |
| PPF | ppf pune | paint protection film pune, ppf near me, ppf price, ppf for new car |
| Ceramic coating | ceramic coating pune | ceramic coating price, ceramic coating near me |
| Car detailing | car detailing pune | car foam wash, interior deep cleaning, car detailing near me |
| New-car protection | new car ppf | new car accessories package, ppf before delivery |
| Seat covers | car seat cover pune | custom seat covers, seat cover shop near me |
| Seat covers / {car} | {car} seat cover | {car} seat cover price |
| Lights / headlights | car led headlight pune | projector headlight, headlight upgrade |
| Fog lamps | car fog lamp | fog lamp fitting near me |
| Android stereo | android stereo for car pune | car stereo shop, car screen |
| 360° camera | 360 camera for car | 360 camera price |
| Dashcams | dashcam for car pune | dash camera fitting |
| Product pages | the product's exact name | "{product} price", "{product} for {car}" |
| Car model pages | {car} accessories pune | {car} ppf price, {car} accessories |
| Car modification (section on Accessories hub) | car modification in pune | car modification shop |
| Area pages | car accessories {area} | car detailing {area}, ppf {area} |

"car accessories wholesale market in pune" (246 searches) only fits if the shop sells wholesale. Ask before targeting it.

---

## 6. SEO setup (technical)

- **Each page:** unique `<title>` (under 60 characters: "PPF in Pune · Jay Ganesh Car Detailing Studio"), meta description (under 155 characters, with a reason to click), one H1, descriptive image `alt`, canonical URL.
- **Structured data (JSON-LD):** `AutoPartsStore` + `AutoRepair` (LocalBusiness) on Home and Visit us, with name, address, phone, hours, geo, `aggregateRating` only if it matches Google; `Service` on service pages; `Product` with `offers` on product pages (only real prices); `FAQPage` on FAQ blocks; `BreadcrumbList` everywhere.
- **`sitemap.xml` and `robots.txt`** generated at build; submit in Google Search Console.
- **Speed:** static export (already set up), images in WebP at the right size, the 3D scene only on Home and lazy-loaded, fonts self-hosted through `next/font`.
- **Internal links:** every product links to its category and to the cars it fits; every job links to its service; every article links to a service.
- **Google Business Profile:** set the website field to the new domain (it currently points to Instagram), add services and products there with links to the matching pages, use the same name, address and phone as the site.
- **Name consistency:** "Jay Ganesh Car Accessories" everywhere. The "Jai Ganesh" spelling (671 searches) is covered by the home page text naturally once ("also searched as Jai Ganesh Car Accessories" in the About section), not repeated.
- **Tracking:** Google Analytics 4 and Search Console; events for WhatsApp clicks, call clicks and directions clicks, by page.

---

## 7. Build order

| Phase | Pages | Needs from the shop |
|---|---|---|
| **1. Launch before Dhanteras (6 Nov)** | Home · PPF · Ceramic coating · Car detailing · New-car protection · Seat covers · Offers · Visit us · Our work (8–10 jobs) | Prices or "from" prices, PPF film brand and warranty, 20–30 job photos, studio photos |
| **2. Accessories (by 20 Nov)** | Accessories hub · Lights (3 sub-pages) · Audio & screens (3) · Dashcams · Mats & interior · Products tab + first 30 product pages | Product list with brands, prices, photos |
| **3. Shop by car (Dec)** | 12–16 car model pages · seat covers by car | Which models they stock covers for |
| **4. Growth (ongoing)** | Car care guide (1 article per car-tip reel) · area pages (6) · more products and jobs every week | Job photos as they happen |

---

## 8. What the shop must confirm before content goes live

1. Prices or "from" prices for PPF, ceramic, detailing, seat covers, lights, stereos
2. PPF and ceramic brands, warranty years, and whether a warranty card is given
3. Whether graphene coating is offered
4. The product brands they stock (for product pages)
5. Whether they fit customer-supplied parts (reviews mention they refuse; the site should say so clearly)
6. Whether the studio has its own entrance or address
7. Founding year (Google says 2010, Justdial says 2016)
8. Pincode (412201 in the profile editor, 412308 shown publicly)
9. Permission to use customer names, photos and quotes
