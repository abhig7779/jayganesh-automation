// Real reels from @jayganeshcar_accessories_pune, shown in the homepage
// "Watch our work" section. Embed: https://www.instagram.com/reel/{id}/embed/
// The newest reels come from Instagram automatically (scripts/fetch-instagram-reels.mjs →
// lib/reels-instagram.json, refreshed daily); the hand-picked list below follows them.
import instagramReels from "./reels-instagram.json";

export type ReelCategory = "ppf" | "coating" | "interior" | "lights" | "accessories";

export type Reel = {
  id: string;
  title: string;
  car?: string;
  category: ReelCategory;
  /** Self-hosted MP4. When set, the reel plays natively (one tap, autoplay); otherwise the Instagram embed is used. */
  video?: string;
  /** Poster JPG shown on the card and before the video starts. */
  poster?: string;
};

/** Self-hosted reel files ship with the site: /reels/{id}.mp4 (540p, faststart) and /reels/{id}.jpg. */
export const reelBase = "/reels";
const own = (id: string) => ({ video: `${reelBase}/${id}.mp4`, poster: `${reelBase}/${id}.jpg` });

export const categoryLabel: Record<ReelCategory, string> = {
  ppf: "PPF",
  coating: "Coating",
  interior: "Seat covers & interior",
  lights: "Headlights & lights",
  accessories: "Screens & styling",
};

const picked: Reel[] = [
  { id: "DS4zOLXCQ8B", title: "Mahindra BE 6 · full-body PPF", car: "Mahindra BE 6", category: "ppf", ...own("DS4zOLXCQ8B") },
  { id: "DdRI9cMoCte", title: "XUV 7XO · Garware PPF", car: "Mahindra XUV 7XO", category: "ppf", ...own("DdRI9cMoCte") },
  { id: "DdL6ewnpxar", title: "XUV 7XO · PPF, fifth one this colour", car: "Mahindra XUV 7XO", category: "ppf", ...own("DdL6ewnpxar") },
  { id: "DcV5ctOqBmQ", title: "XUV 7XO · DHC PPF", car: "Mahindra XUV 7XO", category: "ppf", ...own("DcV5ctOqBmQ") },
  { id: "Db2-tNkNPMW", title: "Renault Duster · PPF", car: "Renault Duster", category: "ppf", ...own("Db2-tNkNPMW") },
  { id: "Dbnin_PKumo", title: "Kia Seltos · PPF", car: "Kia Seltos", category: "ppf", ...own("Dbnin_PKumo") },
  { id: "DbLRHoHK7WX", title: "Maruti Swift · DHC PPF", car: "Maruti Swift", category: "ppf", ...own("DbLRHoHK7WX") },
  { id: "DeO-ixKK_vV", title: "Mahindra Thar · graphene coating", car: "Mahindra Thar", category: "coating", ...own("DeO-ixKK_vV") },
  { id: "DeWqJomKc9U", title: "Mahindra Thar · graphene coating, part 2", car: "Mahindra Thar", category: "coating", ...own("DeWqJomKc9U") },
  { id: "DelJXxghV1r", title: "Ford Endeavour · Autoform seat covers", car: "Ford Endeavour", category: "interior", ...own("DelJXxghV1r") },
  { id: "Db2bzUAgUqO", title: "Renault Duster · PPF and interior change", car: "Renault Duster", category: "interior", ...own("Db2bzUAgUqO") },
];

type AutoReel = { id: string; title: string; car?: string; category: ReelCategory; video: boolean; timestamp: string };

// Newest Instagram reels first; a hand-picked entry for the same reel keeps its own title.
export const reels: Reel[] = [
  ...(instagramReels as AutoReel[]).map((r): Reel => {
    const own = picked.find((p) => p.id === r.id);
    return own ?? { id: r.id, title: r.title, car: r.car, category: r.category, poster: `${reelBase}/${r.id}.jpg`, ...(r.video ? { video: `${reelBase}/${r.id}.mp4` } : {}) };
  }),
  ...picked.filter((p) => !(instagramReels as AutoReel[]).some((r) => r.id === p.id)),
];

export const reelEmbedUrl = (id: string) => `https://www.instagram.com/reel/${id}/embed/`;
export const reelUrl = (id: string) => `https://www.instagram.com/reel/${id}/`;
