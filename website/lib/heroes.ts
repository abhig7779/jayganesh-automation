import fs from "node:fs";
import path from "node:path";
import localSizes from "./image-sizes.json";

// Inner-page hero photos (server only: reads public/ at build time).
//
// Each page has its own art-directed pair in /public/images/hero/:
//   {key}-desktop.jpg (16:9, subject in the right 60%, calm dark space on the left)
//     + {key}-desktop-{480,800,1200,1600}.webp
//   {key}-mobile.jpg  (4:5, subject in the top 60%)
//     + {key}-mobile-{480,800,1024}.webp
// Until a page's files exist, it falls back to the studio renders used by the
// home hero, so a missing photo never breaks the build or the page.

export const heroImageKeys = [
  "ppf",
  "ceramic",
  "detailing",
  "new-car-protection",
  "detailing-studio",
  "accessories",
  "seat-covers",
  "autoform",
  "headlights",
  "ambient-lighting",
  "android-stereo",
  "360-camera",
  "dashcams",
  "car-lights",
  "audio-screens",
] as const;

export type HeroImageKey = (typeof heroImageKeys)[number];

export const heroAlt: Record<HeroImageKey, string> = {
  ppf: "A technician in a yellow-sleeved uniform applies paint protection film to a black SUV's bonnet in the studio",
  ceramic: "A technician polishes a red SUV before ceramic coating",
  detailing: "Machine polishing a dark blue sedan in the studio",
  "new-car-protection": "A technician works on a new white SUV in the studio",
  "detailing-studio": "A black SUV in the light tunnel of the Jay Ganesh detailing studio",
  accessories: "An SUV cabin with accessories fitted at the Jay Ganesh shop",
  "seat-covers": "Tan-and-black quilted seat covers fitted in a car",
  autoform: "Autoform seat covers in a black SUV with the door open",
  headlights: "LED headlights glowing on a black SUV",
  "ambient-lighting": "Blue ambient lighting glowing in a car's cabin",
  "android-stereo": "An Android touchscreen stereo fitted in a car's dashboard",
  "360-camera": "A 360° camera view on a car's dashboard screen",
  dashcams: "A dashcam fitted behind a car's rear-view mirror",
  "car-lights": "An LED tail-light bar glowing on a black SUV",
  "audio-screens": "A car door speaker and a touchscreen stereo",
};

/** Service slugs (lib/services) → hero photo. */
export const serviceHero: Record<string, HeroImageKey> = {
  "ppf-pune": "ppf",
  "ceramic-coating-pune": "ceramic",
  "car-detailing-pune": "detailing",
  "new-car-protection": "new-car-protection",
};

export function isHeroImageKey(value: string): value is HeroImageKey {
  return (heroImageKeys as readonly string[]).includes(value);
}

export type HeroSources = {
  alt: string;
  /** 16:9 desktop photo (≥768px). */
  desktop: { jpg: string; jpgSrcSet?: string; webp?: string; width: number; height: number };
  /** 4:5 phone photo (<768px). */
  mobile: { jpg: string; jpgSrcSet?: string; webp?: string; width: number; height: number };
  /** True when either size still shows the studio render. */
  fallback: boolean;
};

const exists = (publicPath: string) => fs.existsSync(path.join(process.cwd(), "public", publicPath));

/** "480w"-style srcset of the WebP widths that actually exist for a JPEG. */
function webpSet(jpg: string, widths: number[]) {
  const found = widths.filter((w) => exists(jpg.replace(/\.jpg$/, `-${w}.webp`)));
  return found.length ? found.map((w) => `${jpg.replace(/\.jpg$/, `-${w}.webp`)} ${w}w`).join(", ") : undefined;
}

type LocalImage = { w: number; h: number; jpg800: number | null; webp: number[] };
const sizes = localSizes as Record<string, LocalImage>;

const fallbackDesktop = "/images/gen/hero-desktop.jpg";
const fallbackMobile = "/images/gen/hero-mobile.jpg";

const studioAlt = "A black SUV under the light strips of the Jay Ganesh detailing studio";

const cache = new Map<HeroImageKey, HeroSources>();

/** The studio render, with its -800.jpg copy and WebP widths. */
function studio(src: string, webpWidths: number[], fallbackW: number, fallbackH: number) {
  const info = sizes[src];
  return {
    jpg: src,
    jpgSrcSet: info?.jpg800 ? `${src.replace(/\.jpg$/, "-800.jpg")} ${info.jpg800}w, ${src} ${info.w}w` : undefined,
    webp: webpSet(src, webpWidths),
    width: info?.w ?? fallbackW,
    height: info?.h ?? fallbackH,
  };
}

/**
 * Resolve a hero's files at build time. Desktop and phone are decided
 * separately: each uses the page's own photo when its file exists, else the
 * studio render (a phone with no portrait crop yet gets the wide photo).
 */
export function heroSources(key: HeroImageKey): HeroSources {
  const hit = cache.get(key);
  if (hit) return hit;

  const desktopJpg = `/images/hero/${key}-desktop.jpg`;
  const mobileJpg = `/images/hero/${key}-mobile.jpg`;
  const hasDesktop = exists(desktopJpg);
  const hasMobile = exists(mobileJpg);

  const desktop = hasDesktop
    ? { jpg: desktopJpg, webp: webpSet(desktopJpg, [480, 800, 1200, 1600]), width: 1600, height: 900 }
    : studio(fallbackDesktop, [480, 800, 1200, 1600], 1600, 900);
  const mobile = hasMobile
    ? { jpg: mobileJpg, webp: webpSet(mobileJpg, [480, 800, 1024]), width: 1024, height: 1280 }
    : hasDesktop
      ? { jpg: desktopJpg, webp: webpSet(desktopJpg, [480, 800, 1200]), width: 1600, height: 900 }
      : studio(fallbackMobile, [480, 800, 1200], 1280, 1600);

  // One <img>, one alt: describe the page's own photo whenever either size has it.
  const result: HeroSources = {
    alt: hasDesktop || hasMobile ? heroAlt[key] : studioAlt,
    desktop,
    mobile,
    fallback: !hasDesktop || !hasMobile,
  };
  cache.set(key, result);
  return result;
}
