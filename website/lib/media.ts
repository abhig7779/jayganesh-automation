import localSizes from "./image-sizes.json";

// Photos used on the site.
//
// STAND-INS: every `stock: true` image is a free Unsplash photo (Unsplash
// licence: free for commercial use, no permission needed) used until the shop's
// own photos are ready. Replace each one with a real photo of Jay Ganesh's
// studio, team and customers' cars before launch: put the file in
// /public/images/ and set `src` to "/images/<file>.jpg", `stock: false`.

export type Media = { src: string; alt: string; stock: boolean; position?: string };

const unsplash = (id: string) => `https://images.unsplash.com/photo-${id}`;

// Studio renders: generated with Codex from reference photos of our real
// studio (light strips, tiled floor, red stripes). They show the look of the
// studio, not specific customer jobs; swap in real photos as they come.
const gen = (name: string) => `/images/gen/${name}.jpg`;

export const media = {
  hero: { src: unsplash("1567808291548-fc3ee04dbcf0"), alt: "A black car under the studio's long ceiling lights", stock: true, position: "50% 78%" },
  studioHero: { src: gen("hero-desktop"), alt: "A black SUV under the light strips of the Jay Ganesh detailing studio", stock: false, position: "65% 55%" },
  studioHeroMobile: { src: gen("hero-mobile"), alt: "A black SUV under the light strips of the Jay Ganesh detailing studio", stock: false },
  ppfApply: { src: gen("svc-ppf"), alt: "Paint protection film being laid on a black bonnet with a squeegee", stock: false },
  ppfEdge: { src: unsplash("1632605179016-7e21ad342b14"), alt: "Film edges being trimmed and taped", stock: true },
  beadingDark: { src: gen("svc-ceramic"), alt: "Water beading on a ceramic-coated maroon bonnet", stock: false },
  beadingClose: { src: unsplash("1780586585338-c56fe47b49f4"), alt: "Water droplets on a coated grey panel", stock: true },
  polish: { src: unsplash("1708805282695-ef186db20192"), alt: "Machine polishing a dark car before coating", stock: true },
  polishBlue: { src: unsplash("1780558852670-3f49298ecc1f"), alt: "Polishing a blue bonnet with an orbital machine", stock: true },
  foam: { src: gen("svc-detailing"), alt: "A white SUV covered in snow foam in the studio", stock: false },
  foamDark: { src: unsplash("1761312834150-4beefff097a7"), alt: "Foam pre-wash on a black car", stock: true },
  washBay: { src: unsplash("1520340356584-f9917d1eea6f"), alt: "Rinsing a car in the wash bay", stock: true },
  interior: { src: unsplash("1605437241278-c1806d14a4d9"), alt: "Black leather seats and dashboard", stock: true },
  dashClean: { src: unsplash("1771491237218-cbd4a707497e"), alt: "Detailing the dashboard with a soft brush", stock: true },
  headlight: { src: unsplash("1748847613527-7d0bba714fed"), alt: "LED headlight on a red car", stock: true },
  lightsDark: { src: unsplash("1554294314-80a5fb7e6bd5"), alt: "Headlights glowing on a black SUV", stock: true },
  newCar: { src: unsplash("1774578432950-66cedc94d446"), alt: "A new car parked in the studio", stock: true },
  // Hero before/after: until real photos of the same car before and after PPF
  // exist, one photo is shown twice, with the "before" side styled as dull,
  // swirled paint. Replace with two real photos: compareBefore and compareAfter.
  compareAfter: { src: unsplash("1508974239320-0a029497e820"), alt: "Glossy red paint after PPF and coating", stock: true },
  compareBefore: { src: unsplash("1508974239320-0a029497e820"), alt: "The same red paint before, dull and swirled", stock: true },
  studioBay: { src: unsplash("1617898528081-20d57598d40d"), alt: "A car in the wash and detailing bay", stock: true },
} satisfies Record<string, Media>;

export type MediaKey = keyof typeof media;

// Real pixel sizes of the local photos and the WebP widths made for them by
// scripts/optimize-images.py (re-run it after adding or replacing a photo).
// Note the "-800.jpg" copies of portrait renders are 640 wide, not 800.
type LocalImage = { w: number; h: number; jpg800: number | null; webp: number[] };
const localImages = localSizes as Record<string, LocalImage>;

const isUnsplash = (src: string) => src.startsWith("https://images.unsplash.com/");

/** A sized, compressed URL for a photo: Unsplash resizes on the fly; local files use the nearest JPEG. */
export function imageUrl(src: string, width: number) {
  if (isUnsplash(src)) return `${src}?w=${width}&q=72&auto=format&fit=crop`;
  const info = localImages[src];
  if (info?.jpg800 && width <= 800) return src.replace(/\.jpg$/, "-800.jpg");
  return src;
}

/** JPEG (local) or auto-format (Unsplash) srcset with accurate width descriptors. */
export function srcSet(src: string, widths = [480, 640, 800, 1200, 1600]) {
  const info = localImages[src];
  if (info) return info.jpg800 ? `${src.replace(/\.jpg$/, "-800.jpg")} ${info.jpg800}w, ${src} ${info.w}w` : undefined;
  if (!isUnsplash(src)) return undefined;
  return widths.map((w) => `${imageUrl(src, w)} ${w}w`).join(", ");
}

/** WebP srcset for a local photo (studio renders, reel posters), optionally capped at `maxWidth`. */
export function webpSrcSet(src: string, maxWidth = Infinity) {
  const info = localImages[src];
  if (!info) return undefined;
  const widths = info.webp.filter((w) => w <= maxWidth);
  return (widths.length ? widths : info.webp.slice(0, 1)).map((w) => `${src.replace(/\.jpg$/, `-${w}.webp`)} ${w}w`).join(", ");
}
