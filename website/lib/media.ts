import localSizes from "./image-sizes.json";

// Photos used on the site. Every photo is a local file (no stock photos).
//
//  - gen(...)   studio renders in /public/images/gen/, generated with Codex from
//               reference photos of our real studio (light strips, tiled floor,
//               red stripes). They show the look of the studio, not specific
//               customer jobs; swap in real photos as they come.
//  - hero(...)  the art-directed inner-page hero photos in /public/images/hero/.
//  - planned(...) purpose-made 4:5 renders (/public/images/gen/new-*.jpg,
//               1024x1280, + -480/-800/-1024.webp). Until a file and its WebP
//               copies exist, the photo falls back to a close local match, so the
//               build never breaks. Which files exist is decided at build time in
//               next.config.mjs (fs.existsSync) and passed in as JG_GEN_READY,
//               because this module is also bundled for the browser.

export type Media = { src: string; alt: string; stock: boolean; position?: string };

const gen = (name: string) => `/images/gen/${name}.jpg`;
const hero = (name: string) => `/images/hero/${name}.jpg`;

const ready = new Set((process.env.JG_GEN_READY ?? "").split(",").filter(Boolean));

/** A purpose-made render once its files exist, else the fallback photo and its alt. */
function planned(name: string, alt: string, fallback: string, fallbackAlt: string): Media {
  return ready.has(name) ? { src: gen(name), alt, stock: false } : { src: fallback, alt: fallbackAlt, stock: false };
}

export const media = {
  studioHero: { src: gen("hero-desktop"), alt: "A black SUV under the light strips of the Jay Ganesh detailing studio", stock: false, position: "65% 55%" },
  studioHeroMobile: { src: gen("hero-mobile"), alt: "A black SUV under the light strips of the Jay Ganesh detailing studio", stock: false },
  ppfApply: { src: gen("svc-ppf"), alt: "Paint protection film being laid on a black bonnet with a squeegee", stock: false },
  beadingDark: { src: gen("svc-ceramic"), alt: "Water beading on a ceramic-coated maroon bonnet", stock: false },
  foam: { src: gen("svc-detailing"), alt: "A white SUV covered in snow foam in the studio", stock: false },

  // Purpose-made renders (fallback in brackets until the file exists).
  hero: planned("new-studio-hero", "The entrance of the Jay Ganesh detailing studio", gen("hero-mobile"), "A black SUV under the light strips of the Jay Ganesh detailing studio"),
  polish: planned("new-detailing-polish", "Machine polishing a dark car in the studio before coating", hero("detailing-mobile"), "Machine polishing a dark blue sedan in the studio"),
  ppfEdge: planned("new-ppf-edge", "Paint protection film edges being trimmed and tucked", hero("ppf-mobile"), "A technician applies paint protection film to a black SUV's bonnet"),
  beadingClose: planned("new-beading", "Water droplets beading on a ceramic-coated panel", hero("ceramic-mobile"), "A technician polishes a red SUV before ceramic coating"),
  foamDark: planned("new-foam", "Snow foam pre-wash on a car in the studio", gen("svc-detailing"), "A white SUV covered in snow foam in the studio"),
  washBay: planned("new-washbay", "Rinsing a car in the studio wash bay", hero("detailing-studio-mobile"), "A black SUV in the light tunnel of the Jay Ganesh detailing studio"),
  interior: planned("new-seat-covers", "Custom-fitted seat covers in a car", hero("seat-covers-mobile"), "Tan-and-black quilted seat covers fitted in a car"),
  mats: planned("new-mats", "Fitted floor mats and interior trims in a car", hero("accessories-mobile"), "An SUV cabin with accessories fitted at the Jay Ganesh shop"),
  dashClean: planned("new-dash-clean", "Detailing a car's dashboard with a soft brush", hero("360-camera-mobile"), "A car's dashboard and screen in the studio"),
  ambient: planned("new-ambient", "Ambient lighting glowing in a car's cabin", hero("ambient-lighting-mobile"), "Blue ambient lighting glowing in a car's cabin"),
  stereo: planned("new-stereo", "An Android touchscreen stereo fitted in a dashboard", hero("android-stereo-mobile"), "An Android touchscreen stereo fitted in a car's dashboard"),
  audio: planned("new-audio", "Car speakers and audio upgrade fitted in a door", hero("audio-screens-mobile"), "A car door speaker and a touchscreen stereo"),
  dashcam: planned("new-dashcam", "A dashcam fitted behind a car's rear-view mirror", hero("dashcams-mobile"), "A dashcam fitted behind a car's rear-view mirror"),
  workMuvSeats: planned("new-work-muv-seats", "Custom seat covers fitted in an MUV, all three rows", hero("autoform-mobile"), "Seat covers in a black SUV with the door open"),
  workHatchInterior: planned("new-work-hatch-interior", "A hatchback interior after a deep clean", hero("accessories-mobile"), "A car cabin after interior work at the Jay Ganesh shop"),
  workSuvStereo: planned("new-work-suv-stereo", "An Android stereo and 360° camera fitted in an SUV", hero("android-stereo-mobile"), "An Android touchscreen stereo fitted in a car's dashboard"),

  // Real photos of the shop and studio (owner's photos, Oct 2026; customer
  // number plates blurred), in /public/images/gen/ so optimize-images.py sizes them.
  shopFront: { src: gen("shop-front-detailing-studio"), alt: "Jay Ganesh Car Accessories and Detailing Studio shop front on the Pune–Solapur road, Loni Kalbhor", stock: false },
  shopFrontHighway: { src: gen("shop-front-highway"), alt: "The orange Jay Ganesh Car Accessories sign at Kawadipat Toll Naka, with cars being fitted out front", stock: false },
  autoformDisplay: { src: gen("shop-autoform-display"), alt: "Autoform seat cover display and accessories shelves inside the Jay Ganesh shop", stock: false },
  studioBayReal: { src: gen("studio-bay-real"), alt: "A Mahindra BE 6 in the Jay Ganesh detailing studio under the light tunnel", stock: false },
  studioBayPortrait: { src: gen("studio-bay-real-portrait"), alt: "A Mahindra BE 6 facing out of the Jay Ganesh studio's light tunnel", stock: false },
  studioMatteBe6: { src: gen("studio-matte-be6"), alt: "A satin-black Mahindra BE 6 on the red and grey floor of the Jay Ganesh detailing studio", stock: false },

  // Inner-page hero photos reused as card photos.
  headlight: { src: hero("headlights-desktop"), alt: "LED headlights glowing on a black SUV", stock: false },
  lightsDark: { src: hero("car-lights-desktop"), alt: "An LED tail-light bar glowing on a black SUV", stock: false },
  newCar: { src: hero("new-car-protection-desktop"), alt: "A technician works on a new white SUV in the studio", stock: false },
  camera360: { src: hero("360-camera-desktop"), alt: "A 360° camera view on a car's dashboard screen", stock: false },
  studioBay: { src: hero("detailing-studio-desktop"), alt: "A black SUV in the light tunnel of the Jay Ganesh detailing studio", stock: false },
  // Before/after slider (HeroCompare, not on any page now): one photo shown twice,
  // the "before" side styled as dull paint. Replace with two real photos.
  compareAfter: { src: hero("ceramic-desktop"), alt: "Glossy paint after polishing and coating", stock: false },
  compareBefore: { src: hero("ceramic-desktop"), alt: "The same paint before, dull and swirled", stock: false },
} satisfies Record<string, Media>;

