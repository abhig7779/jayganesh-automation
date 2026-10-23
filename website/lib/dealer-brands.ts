import type { HeroImageKey } from "./heroes";
import type { Topic } from "./reviews";

// Brands the shop is an AUTHORISED DEALER for (owner-confirmed, Oct 2026), by
// category. Drives the brand landing pages (/garware-ppf-pune/ …), the two hubs
// (/ppf-brands-pune/, /headlight-brands-pune/), the "Authorised dealer"
// sections on /ppf-pune/, /car-lights/headlights/ and /car-lights/, the
// homepage dealer strip, the sitemap, llms.txt and the business JSON-LD.
//
// FACTS RULE: no prices, film thickness, warranty years, founding years or
// distances. Every brand fact (`intro`, `range`, brand FAQs) comes from the
// brand's OWN website or official social account, with the source URL in a
// comment next to it (checked Oct 2026). Where a brand publishes little
// (Night Scene, Maxxlink, myTVS PPF) the copy says so instead of guessing.
// Logos live in lib/brands.ts (`logo` = Brand.slug); brands with no official
// logo file get a text wordmark chip.

export type DealerCategory = "ppf" | "headlights";

export type DealerFaq = { q: string; a: string };

export type DealerRangeItem = {
  /** Product line as the brand names it. */
  name: string;
  /** What the brand says about it, in plain words. */
  detail: string;
};

export type DealerBrand = {
  /** URL slug: /<slug>/ */
  slug: string;
  category: DealerCategory;
  /** Brand name as the brand writes it. */
  name: string;
  /** Other spellings customers use (shown once, used in metadata). */
  alsoSearchedAs?: string[];
  /** lib/brands.ts slug for an official logo; omitted = text wordmark chip. */
  logo?: string;
  /** Brand's official site, where one was found. */
  url?: string;
  /** One line for the "Authorised dealer" grids. */
  note: string;
  /** <title>, written per brand (keep under ~65 characters). */
  title: string;
  /** Meta description, 140–155 characters: brand + Pune + "authorised dealer" + a CTA. */
  description: string;
  /** 2–3 sentences on what the brand is known for, from its own site. */
  intro: string;
  /** Named product lines from the brand's own site. Empty when the brand publishes none. */
  range: DealerRangeItem[];
  /** Shown under the range (or instead of it when `range` is empty). */
  rangeNote?: string;
  /** Three brand-specific questions (merged with two generic ones on the page). */
  faqs: DealerFaq[];
};

export type DealerCategoryInfo = {
  key: DealerCategory;
  /** Product noun used after the brand name: "Garware PPF", "DHC headlights". */
  product: string;
  /** Long product name for prose. */
  productLong: string;
  hubPath: string;
  hubLabel: string;
  servicePath: string;
  serviceLabel: string;
  serviceType: string;
  topics: Topic[];
  hero: HeroImageKey;
  /** Breadcrumbs above the hub (service page and its parents). */
  parents: { label: string; href: string }[];
};

export const dealerCategories: Record<DealerCategory, DealerCategoryInfo> = {
  ppf: {
    key: "ppf",
    product: "PPF",
    productLong: "paint protection film",
    hubPath: "/ppf-brands-pune/",
    hubLabel: "PPF brands",
    servicePath: "/ppf-pune/",
    serviceLabel: "PPF",
    serviceType: "Paint protection film (PPF)",
    topics: ["ppf"],
    hero: "ppf",
    parents: [
      { label: "Detailing studio", href: "/detailing-studio/" },
      { label: "PPF", href: "/ppf-pune/" },
    ],
  },
  headlights: {
    key: "headlights",
    product: "headlights",
    productLong: "LED and projector headlights",
    hubPath: "/headlight-brands-pune/",
    hubLabel: "Headlight brands",
    servicePath: "/car-lights/headlights/",
    serviceLabel: "Headlights",
    serviceType: "Car LED headlight installation",
    topics: ["lighting"],
    hero: "headlights",
    parents: [
      { label: "Accessories", href: "/accessories/" },
      { label: "Car lights", href: "/car-lights/" },
      { label: "Headlights", href: "/car-lights/headlights/" },
    ],
  },
};

