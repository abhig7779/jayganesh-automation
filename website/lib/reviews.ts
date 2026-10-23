// Google rating, review count and review text for the site.
// lib/reviews.json is refreshed every day by .github/workflows/site-reviews.yml
// (scripts/fetch-reviews.mjs), so never hard-code the rating or the count anywhere.
import raw from "./reviews.json";

export type Review = {
  id: string;
  name: string;
  stars: number;
  text: string;
  /** Google's relative label at the time it was fetched, e.g. "3 months ago". */
  when: string;
  /** ISO timestamp when Google gave one. */
  publishTime: string | null;
  /**
   * YYYY-MM-DD estimate for reviews without publishTime, derived once from `when`
   * and saved so the label keeps ageing correctly between refreshes.
   */
  approxTime?: string;
  /** Google's review-topic chips this review appeared under (from the one-time import). */
  topics?: string[];
  /** Reviewer's Google profile picture (shown on their own review card). */
  photo?: string;
};

export type ReviewsData = {
  rating: number;
  count: number;
  /** YYYY-MM-DD of the last successful refresh. */
  updatedAt: string;
  source: string;
  reviews: Review[];
};

export const reviewsData: ReviewsData = raw as ReviewsData;

/** "4.9" */
export const ratingText: string = reviewsData.rating.toFixed(1);

/** Indian digit grouping: 727 → "727", 1234 → "1,234", 123456 → "1,23,456". */
function groupIndian(n: number): string {
  const s = String(Math.round(Math.abs(n)));
  if (s.length <= 3) return s;
  const last3 = s.slice(-3);
  const rest = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ",");
  return `${rest},${last3}`;
}

/** Rounded-down review count for display, e.g. 737 → "700+", 760 → "750+".
 *  The exact count only goes into JSON-LD (aggregateRating), where Google
 *  requires the real number. */
export const countText: string = `${groupIndian(Math.floor(reviewsData.count / 50) * 50)}+`;

