// Brand logos shown on the site (app/components/BrandLogos.tsx).
//
// Every file in public/brands/ was taken from the brand's own website (see
// `source`), never from Wikipedia, logo aggregators or image search. Brands the
// shop has fitted but with no official logo file found (DHC, Alphatron,
// Innavi, Morel) are not listed here, so they keep their text chip.
//
// Autoform is the only brand the shop is an authorised dealer for. Every other
// brand here is "brands we've fitted include …" only (see lib/products.ts).

export type Brand = {
  slug: string;
  /** Name as used in lib/products.ts `brands` arrays. */
  name: string;
  /** Logo for the light theme (or both themes when there's no logoDark). */
  logo: string;
  /** Optional variant for the dark theme, from the same official site. */
  logoDark?: string;
  /** Intrinsic size of `logo`, for width/height attributes (avoids layout shift). */
  width: number;
  height: number;
  /**
   * Ink colour of `logo`, so BrandLogos can keep it readable on both themes:
   * "dark"  = dark or deep-coloured ink on transparent (gets a light pill in dark theme)
   * "light" = white ink on transparent (gets a dark pill in light theme)
   * "white-mono" = single-colour white mark (shown inverted to black in light theme)
   * "color" = reads on both
   */
  ink: "dark" | "light" | "white-mono" | "color";
  /** Emblem without a wordmark: BrandLogos prints the name beside it, as the brand's own site header does. */
  emblemOnly?: boolean;
  /** Brand's official site. */
  url: string;
  /** Exact official URL the logo file was downloaded from. */
  source: string;
  note?: string;
};

export const brands: Brand[] = [
  {
    slug: "autoform",
    name: "Autoform",
    logo: "/brands/autoform.png",
    width: 800,
    height: 181,
    ink: "white-mono",
    url: "https://www.autoformindia.com/",
    source: "https://autoform-image.s3.ap-south-1.amazonaws.com/Autoform-_-New-Logo+(3).png",
    note: "Footer logo of autoformindia.com (FOOTER_LOGO_URL in the site's footer component). Downscaled from 5953px wide.",
  },
  {
    slug: "garware-hi-tech-films",
    name: "Garware Hi-Tech Films",
    logo: "/brands/garware-hi-tech-films.png",
    width: 198,
    height: 48,
    ink: "light",
    url: "https://www.garwarehitechfilms.com/",
    source: "https://www.garwarehitechfilms.com/front_assets/home/image/logo-white.png",
    note: "Header logo and schema.org logo of garwarehitechfilms.com. Only 198px wide; fine at ~30px tall.",
  },
  {
    slug: "blaupunkt",
    name: "Blaupunkt",
    logo: "/brands/blaupunkt.svg",
    logoDark: "/brands/blaupunkt-white.svg",
    width: 261,
    height: 29,
    ink: "color",
    url: "https://www.blaupunkt.com/en-in/",
    source: "https://www.blaupunkt.com/build/theme/blaupunkt/assets/footer-logo-MmFgbMXy.svg",
    note: "Dark variant: https://www.blaupunkt.com/build/theme/blaupunkt/assets/white-logo-CXNxhzHO.svg",
  },
  {
    slug: "70mai",
    name: "70mai",
    logo: "/brands/70mai.svg",
    width: 300,
    height: 80,
    ink: "dark",
    url: "https://www.70mai.com/in/",
    source: "https://www.70mai.com/in/",
    note: "Inline header <svg> on 70mai.com/in, saved as a file with framework attributes removed.",
  },
  {
    slug: "aozoom",
    name: "Aozoom",
    logo: "/brands/aozoom.png",
    width: 300,
    height: 90,
    ink: "color",
    url: "https://www.aozoomled.com/",
    source: "https://www.aozoomled.com/uploads/46670/logo.png",
    note: "Header logo of Guangzhou Aozoom Auto Lighting Co., Ltd. (served as WebP, converted to PNG).",
  },
  {
    slug: "moto-ranger",
    name: "Moto Ranger",
    logo: "/brands/moto-ranger.png",
    width: 283,
    height: 300,
    ink: "dark",
    emblemOnly: true,
    url: "https://www.motoranger.in/",
    source: "https://www.motoranger.in/images/logotransparent.png",
    note: "Header emblem; the site sets the MOTORANGER wordmark as text beside it. Transparent padding trimmed.",
  },
];

const byName = new Map(brands.map((b) => [b.name.toLowerCase(), b]));

/** Brand with a logo for a name used in lib/products.ts, or undefined (keep a text chip). */
export const getBrand = (name: string): Brand | undefined => byName.get(name.toLowerCase());

export const getBrandBySlug = (slug: string): Brand | undefined => brands.find((b) => b.slug === slug);
