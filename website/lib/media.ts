// Photos used on the site.
//
// STAND-INS: every `stock: true` image is a free Unsplash photo (Unsplash
// licence: free for commercial use, no permission needed) used until the shop's
// own photos are ready. Replace each one with a real photo of Jay Ganesh's
// studio, team and customers' cars before launch: put the file in
// /public/images/ and set `src` to "/images/<file>.jpg", `stock: false`.

export type Media = { src: string; alt: string; stock: boolean; position?: string };

const unsplash = (id: string) => `https://images.unsplash.com/photo-${id}`;

export const media = {
  hero: { src: unsplash("1567808291548-fc3ee04dbcf0"), alt: "A black car under the studio's long ceiling lights", stock: true, position: "50% 78%" },
  ppfApply: { src: unsplash("1632605157148-6313421c504b"), alt: "Film being laid on a car panel with a squeegee", stock: true },
  ppfEdge: { src: unsplash("1632605179016-7e21ad342b14"), alt: "Film edges being trimmed and taped", stock: true },
  beadingDark: { src: unsplash("1770700391793-22fc46041c2b"), alt: "Water beading on a coated dark blue bonnet", stock: true },
  beadingClose: { src: unsplash("1780586585338-c56fe47b49f4"), alt: "Water droplets on a coated grey panel", stock: true },
  polish: { src: unsplash("1708805282695-ef186db20192"), alt: "Machine polishing a dark car before coating", stock: true },
  polishBlue: { src: unsplash("1780558852670-3f49298ecc1f"), alt: "Polishing a blue bonnet with an orbital machine", stock: true },
  foam: { src: unsplash("1633014041037-f5446fb4ce99"), alt: "An SUV covered in snow foam inside the wash bay", stock: true },
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

/** A sized, compressed URL for a photo. Local files are returned as they are. */
export function imageUrl(src: string, width: number) {
  if (!src.startsWith("https://images.unsplash.com/")) return src;
  return `${src}?w=${width}&q=72&auto=format&fit=crop`;
}

export function srcSet(src: string, widths = [480, 800, 1200, 1800]) {
  if (!src.startsWith("https://images.unsplash.com/")) return undefined;
  return widths.map((w) => `${imageUrl(src, w)} ${w}w`).join(", ");
}