export type MediaKey = keyof typeof media;

// Real pixel sizes of the local photos and the WebP widths made for them by
// scripts/optimize-images.py (re-run it after adding or replacing a photo).
// Note the "-800.jpg" copies of portrait renders are 640 wide, not 800.
type LocalImage = { w: number; h: number; jpg800: number | null; webp: number[] };
const localImages = localSizes as Record<string, LocalImage>;

/** A sized, compressed URL for a local photo: the nearest JPEG. */
export function imageUrl(src: string, _width?: number) {
  const info = localImages[src];
  if (info?.jpg800 && (_width ?? 800) <= 800) return src.replace(/\.jpg$/, "-800.jpg");
  return src;
}

/** JPEG srcset with accurate width descriptors (only when a smaller copy exists). */
export function srcSet(src: string) {
  const info = localImages[src];
  if (info?.jpg800) return `${src.replace(/\.jpg$/, "-800.jpg")} ${info.jpg800}w, ${src} ${info.w}w`;
  return undefined;
}

/** WebP srcset for a local photo (studio renders, hero photos, reel posters), optionally capped at `maxWidth`. */
export function webpSrcSet(src: string, maxWidth = Infinity) {
  const info = localImages[src];
  if (!info) return undefined;
  const widths = info.webp.filter((w) => w <= maxWidth);
  return (widths.length ? widths : info.webp.slice(0, 1)).map((w) => `${src.replace(/\.jpg$/, `-${w}.webp`)} ${w}w`).join(", ");
}
