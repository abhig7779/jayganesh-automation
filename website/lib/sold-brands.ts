// Brands the shop SELLS AND FITS but is NOT confirmed as an authorised dealer
// for (owner's list, 24 Oct 2026): car speakers and window (sun-control) film.
// Always worded "brands we sell and fit", never "authorised dealer".
// (Authorised-dealer brands live in lib/dealer-brands.ts, plus Autoform.)
//
// FACTS RULE: names only, no specs, prices or warranty claims. Logos come from
// lib/brands.ts (official files, source URL recorded there); `logo` omitted =
// neutral text wordmark chip.

export type SoldBrand = {
  /** Brand name as the brand writes it (or as the owner wrote it, when unverified). */
  name: string;
  /** lib/brands.ts slug of an official logo; omitted = text wordmark chip. */
  logo?: string;
  /** Brand's official site, where one was found. */
  url?: string;
};

export type SoldCategory = "speakers" | "window-film";

export const soldCategories: Record<SoldCategory, { label: string; path: string; product: string }> = {
  speakers: { label: "Speakers", path: "/audio-screens/speakers/", product: "speakers" },
  "window-film": { label: "Window film", path: "/car-window-film-pune/", product: "window film" },
};

export const speakerBrands: SoldBrand[] = [
  { name: "Sony", logo: "sony", url: "https://www.sony.net/" },
  { name: "Pioneer", logo: "pioneer", url: "https://pioneer-india.in/" },
  { name: "Alpine", logo: "alpine", url: "https://www.alpine-usa.com/" },
  // morelhifi.com has no logo file (see lib/brands.ts): text chip, as elsewhere on the site.
  { name: "Morel", url: "https://www.morelhifi.com/" },
  { name: "Audison", logo: "audison", url: "https://audison.com/" },
  // infinityspeakers.com and harmanaudio.in only return a bot check: text chip.
  { name: "Infinity" },
  // Owner wrote "Basofex". No car-audio brand of that exact name was found
  // (Oct 2026); the nearest match, the UK brand Bassface, could not be tied to
  // the shop with confidence, so it is shown as the owner typed it.
  { name: "Basofex" },
  { name: "Rockford Fosgate", logo: "rockford-fosgate", url: "https://rockfordfosgate.com/" },
];

// Each brand's own site confirms it makes car window / sun-control film (checked Oct 2026):
export const windowFilmBrands: SoldBrand[] = [
  // https://www.garwarehitechfilms.com/sun-control-films ("Garware sun control films for cars & windows")
  { name: "Garware", logo: "garware-hi-tech-films", url: "https://www.garwarehitechfilms.com/sun-control-films" },
  // https://www.3mindia.in/3M/en_IN/p/c/films-sheeting/window/i/automotive/ ("3M Window Films for Automotive")
  { name: "3M", logo: "3m", url: "https://www.3mindia.in/3M/en_IN/p/c/films-sheeting/window/i/automotive/" },
  // https://www.llumar.com/automotive-film (automotive window tint). Owner wrote "Lumar".
  { name: "LLumar", logo: "llumar", url: "https://www.llumar.com/automotive-film" },
  // https://dhcautosolutions.com/ (home page post: "DHC Sun Protection Films … Nano Ceramic technology")
  { name: "DHC", logo: "dhc", url: "https://dhcautosolutions.com/" },
  // Owner wrote "Iccool". Not identified with confidence (candidates: Garware's
  // "Ice Cool" film line, or icoolfilms.com), so shown as the owner typed it.
  { name: "Iccool" },
];

export const soldBrandsIn = (c: SoldCategory) => (c === "speakers" ? speakerBrands : windowFilmBrands);
