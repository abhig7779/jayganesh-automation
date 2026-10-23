import type { HeroImageKey } from "./heroes";
import type { Topic } from "./reviews";

// Brands the shop is an AUTHORISED DEALER for (owner-confirmed, Oct 2026), by
// category. Drives the brand landing pages (/garware-ppf-pune/ …), the two hubs
// (/ppf-brands-pune/, /headlight-brands-pune/), the "Authorised dealer"
// sections on /ppf-pune/, /car-lights/headlights/ and /car-lights/, the sitemap,
// llms.txt and the business JSON-LD.
//
// FACTS RULE: no prices, film specs, warranty years, model years or distances.
// `about` says only what the brand's own site shows (who makes it, what it
// sells). Logos live in lib/brands.ts (`logo` = Brand.slug); brands with no
// official logo file get a text wordmark chip.

export type DealerCategory = "ppf" | "headlights";

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
  /** One or two plain sentences about the brand, from its own site. */
  about?: string;
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
    note: "Paint protection film from Garware Hi-Tech Films.",
    about: "Garware PPF is made by Garware Hi-Tech Films, an Indian film maker that sells paint protection film under its own name.",
  },
  {
    slug: "3m-ppf-pune",
    category: "ppf",
    name: "3M",
    logo: "3m",
    url: "https://www.3mindia.in/",
    note: "3M paint protection film for cars.",
    about: "3M sells paint protection film for cars in India through 3M India.",
  },
  {
    slug: "llumar-ppf-pune",
    category: "ppf",
    name: "LLumar",
    alsoSearchedAs: ["Lumar", "Llumar"],
    logo: "llumar",
    url: "https://llumar.com/en/",
    note: "LLumar paint protection film, from Eastman.",
    about: "LLumar is the window film and paint protection film brand of Eastman.",
  },
  {
    slug: "ultraguard-ppf-pune",
    category: "ppf",
    name: "Ultraguard",
    alsoSearchedAs: ["Aultragard", "Ultragard", "Ultra Guard"],
    logo: "ultraguard",
    url: "https://www.ultraguardindia.com/",
    note: "Ultraguard clear, matte and colour PPF.",
    about: "Ultraguard is a paint protection film brand in India, with clear, matte, black and colour films on its range.",
  },
  {
    slug: "dhc-ppf-pune",
    category: "ppf",
    name: "DHC",
    logo: "dhc",
    url: "https://dhcautosolutions.com/",
    note: "DHC paint protection film, from DHC Autosolutions.",
    about: "DHC Autosolutions is an Indian car accessories brand that added its own paint protection film to its range.",
  },
  {
    slug: "night-scene-ppf-pune",
    category: "ppf",
    name: "Night Scene",
    alsoSearchedAs: ["Nightscene"],
    note: "Night Scene paint protection film.",
  },
  {
    slug: "tvs-ppf-pune",
    category: "ppf",
    name: "myTVS",
    alsoSearchedAs: ["TVS", "My TVS"],
    logo: "mytvs",
    url: "https://mytvs.com/",
    note: "Paint protection film from myTVS, TVS's parts and accessories brand.",
    about: "myTVS is the parts and accessories brand from TVS, and paint protection film is part of its car range.",
  },

  /* ---------- Headlights ---------- */
  {
    slug: "aozoom-headlights-pune",
    category: "headlights",
    name: "Aozoom",
    logo: "aozoom",
    url: "https://www.aozoomled.com/",
    note: "Aozoom LED and projector lighting.",
    about: "Aozoom is made by Guangzhou Aozoom Auto Lighting Co., Ltd., a car lighting maker.",
  },
  {
    slug: "motoranger-headlights-pune",
    category: "headlights",
    name: "Moto Ranger",
    alsoSearchedAs: ["Motoranger"],
    logo: "moto-ranger",
    url: "https://www.motoranger.in/",
    note: "Moto Ranger LED headlights and auxiliary lights.",
    about: "Moto Ranger is a vehicle lighting brand with its own site at motoranger.in.",
  },
  {
    slug: "dhc-headlights-pune",
    category: "headlights",
    name: "DHC",
    logo: "dhc",
    url: "https://dhcautosolutions.com/",
    note: "DHC LED headlamps and headlight bulbs.",
    about: "DHC Autosolutions is an Indian car accessories brand that sells LED headlamps and LED headlight bulbs.",
  },
  {
    slug: "maxxlink-headlights-pune",
    category: "headlights",
    name: "Maxxlink",
    alsoSearchedAs: ["Maxlink", "Max Link"],
    note: "Maxxlink LED headlights and projector lights.",
  },
  {
    slug: "lumax-headlights-pune",
    category: "headlights",
    name: "Lumax",
    logo: "lumax",
    url: "https://lumaxestore.com/",
    note: "Lumax car lighting, from the Lumax DK Jain Group.",
    about: "Lumax is part of the Lumax DK Jain Group, an Indian automotive lighting and components group.",
  },
  {
    slug: "tvs-headlights-pune",
    category: "headlights",
    name: "myTVS",
    alsoSearchedAs: ["TVS", "My TVS"],
    logo: "mytvs",
    url: "https://mytvs.com/",
    note: "myTVS LED headlight bulbs, from TVS's accessories brand.",
    about: "myTVS is the parts and accessories brand from TVS, and LED headlight bulbs are part of its car range.",
  },
  {
    slug: "moco-headlights-pune",
    category: "headlights",
    name: "Moco",
    alsoSearchedAs: ["MOCO"],
    note: "Moco LED headlight bulbs.",
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