export const dealerBrands: DealerBrand[] = [
  /* ---------- PPF ---------- */
  {
    slug: "garware-ppf-pune",
    category: "ppf",
    name: "Garware",
    alsoSearchedAs: ["Garware Hi-Tech Films"],
    logo: "garware-hi-tech-films",
    url: "https://www.garwarehitechfilms.com/",
    note: "Garware Hi-Tech Films PPF: named clear series, matte, black, white and colour.",
    title: "Garware PPF Pune – Authorised Dealer & Price | Jay Ganesh",
    description:
      "Garware PPF in Pune from an authorised dealer: Titanium, Matte, Black and Coloured films, fitted at our Loni Kalbhor studio. WhatsApp your car for a price.",
    // Source: https://www.garwarehitechfilms.com/ ("a global leader with over 90 years of legacy";
    // PPF card: "self-healing, scratch-resistant invisible film") and
    // https://www.garwarehitechfilms.com/paint-protection-films/coloured-ppf ("designs, formulates, and manufactures Paint Protection Film").
    intro:
      "Garware PPF is made by Garware Hi-Tech Films, which describes itself as a global leader with over 90 years of legacy and designs, formulates and manufactures its own paint protection film. Garware sells the film as self-healing and scratch-resistant. What stands out is how wide the range is: alongside its named series there are matte, black, white and coloured films, so you can protect the paint and change its look in one job.",
    range: [
      // Source: https://www.garwarehitechfilms.com/ (Paint Protection Films > Product menu, each with its own page under /paint-protection-films/)
      { name: "Titanium, Pinnacle, Premium, Glaze, Plus, Protect and Aura PPF", detail: "Garware's named PPF series. Each has its own page on Garware's site; we'll help you pick one for your car and budget." },
      // Source: https://www.garwarehitechfilms.com/paint-protection-films/matte-ppf and /matte-plus
      { name: "Matte PPF and Matte+ PPF", detail: "Two matte films, for a flat finish over gloss paint." },
      // Source: https://www.garwarehitechfilms.com/paint-protection-films/black-ppf and /white-ppf
      { name: "Black PPF and White PPF", detail: "Listed by Garware as separate black and white films." },
      // Source: https://www.garwarehitechfilms.com/paint-protection-films/coloured-ppf (shades include Matte Black)
      { name: "Coloured PPF", detail: "Garware's colour film, sold as style plus protection; its shades include Matte Black." },
      // Source: https://www.garwarehitechfilms.com/paint-protection-films/windshield-pro-plus
      { name: "Windshield Pro Plus", detail: "A windshield film listed alongside Garware's PPF range." },
    ],
    faqs: [
      // Range source: https://www.garwarehitechfilms.com/ (PPF product menu)
      { q: "Which Garware PPF finishes can I get in Pune?", a: "Garware lists its named series (Titanium, Pinnacle, Premium, Glaze, Plus, Protect and Aura) plus Matte, Matte+, Black, White and Coloured PPF. Tell us on WhatsApp how you want the car to look and we'll tell you which Garware films we can fit for it and help you choose." },
      // Source: https://www.garwarehitechfilms.com/paint-protection-films/coloured-ppf
      { q: "Is Garware PPF good for a matte or colour-change look?", a: "Yes, that's one of the reasons people pick Garware. Its Matte, Matte+, Black, White and Coloured films change the finish while still protecting the paint underneath. We'll show you the options and help you choose a finish that suits your car." },
      { q: "Do you fit Garware PPF on hatchbacks, sedans and SUVs?", a: "Yes. We fit Garware PPF on everything from small hatchbacks to large SUVs, as a front kit, on high-impact areas or on the full body. The amount of film depends on the model, so send us the make, model and year for a price." },
    ],
  },
  {
    slug: "3m-ppf-pune",
    category: "ppf",
    name: "3M",
    logo: "3m",
    url: "https://www.3mindia.in/",
    note: "3M Scotchgard™ Paint Protection Film Pro Series.",
    title: "3M PPF Pune – Authorised Dealer, Price on WhatsApp | Jay Ganesh",
    description:
      "3M Scotchgard PPF in Pune from an authorised dealer. Pro Series film fitted by our own team at our Loni Kalbhor studio. WhatsApp your car for a 3M price.",
    // Source: https://www.3mindia.in/3M/en_IN/p/d/b40066706/ (Scotchgard™ Paint Protection Film Pro Series: self-healing,
    // clear urethane topcoat) and https://www.3mindia.in/3M/en_IN/p/d/b5005352002/ (Pro Series 200: installer friendly,
    // adhesive optimised for repositioning).
    intro:
      "3M sells its car paint protection film in India under the Scotchgard™ name, as the Pro Series and the newer Pro Series 200. 3M India describes the film as self-healing, with a hard-wearing clear urethane topcoat, so light scratches fade on their own. Plenty of owners simply ask for \"3M PPF\" – this is that film, and we fit it ourselves.",
    range: [
      // Source: https://www.3mindia.in/3M/en_IN/p/d/b5005352002/
      { name: "Scotchgard™ Paint Protection Film Pro Series 200", detail: "3M's newer film, with an adhesive made for repositioning and a wide roll for large panels such as a full bonnet." },
      // Source: https://www.3mindia.in/3M/en_IN/p/d/b40066706/
      { name: "Scotchgard™ Paint Protection Film Pro Series", detail: "Clear, self-healing film with a urethane topcoat; light scratches fade on their own." },
      // Source: https://multimedia.3m.com/mws/media/1610105O/scotchgard-paint-protection-film-pro-series-matte-tds.pdf (3M technical data sheet)
      { name: "Scotchgard™ Paint Protection Film Pro Series Matte", detail: "3M's matte version of the Pro Series. Ask us about availability for your car." },
    ],
    faqs: [
      // Source: 3mindia.in product pages above
      { q: "Which 3M PPF can I get in Pune?", a: "We fit 3M Scotchgard™ Paint Protection Film from the Pro Series range, including the newer Pro Series 200. 3M also makes a matte Pro Series film. WhatsApp us your car model and we'll confirm which 3M film we can fit and help you choose." },
      // Source: https://www.3mindia.in/3M/en_IN/p/d/b40066706/
      { q: "Is 3M PPF self-healing?", a: "3M India describes its Scotchgard Pro Series film as self-healing: light scratches in the clear urethane topcoat fade on their own. We'll explain what that means for your car's paint at the inspection." },
      { q: "Do you fit 3M PPF on new cars and SUVs?", a: "Yes. Many customers bring a new car straight from the showroom, but we fit 3M film on hatchbacks, sedans and SUVs of any age once the paint is inspected and prepared. Choose a front kit, high-impact areas or the full body." },
    ],
  },
  {
    slug: "llumar-ppf-pune",
    category: "ppf",
    name: "LLumar",
    alsoSearchedAs: ["Lumar", "Llumar"],
    logo: "llumar",
    url: "https://llumar.com/en/",
    note: "LLumar (Eastman) Valor, Platinum and Gloss & Matte PPF.",
    title: "LLumar (Lumar) PPF Pune – Authorised Dealer | Jay Ganesh",
    description:
      "LLumar PPF in Pune (also searched as Lumar) from an authorised dealer: Valor, Platinum and Gloss & Matte films. WhatsApp your car model for a price.",
    // Source: https://llumar.com/en/paint-protection-film/ (product list and wording; "Product availability may vary by region";
    // footer "© 2026 Eastman Performance Films, LLC").
    intro:
      "LLumar is the window film and paint protection film brand of Eastman Performance Films, and many people in Pune search for it as \"Lumar\". Its PPF range climbs from the everyday Gloss & Matte film through the Platinum films to Valor, which LLumar sells as PPF and easy-wash ceramic-coating benefits in one. LLumar says availability varies by region, so we confirm which films we can get for your car.",
    range: [
      // Source: https://llumar.com/en/paint-protection-film/valor/
      { name: "Valor PPF", detail: "Super-hydrophobic, deep-gloss film with easy-wash ceramic-coating benefits built in (Tetrashield™ technology)." },
      // Source: https://llumar.com/en/paint-protection-film/platinum-extra/
      { name: "Platinum Extra PPF", detail: "LLumar's highest impact resistance, made for gravel roads and harsh weather." },
      // Source: https://llumar.com/en/paint-protection-film/platinum-gloss/
      { name: "Platinum Gloss PPF", detail: "High-shine top coat that blends with factory paint, with HydroGard™ stain resistance." },
      // Source: https://llumar.com/en/paint-protection-film/platinum-matte/
      { name: "Platinum Matte PPF", detail: "A flat finish over gloss paint, or to protect matte paint; beads water and self-heals." },
      // Source: https://llumar.com/en/paint-protection-film/select-black/
      { name: "Select Black PPF", detail: "Turns paint a rich, glossy black with Platinum-level protection, as a full wrap or on accents." },
      // Source: https://llumar.com/en/paint-protection-film/gloss-matte/
      { name: "Gloss & Matte PPF", detail: "LLumar's affordable, road-tested film in gloss or matte; self-heals with sun or engine heat." },
    ],
    faqs: [
      // Source: https://llumar.com/en/paint-protection-film/
      { q: "Which LLumar PPF finishes can I get in Pune?", a: "LLumar makes gloss films (Valor, Platinum Gloss, Platinum Extra and Gloss & Matte), matte films (Platinum Matte and the matte Gloss & Matte) and a glossy black film, Select Black. WhatsApp us the finish you want and we'll confirm what we can fit and help you choose." },
      // Source: https://llumar.com/en/paint-protection-film/platinum-extra/
      { q: "Is LLumar PPF good for rough roads and highway driving?", a: "LLumar sells Platinum Extra as its highest-impact film, made for gravel roads and harsh weather. If your car does a lot of highway runs or rough roads, ask us about it; we'll compare it with Platinum Gloss and Valor for your car." },
      { q: "Is Lumar PPF the same as LLumar?", a: "Yes. The brand is spelt LLumar, with two Ls, and it's made by Eastman. \"Lumar\" and \"Llumar\" are just common spellings, and it's the same film we fit at our studio." },
    ],
  },
  {
    slug: "ultraguard-ppf-pune",
    category: "ppf",
    name: "Ultraguard",
    alsoSearchedAs: ["Aultragard", "Ultragard", "Ultra Guard"],
    logo: "ultraguard",
    url: "https://www.ultraguardindia.com/",
    note: "Ultraguard clear, matte, black, colour, texture and chrome PPF.",
    title: "Ultraguard PPF Pune – Clear, Matte & Colour Dealer | Jay Ganesh",
    description:
      "Ultraguard (Aultragard) PPF in Pune from an authorised dealer: clear, matte, black, colour and texture films, fitted in-studio. WhatsApp your car model.",
    // Source: https://www.ultraguardindia.com/ ("a high-quality thermoplastic polyurethane film"; "self-healing properties,
    // featuring a high-performance heat-healing clear coating"; products listed "for car and bike").
    intro:
      "Ultraguard (often searched as Aultragard or Ultragard) sells paint protection film in India through ultraguardindia.com. Its film is thermoplastic polyurethane (TPU) with a heat-healing clear coat, and the range is unusually wide: clear gloss and clear matte, black, colour, texture, chrome and printable films, plus separate films for headlights and sunroofs.",
    range: [
      // Source: https://www.ultraguardindia.com/clear-ppf-for-car-and-bike
      { name: "Clear PPF (Clear Gloss)", detail: "Crystal-clear film with self-healing and anti-yellowing protection." },
      // Source: https://www.ultraguardindia.com/matte-ppf-for-car-and-bike
      { name: "Matte PPF (Clear Matte)", detail: "Protection with a refined satin finish." },
      // Source: https://www.ultraguardindia.com/black-ppf-for-car-and-bike
      { name: "Black PPF", detail: "Deep gloss black film for a bold look." },
      // Source: https://www.ultraguardindia.com/color-ppf-for-car-and-bike and https://www.ultraguardindia.com/printable-color-ppf
      { name: "Color PPF and Printable Color PPF", detail: "TPU colour films, and a printable film for custom designs." },
      // Source: https://www.ultraguardindia.com/texture-ppf-for-car-and-bike and https://www.ultraguardindia.com/chrome-series
      { name: "Texture PPF and Chrome Series", detail: "Textured and chrome-look films for a distinctive finish." },
      // Source: https://www.ultraguardindia.com/ (Instant Healing PPF card)
      { name: "Instant Healing PPF", detail: "Sold as repairing minor scratches and swirl marks instantly." },
      // Source: https://www.ultraguardindia.com/headlight-ppf and https://www.ultraguardindia.com/sunroof-ppf-for-car
      { name: "Headlight PPF and Sunroof PPF", detail: "Films made for headlights and for the sunroof glass." },
    ],
    faqs: [
      // Source: https://www.ultraguardindia.com/
      { q: "Which Ultraguard PPF finishes can I get in Pune?", a: "Ultraguard makes Clear (gloss), Matte, Black, Color, Printable Color, Texture, Chrome and Instant Healing PPF, plus headlight and sunroof films. WhatsApp us the look you want and we'll confirm which Ultraguard films we can fit and help you choose." },
      // Source: https://www.ultraguardindia.com/color-ppf-for-car-and-bike
      { q: "Can Ultraguard PPF change my car's colour?", a: "Yes. Ultraguard's Black, Color, Texture and Chrome films change the look while protecting the paint, and its Printable Color PPF takes custom designs. We'll show you samples and help you choose." },
      { q: "Is Aultragard PPF the same as Ultraguard?", a: "Yes. The brand is written Ultraguard; Aultragard, Ultragard and Ultra Guard are common spellings people search. It's the same film, and we fit it on hatchbacks, sedans and SUVs at our studio." },
    ],
  },
  {
    slug: "dhc-ppf-pune",
    category: "ppf",
    name: "DHC",
    logo: "dhc",
    url: "https://dhcautosolutions.com/",
    note: "DHC Autosolutions TPU paint protection film.",
    title: "DHC PPF in Pune – Authorised Dealer, Studio Fitted | Jay Ganesh",
    description:
      "DHC PPF in Pune from an authorised dealer: DHC Autosolutions' self-healing TPU film, fitted by our team at our Loni Kalbhor studio. WhatsApp for a price.",
    // Source: https://dhcautosolutions.com/ ("delivering precision, safety and aesthetics in all cars"; "brand leader in
    // Reverse Parking Sensors") and DHC's launch post linked there, https://www.instagram.com/p/DeYvWW8DsTn/
    // ("NEW ARRIVAL – DHC PAINT PROTECTION FILM (PPF)!", "TPU Heat Self-Healing Technology", "Hydrophobic Protection").
    intro:
      "DHC Autosolutions is an Indian car accessories brand, best known for its own-name lighting and for reverse parking sensors, a category it says it wants to lead. Paint protection film is a newer addition: DHC launched it as a TPU film with heat self-healing technology and hydrophobic protection.",
    range: [
      // Source: https://www.instagram.com/p/DeYvWW8DsTn/ (linked from https://dhcautosolutions.com/)
      { name: "DHC Paint Protection Film (PPF)", detail: "TPU film with heat self-healing technology and hydrophobic protection." },
    ],
    rangeNote: "DHC publishes one PPF without a series name. We'll show you the film and its warranty card before you book.",
    faqs: [
      { q: "Which DHC PPF can I get in Pune?", a: "DHC currently offers one paint protection film rather than several series. We fit it as a front kit, on high-impact areas or on the full body. WhatsApp us your car model and we'll help you choose the coverage." },
      // Source: https://www.instagram.com/p/DeYvWW8DsTn/
      { q: "Is DHC PPF self-healing?", a: "DHC describes its film as TPU with heat self-healing technology and hydrophobic protection, so light marks are meant to fade with heat and water beads off. We'll show you how it behaves on a sample panel at the studio." },
      { q: "Do you fit DHC PPF on hatchbacks, sedans and SUVs?", a: "Yes. We fit DHC PPF on hatchbacks, sedans and SUVs. The amount of film depends on the model, so send us the make, model and year for a price." },
    ],
  },
  {
    slug: "night-scene-ppf-pune",
    category: "ppf",
    name: "Night Scene",
    alsoSearchedAs: ["Nightscene"],
    note: "Night Scene paint protection film.",
    title: "Night Scene PPF in Pune – Authorised Dealer | Jay Ganesh",
    description:
      "Night Scene PPF in Pune from an authorised dealer. See the film and its warranty card at our Loni Kalbhor studio before you book. WhatsApp your car model.",
    // No official Night Scene website or social account was found (Oct 2026), so nothing about the film is claimed here.
    intro:
      "Night Scene (sometimes written Nightscene) is one of the PPF brands we're authorised to sell and fit. We couldn't find an official website for the brand, so we don't publish film details here that we can't check. Ask us and we'll show you the Night Scene film itself, with its warranty card, before you decide.",
    range: [],
    rangeNote: "Night Scene doesn't publish a range online. We'll show you the Night Scene films we have, in the studio or on WhatsApp.",
    faqs: [
      { q: "Which Night Scene PPF options can I get in Pune?", a: "We'll show you the Night Scene films we currently have, in the studio or with photos on WhatsApp, and explain the finish and warranty card for each. Then we'll help you choose." },
      { q: "Is Night Scene PPF a good choice for my car?", a: "It depends on your car, your budget and the finish you want. Because we fit several PPF brands, we can put Night Scene side by side with the others and you choose with the film in front of you." },
      { q: "Do you fit Night Scene PPF on hatchbacks and SUVs?", a: "Yes. We fit Night Scene PPF on hatchbacks, sedans and SUVs, as a front kit, on high-impact areas or on the full body. Send us your car model for a price." },
    ],
  },
  {
    slug: "tvs-ppf-pune",
    category: "ppf",
    name: "myTVS",
    alsoSearchedAs: ["TVS", "My TVS"],
    logo: "mytvs",
    url: "https://mytvs.com/",
    note: "Paint protection film from myTVS, TVS's parts and accessories brand.",
    title: "myTVS (TVS) PPF Pune – Authorised Dealer | Jay Ganesh",
    description:
      "myTVS PPF in Pune, also searched as TVS PPF, from an authorised dealer. Fitted by our own team at our Loni Kalbhor studio. WhatsApp your car model.",
    // Source: https://mytvs.com/ (store header "myTVS Parts & Accessories"; collection "Paint Protection Film (PPF)" at
    // https://mytvs.com/collections/paint-protection-film, no named products listed in Oct 2026).
    intro:
      "myTVS is the parts and accessories brand from TVS, so many people search for it simply as \"TVS PPF\". Its store lists Paint Protection Film (PPF) as a category alongside car care, lighting, body covers and wiper blades. myTVS doesn't publish named PPF series online, so we'll show you the film in person.",
    range: [],
    rangeNote: "myTVS lists PPF as a category on mytvs.com but no named film series. We'll show you the myTVS film and coverage options in the studio or on WhatsApp.",
    faqs: [
      { q: "Which myTVS PPF options can I get in Pune?", a: "We'll show you the myTVS film we have and the coverage options: a front kit, high-impact areas or the full body. Send us your car model on WhatsApp and we'll help you choose." },
      // Source: https://mytvs.com/ ("myTVS Parts & Accessories")
      { q: "Is TVS PPF the same as myTVS PPF?", a: "Yes. myTVS is TVS's parts and accessories brand, so \"TVS PPF\" and \"myTVS PPF\" mean the same film." },
      { q: "Do you fit myTVS PPF on hatchbacks, sedans and SUVs?", a: "Yes. We fit it on all three at our studio. The amount of film depends on the model, so send the make, model and year for a price." },
    ],
  },

  /* ---------- Headlights ---------- */
  {
    slug: "aozoom-headlights-pune",
    category: "headlights",
    name: "Aozoom",
    logo: "aozoom",
    url: "https://www.aozoomled.com/",
    note: "Aozoom bi-LED and laser projectors, LED headlights and fog projectors.",
    title: "Aozoom Projector & LED Headlights Pune – Dealer | Jay Ganesh",
    description:
      "Aozoom bi-LED projectors, laser lights and LED headlights in Pune from an authorised dealer, fitted and aimed at our shop. WhatsApp your car model.",
    // Source: https://www.aozoomled.com/ (company name "Guangzhou Aozoom Auto Lighting Co., Ltd."; products "exported to more
    // than 70 countries and regions"; certifications "E-MARK, CE, FCC, ISO9001"; product categories below).
    intro:
      "Aozoom is made by Guangzhou Aozoom Auto Lighting Co., Ltd. in China, and lighting is all it makes: LED headlights, bi-LED and laser projector lenses, projector fog lamps and off-road lights. The company says its products are exported to more than 70 countries and regions and carry E-MARK, CE, FCC and ISO 9001 certifications.",
    range: [
      // Source: https://www.aozoomled.com/car-headlight-projector-lens/
      { name: "Bi-LED projector lenses", detail: "Bi-LED projector lights and direct-fit lens modules, in round and square versions." },
      // Source: https://www.aozoomled.com/laser-headlight/
      { name: "Laser headlights", detail: "Laser projector lenses and laser LED driving and auxiliary lights." },
      // Source: https://www.aozoomled.com/led-headlight/
      { name: "LED headlights", detail: "High-power LED headlights, including single and dual heat-pipe designs." },
      // Source: https://www.aozoomled.com/led-fog-lamp-projector/
      { name: "LED fog lamp projectors", detail: "3-inch round and square fog projectors, including colour versions." },
      // Source: https://www.aozoomled.com/off-road-led-lights/
      { name: "Off-road LED lights", detail: "5- and 7-inch round lights and LED auxiliary lamps." },
    ],
    faqs: [
      // Source: https://www.aozoomled.com/ (product categories)
      { q: "Which Aozoom lights can I get in Pune?", a: "Aozoom makes bi-LED and laser projector lenses, LED headlights, LED fog lamp projectors and off-road lights. WhatsApp us your car model and a photo of the headlights and we'll tell you which Aozoom lights we can fit and help you choose." },
      { q: "Is an Aozoom bi-LED projector good for night highway driving?", a: "Aozoom's bi-LED and laser projector lenses are made for exactly this kind of upgrade. Whether a projector suits your car depends on the headlamp housing, so we'll check it first, and we aim the beam after fitting." },
      { q: "Do you fit Aozoom projectors on cars like the Swift, Creta or Thar?", a: "Yes, we fit Aozoom lights on hatchbacks, sedans and SUVs. What fits depends on the model, year and headlamp type, so send a photo of your headlights with the model and we'll confirm." },
    ],
  },
  {
    slug: "motoranger-headlights-pune",
    category: "headlights",
    name: "Moto Ranger",
    alsoSearchedAs: ["Motoranger"],
    logo: "moto-ranger",
    url: "https://www.motoranger.in/",
    note: "Pune's Moto Ranger: LED bulbs, Primus/Alpha/Optimus projectors, Alfatron.",
    title: "Moto Ranger Headlights Pune – Authorised Dealer | Jay Ganesh",
    description:
      "Moto Ranger LED bulbs and Primus, Alpha and Optimus projectors in Pune from an authorised dealer, fitted at our Loni Kalbhor shop. WhatsApp your car.",
    // Source: https://www.motoranger.in/ (contact "Pune, India"), https://www.motoranger.in/about.html ("from OEM replacement
    // upgrades to powerful off-road lighting") and product names/descriptions from https://www.motoranger.in/data/products.json
    // (the data behind https://www.motoranger.in/products.html).
    intro:
      "Moto Ranger (MotoRanger) is a car lighting brand from Pune, and its range covers, in its own words, everything from OEM replacement upgrades to off-road lighting. That means direct-replacement LED bulbs, its own Primus, Alpha and Optimus headlight projectors, and Alfatron car-specific multi-projector headlights.",
    range: [
      // Source: https://www.motoranger.in/data/products.json
      { name: "LED 120W, LED 165W, LED 180W, LED 250W", detail: "LED headlight bulbs sold as direct replacements for stock bulbs." },
      { name: "D-Series LED", detail: "Moto Ranger's premium D-Series LED upgrade." },
      { name: "Primus, Alpha and Optimus headlight projectors", detail: "Primus is the value pick; Alpha and Optimus use a trifocal lens, with Optimus at the top of the range." },
      { name: "2.5-inch and Square headlight projectors", detail: "A 2.5-inch projector and a square-shaped projector." },
      { name: "Monofocal, Bifocal and Trifocal fog light projectors", detail: "3-inch monofocal and trifocal, and a 2-inch plug-and-play bifocal." },
      { name: "Alfatron", detail: "Car-specific, high-performance multi-projector headlight systems." },
      { name: "Thar Armour Lights", detail: "Headlights for the Mahindra Thar with an off-road look." },
      { name: "Duolens / Triolens and 22-inch bar light", detail: "Grill lights, and a light bar with built-in turn indicators." },
    ],
    faqs: [
      // Source: https://www.motoranger.in/data/products.json
      { q: "Which Moto Ranger headlight projector should I choose?", a: "Moto Ranger describes Primus as its value-for-money projector, Alpha as a premium projector with a trifocal lens, and Optimus as its top projector, also trifocal. Alfatron is a car-specific multi-projector system. Tell us your car and budget and we'll help you choose." },
      { q: "Which Moto Ranger LED bulbs do you fit?", a: "Moto Ranger makes LED 120W, 165W, 180W and 250W bulbs and a D-Series LED. Which one fits depends on your car's bulb type, so send us your model, year and a photo of the headlights." },
      // Source: https://www.motoranger.in/data/products.json ("THAR ARMOUR LIGHTS")
      { q: "Do you fit Moto Ranger lights on the Thar and other SUVs?", a: "Yes. Moto Ranger makes Thar Armour Lights for the Mahindra Thar, and its bulbs, projectors and grill lights fit many other SUVs, sedans and hatchbacks. Send your model and we'll confirm what fits." },
    ],
  },
  {
    slug: "dhc-headlights-pune",
    category: "headlights",
    name: "DHC",
    logo: "dhc",
    url: "https://dhcautosolutions.com/",
    note: "DHC F35, VLUX, Ignite and Gleam LED headlamps and PL fog projectors.",
    title: "DHC LED Headlamps Pune – Authorised Dealer | Jay Ganesh",
    description:
      "DHC LED headlamps in Pune (Ignite, Gleam, VLUX) and PL projector fog lamps from an authorised dealer, fitted at our Loni Kalbhor shop. WhatsApp your model.",
    // Source: https://dhcautosolutions.com/product/lighting-solutions-1 (all product names below).
    intro:
      "DHC Autosolutions is an Indian car accessories brand with a large own-name lighting range. Its LED headlamps come as named series (F35, VLUX 2.0, Ignite 2.0 and Gleam 2.0) in 4300K and 6000K colours, alongside PL-series projector fog lamps, 7-inch headlights and Pulse auxiliary lights.",
    range: [
      // Source: https://dhcautosolutions.com/product/lighting-solutions-1
      { name: "F35, VLUX 2.0 F65, Ignite 2.0 F90, Gleam 2.0 F110", detail: "DHC's LED headlamp series, in 4300K and 6000K." },
      { name: "D Series LED Headlamp", detail: "Listed by DHC as its own LED headlamp series." },
      { name: "PL45, PL45.2, PL55, PL75 and PL75.2", detail: "2- and 3-inch LED projector fog lamps, including a bifocal and a 3-colour version." },
      { name: "AO36 Blackhawk and Blitz", detail: "7-inch LED headlights." },
      { name: "Blaze, Pulse, Pulse Pro and Pulse Max", detail: "Auxiliary lights, including projector versions." },
      { name: "Driving Light Duo 2.0", detail: "Driving lights, alongside 8.2-inch and 21-inch versions." },
    ],
    faqs: [
      // Source: https://dhcautosolutions.com/product/lighting-solutions-1
      { q: "Which DHC LED headlamp should I choose: F35, VLUX, Ignite or Gleam?", a: "DHC sells them as separate series: F35, VLUX 2.0 F65, Ignite 2.0 F90 and Gleam 2.0 F110, most in 4300K (warmer white) or 6000K (cooler white). Tell us your car and how you drive, and we'll help you choose." },
      { q: "Do you fit DHC projector fog lamps?", a: "Yes. DHC's PL range covers 2-inch and 3-inch LED projector fog lamps, including the PL75 bifocal and the 3-colour PL75.2. We'll check your bumper's fog lamp housing first." },
      { q: "Will DHC lights fit my Swift, Nexon or Thar?", a: "DHC makes LED headlamps, fog projectors and 7-inch headlights, so there's usually an option for hatchbacks, compact SUVs and round-headlamp SUVs alike. Send a photo of your headlights with the model and year and we'll confirm." },
    ],
  },
  {
    slug: "maxxlink-headlights-pune",
    category: "headlights",
    name: "Maxxlink",
    alsoSearchedAs: ["Maxlink", "Max Link"],
    logo: "maxxlink",
    url: "https://www.instagram.com/maxxlink_international/",
    note: "Maxxlink LED headlights and projector lights.",
    title: "Maxxlink (Maxlink) Headlights Pune – Authorised Dealer",
    description:
      "Maxxlink headlights in Pune, often searched as Maxlink, from an authorised dealer. Supplied and fitted at our Loni Kalbhor shop. WhatsApp your car model.",
    // Source: https://www.instagram.com/maxxlink_international/ (profile "Maxxlink International Corporation";
    // bio "Daily car upgrades & installations"). The brand's website, maxxlinkinternational.com, was parked in Oct 2026.
    intro:
      "Maxxlink (often searched as Maxlink) is the brand of Maxxlink International Corporation, which shares its car upgrades and installations on its official Instagram. It doesn't publish a product catalogue online, so we don't list models here: send us your car and we'll show you the Maxxlink lights that fit it.",
    range: [],
    rangeNote: "Maxxlink doesn't publish a catalogue online. WhatsApp your car model and a photo of the headlights and we'll send the Maxxlink options that fit.",
    faqs: [
      { q: "Is Maxlink the same as Maxxlink?", a: "Yes. The brand is written Maxxlink, with two Xs; Maxlink and Max Link are common spellings. It's the same brand we sell and fit." },
      { q: "Which Maxxlink headlights can I get in Pune?", a: "It depends on your car's headlamp type. Send us your model, year and a photo of the headlights on WhatsApp and we'll send the Maxxlink options that fit and help you choose." },
      { q: "Do you fit Maxxlink lights on hatchbacks and SUVs?", a: "Yes. Our team fits and wires Maxxlink lights on hatchbacks, sedans and SUVs at the shop, aims the beam and tests everything with you." },
    ],
  },
  {
    slug: "lumax-headlights-pune",
    category: "headlights",
    name: "Lumax",
    logo: "lumax",
    url: "https://lumaxestore.com/",
    note: "Lumax LED bulbs, projector fog lamps and headlamp assemblies.",
    title: "Lumax LED Bulbs & Fog Lamps Pune – Dealer | Jay Ganesh",
    description:
      "Lumax LED headlight bulbs, projector fog lamps and headlamps in Pune from an authorised dealer, fitted at our Loni Kalbhor shop. WhatsApp your car model.",
    // Source: https://www.lumaxworld.in/ ("a leading manufacturer and supplier of automotive components and systems in
    // India"; "serves virtually every major OEM in India, as well as the aftermarket and export markets") and the Lumax
    // aftermarket store https://lumaxestore.com/ (collections led-bulbs, projectors, head-lamps).
    intro:
      "Lumax is part of the Lumax DK Jain Group, which describes itself as a leading maker and supplier of automotive components in India and says it serves virtually every major car maker here. Its aftermarket store sells Lumax LED headlight bulbs, projector fog lamps and model-specific headlamp assemblies.",
    range: [
      // Source: https://lumaxestore.com/collections/led-bulbs
      { name: "LED headlight bulb kits", detail: "76W, 140W, 160W and 200W kits in H1, H4, H7, H8/H11, H18, H19, H27, 9005 and 9012 fittings, some in 4300K." },
      // Source: https://lumaxestore.com/collections/projectors
      { name: "LED and Bi-LED projector fog lamps", detail: "2-inch and 3-inch, in 3000K, 4300K, 5800K and 6000K, plus tri-colour versions." },
      // Source: https://lumaxestore.com/collections/head-lamps
      { name: "Headlamp assemblies", detail: "LED DRL headlamps listed for the Thar Roxx, Scorpio-N and Nexon/Curvv, and a 7-inch projector LED DRL set for the Thar." },
      // Source: https://lumaxestore.com/collections/drl and https://lumaxestore.com/collections/tail-lamp
      { name: "DRLs and tail lamps", detail: "Daytime running lights and tail lamps." },
    ],
    faqs: [
      // Source: https://lumaxestore.com/collections/led-bulbs
      { q: "Which Lumax LED headlight bulb fits my car?", a: "Lumax lists LED kits in H1, H4, H7, H8/H11, H18, H19, H27, 9005 and 9012 fittings, in several wattages. Your car's bulb type decides which one fits, so send us your model, year and a photo of the headlights and we'll confirm." },
      // Source: https://lumaxestore.com/collections/projectors
      { q: "Do you fit Lumax projector fog lamps?", a: "Yes. Lumax makes 2-inch and 3-inch LED and Bi-LED projector fog lamps in several colour temperatures, including tri-colour. We'll check your bumper's fog lamp housing and help you choose." },
      // Source: https://lumaxestore.com/collections/head-lamps
      { q: "Do you have Lumax headlamps for the Thar, Scorpio-N or Nexon?", a: "Lumax lists headlamp assemblies for the Thar Roxx, Scorpio-N and Nexon/Curvv, and a 7-inch projector set for the Thar. WhatsApp us your exact variant and we'll check what's available." },
    ],
  },
  {
    slug: "tvs-headlights-pune",
    category: "headlights",
    name: "myTVS",
    alsoSearchedAs: ["TVS", "My TVS"],
    logo: "mytvs",
    url: "https://mytvs.com/",
    note: "myTVS LED9 Hyperbeam Ultra and LED8 headlight bulbs.",
    title: "myTVS LED Headlight Bulbs Pune – TVS Dealer | Jay Ganesh",
    description:
      "myTVS (TVS) LED headlight bulbs in Pune from an authorised dealer: LED9 Hyperbeam Ultra and LED8 in H4, H7 and more, fitted at our shop. WhatsApp us.",
    // Source: https://mytvs.com/products/mytvs-led-headlight-152w-200w ("myTVS LED9 Hyperbeam Ultra", "Plug-n-Play",
    // "IP68 waterproof rating", "aluminum body", variants by fitting and colour) and https://mytvs.com/collections/led.
    intro:
      "myTVS is TVS's parts and accessories brand, which is why most people simply search for a \"TVS LED headlight\". Its LED9 Hyperbeam Ultra bulbs come in a wide spread of fittings, from H4 and H7 to HB3, HB4 and H19, in 6000K cool white or 4300K natural white, and myTVS sells them as plug-and-play with an IP68 waterproof rating.",
    range: [
      // Source: https://mytvs.com/products/mytvs-led-headlight-152w-200w
      { name: "LED9 Hyperbeam Ultra", detail: "152W and 204W LED headlight bulbs in H1, H4, H7, H8, H18, H19, H27, HB3, HB4 and HB12, in 6000K cool white or 4300K natural white." },
      // Source: https://mytvs.com/products/h4-led-headlight-bulb and https://mytvs.com/products/h7-led-headlight-bulb
      { name: "LED8", detail: "152W LED headlight bulbs in H4 and H7." },
      // Source: https://mytvs.com/collections/led
      { name: "GL-3L, GL-2L and AU-2L", detail: "LED grill light and auxiliary lamps." },
      { name: "LH-130 relay wiring harness", detail: "Headlight relay wiring harness for lighting upgrades." },
    ],
    faqs: [
      // Source: https://mytvs.com/products/mytvs-led-headlight-152w-200w
      { q: "Which myTVS LED headlight bulb fits my car?", a: "myTVS makes its LED bulbs in H1, H4, H7, H8, H18, H19, H27, HB3, HB4 and HB12 fittings. Your car's bulb type decides which one fits, so send us your model, year and a photo of the headlights and we'll confirm." },
      { q: "Should I choose 6000K cool white or 4300K natural white?", a: "myTVS offers both. 6000K looks whiter and more modern; 4300K is a warmer white. Tell us how and where you drive and we'll help you choose." },
      { q: "Is a TVS LED headlight the same as myTVS?", a: "Yes. myTVS is TVS's parts and accessories brand, so a \"TVS LED headlight\" is the myTVS bulb we sell and fit." },
    ],
  },
  {
    slug: "moco-headlights-pune",
    category: "headlights",
    name: "Moco",
    alsoSearchedAs: ["MOCO"],
    logo: "moco",
    url: "https://moco.co.in/",
    note: "Moco 72W–300W LED bulbs, D-Series LEDs and Lazer Eye fog projectors.",
    title: "Moco LED Headlights Pune – Authorised Dealer, Fitted | Jay Ganesh",
    description:
      "Moco LED headlight bulbs (72W to 300W), D-Series LEDs and Lazer Eye fog projectors in Pune from an authorised dealer. WhatsApp your car model for a price.",
    // Source: https://moco.co.in/pages/about-us (Abbtron Technologies Pvt. Ltd., "car infotainment, safety, and security
    // products under the brands MOCO …") and https://moco.co.in/collections/led-headlight-bulbs,
    // https://moco.co.in/collections/led-projector-fog-lamps (product names below).
    intro:
      "Moco is a brand of Abbtron Technologies Pvt. Ltd., which also sells car infotainment, safety and security products under the MOCO name. Its lighting range is built around LED headlight bulbs from 72W to 300W, in hi/low-beam (H4/H19) and single-beam versions, plus D-Series LEDs and Lazer Eye projector fog lamps.",
    range: [
      // Source: https://moco.co.in/collections/led-headlight-bulbs
      { name: "72W, 170W, 200W, 240W and 300W LED lights", detail: "LED headlight bulbs in Hi/Low Beam (H4/H19) and Single Beam versions." },
      { name: "120W D-Series LED Lights", detail: "For D1S, D2S, D3S, D4S, D5S and D8S fittings." },
      // Source: https://moco.co.in/collections/led-projector-fog-lamps
      { name: "Lazer Eye fog lamp projectors", detail: "2-inch and 3-inch Lazer Eye projectors, and a 3-inch Dual Lazer Lens version." },
      { name: "3.0\" Tri Color fog lamp projector", detail: "Switches between 4300K, 5000K and 6000K, with a remote." },
      { name: "180W 3 Lens Dual Lamp", detail: "Hi/low-beam auxiliary lamp projector." },
    ],
    faqs: [
      // Source: https://moco.co.in/collections/led-headlight-bulbs
      { q: "Which Moco LED headlight bulb should I choose?", a: "Moco makes 72W, 170W, 200W, 240W and 300W LED lights, as Hi/Low Beam (H4/H19) or Single Beam. Your bulb type and how you drive decide the best fit; send us your model and a photo of the headlights and we'll help you choose." },
      // Source: https://moco.co.in/collections/led-projector-fog-lamps
      { q: "Do you fit Moco Lazer Eye fog lamp projectors?", a: "Yes. Moco's Lazer Eye projectors come in 2-inch and 3-inch sizes, and there's a 3-inch Tri Color projector that switches between three colours. We'll check your bumper's fog lamp housing first." },
      { q: "Do you fit Moco LEDs on cars with D-series bulbs?", a: "Moco makes a 120W D-Series LED for D1S, D2S, D3S, D4S, D5S and D8S fittings. Send us your car model and a photo of the bulb and we'll confirm it fits." },
    ],
  },
];

export const dealerBrandsIn = (category: DealerCategory) => dealerBrands.filter((b) => b.category === category);

export const getDealerBrand = (slug: string) => dealerBrands.find((b) => b.slug === slug);

export const dealerBrandPath = (b: DealerBrand) => `/${b.slug}/`;

/** "Garware PPF", "DHC headlights". */
export const dealerProductName = (b: DealerBrand) => `${b.name} ${dealerCategories[b.category].product}`;

/** All brand names (deduplicated) for a category, e.g. for prose and JSON-LD. */
export const dealerBrandNames = (category?: DealerCategory) => [
  ...new Set((category ? dealerBrandsIn(category) : dealerBrands).map((b) => b.name)),
];