function titleCase(word: string): string {
  return word
    .split(/([-'’])/)
    .map((p) => (p.length ? p.charAt(0).toUpperCase() + p.slice(1).toLowerCase() : p))
    .join("");
}

/** "Sarthak Chatle" → "Sarthak C.", "Prajwal" → "Prajwal", "ARNAV GIRME" → "Arnav G." */
export function shortName(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return "Google user";
  const isAllCaps = (w: string) => /[A-Z]/.test(w) && w === w.toUpperCase();
  const norm = (w: string) => (isAllCaps(w) && w.length > 1 ? titleCase(w) : w);
  const first = norm(parts[0]);
  if (parts.length === 1) return first;
  const lastInitial = parts[parts.length - 1].replace(/[^\p{L}]/gu, "").charAt(0).toUpperCase();
  return lastInitial ? `${first} ${lastInitial}.` : first;
}

const DAY = 86_400_000;

/** Rough age in days from Google's relative label ("a month ago", "3 weeks ago"). */
function relativeToDays(when: string): number | null {
  const m = when.toLowerCase().match(/(a|an|\d+)\s+(minute|hour|day|week|month|year)s?\s+ago/);
  if (!m) return null;
  const n = m[1] === "a" || m[1] === "an" ? 1 : Number(m[1]);
  const unit = { minute: 1 / 1440, hour: 1 / 24, day: 1, week: 7, month: 30.44, year: 365.25 }[m[2] as "day"];
  return n * unit;
}

/** Best-known publish date: Google's timestamp, else the saved estimate, else from the label. */
function estimatedTime(r: Review): number {
  for (const iso of [r.publishTime, r.approxTime && `${r.approxTime}T12:00:00Z`]) {
    if (!iso) continue;
    const t = Date.parse(iso);
    if (!Number.isNaN(t)) return t;
  }
  const days = relativeToDays(r.when);
  const base = Date.parse(`${reviewsData.updatedAt}T12:00:00Z`);
  if (days == null || Number.isNaN(base)) return 0;
  return base - days * DAY;
}

/**
 * Relative label as of the last refresh (updatedAt). Deterministic, so server and
 * client render the same text; the daily job keeps updatedAt current.
 */
function relativeLabel(r: Review, time: number): string {
  const base = Date.parse(`${reviewsData.updatedAt}T12:00:00Z`);
  if (!(r.publishTime || r.approxTime) || !time || Number.isNaN(base)) return r.when;
  const days = Math.max(0, (base - time) / DAY);
  if (days < 1) return "today";
  if (days < 2) return "yesterday";
  if (days < 7) return `${Math.floor(days)} days ago`;
  if (days < 30) {
    const w = Math.floor(days / 7);
    return w === 1 ? "a week ago" : `${w} weeks ago`;
  }
  if (days < 365) {
    const mo = Math.max(1, Math.round(days / 30.44));
    if (mo >= 12) return "a year ago";
    return mo === 1 ? "a month ago" : `${mo} months ago`;
  }
  const y = Math.floor(days / 365.25);
  return y <= 1 ? "a year ago" : `${y} years ago`;
}

export type VisibleReview = Review & { shortName: string; relative: string };

/** 4–5★ reviews with text, newest first. */
export const visibleReviews: VisibleReview[] = reviewsData.reviews
  .filter((r) => r.stars >= 4 && r.text && r.text.trim().length > 0)
  .map((r, i) => ({ r, i, t: estimatedTime(r) }))
  .sort((a, b) => b.t - a.t || a.i - b.i)
  .map(({ r, t }) => ({ ...r, text: r.text.trim(), shortName: shortName(r.name), relative: relativeLabel(r, t) }));

/* ---------- Topics: which reviews belong on which page ---------- */

export type Topic =
  | "seat-covers" | "autoform" | "lighting" | "ambient" | "audio" | "camera"
  | "dashcam" | "ppf" | "coating" | "detailing" | "pricing";

// Google's own topic chips, mapped to ours.
const googleTopic: Record<string, Topic[]> = {
  "seat cover": ["seat-covers"],
  "custom seat cover shop": ["seat-covers"],
  "autoform seat cover": ["autoform", "seat-covers"],
  "projector light": ["lighting"],
  "led lights": ["lighting"],
  "car lighting work": ["lighting"],
  "brake light": ["lighting"],
  "car audio system": ["audio"],
  "dash cam": ["dashcam"],
  "fair pricing": ["pricing"],
};

// Words in the review itself, so new reviews from the daily refresh get tagged too.
const keywordTopic: [Topic, RegExp][] = [
  ["seat-covers", /seat\s*-?\s*covers?/i],
  ["autoform", /auto\s*form/i],
  ["lighting", /head\s*-?\s*lights?|projector|\bled\b|\bhid\b|fog\s*lamp|brake\s*light|\blights?\b/i],
  ["ambient", /ambient/i],
  ["audio", /audio|speaker|stereo|music|android|blaupunkt|amplifier|sub\s*woofer|sound\s*system/i],
  ["camera", /360/i],
  ["dashcam", /dash\s*-?\s*cam/i],
  ["ppf", /\bppf\b|paint\s+protection/i],
  ["coating", /ceramic|graphene|borophene|coating/i],
  ["detailing", /detailing|polish|foam\s*wash|interior\s+clean/i],
  ["pricing", /fair\s+pric|reasonabl|value\s+for\s+money|worth/i],
];

export function topicsOf(r: Review): Set<Topic> {
  const t = new Set<Topic>();
  for (const g of r.topics ?? []) for (const x of googleTopic[g] ?? []) t.add(x);
  for (const [topic, re] of keywordTopic) if (re.test(r.text)) t.add(topic);
  return t;
}

/** Newest-first reviews matching any of the topics. */
export function reviewsFor(topics: Topic[]): VisibleReview[] {
  return visibleReviews.filter((r) => {
    const t = topicsOf(r);
    return topics.some((x) => t.has(x));
  });
}
