// Offline checks for content/calendar.json and config/*.json. No tokens needed.
//   npm run validate            → errors fail, missing values are listed as warnings
//   npm run validate -- --strict → missing values fail too
import { loadCalendar, loadVars, dayKey } from "./lib/config.mjs";
import { renderPost, countHashtags } from "./lib/render.mjs";
import { scheduledAt } from "./lib/schedule.mjs";

const strict = process.argv.includes("--strict");
const { posts } = loadCalendar();
const vars = loadVars();
const errors = [];
const missingBy = new Map();
const TYPES = new Set(["reel", "image", "carousel"]);
const PLATFORMS = new Set(["instagram", "facebook", "youtube"]);

const days = new Set();
for (const p of posts) {
  const k = dayKey(p.day);
  const err = (m) => errors.push(`${k}: ${m}`);
  if (days.has(p.day)) err("duplicate day number");
  days.add(p.day);
  if (!TYPES.has(p.type)) err(`type must be reel, image or carousel (got "${p.type}")`);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(p.date) || !/^\d{2}:\d{2}$/.test(p.time) || isNaN(scheduledAt(p))) err("date must be YYYY-MM-DD and time HH:MM (IST)");
  for (const pl of p.platforms) if (!PLATFORMS.has(pl)) err(`unknown platform "${pl}"`);
  if (p.platforms.includes("youtube") && p.type !== "reel") err("YouTube needs a video (type reel)");
  if (p.platforms.includes("youtube") && !p.youtube?.title) err("YouTube title missing");
  if ((p.collaborators || []).length > 3) err("Instagram allows at most 3 collaborators");

  const r = renderPost(p, vars);
  r.missing.forEach((m) => missingBy.set(m, [...(missingBy.get(m) || []), p.day]));
  if (r.caption.length > 2200) err(`caption is ${r.caption.length} characters (Instagram max 2,200)`);
  if (countHashtags(r.caption) > 30) err(`caption has ${countHashtags(r.caption)} hashtags (Instagram max 30)`);
  if (r.yt_title && r.yt_title.length > 100) err(`YouTube title is ${r.yt_title.length} characters (max 100)`);
  if (r.yt_title && /[<>]/.test(r.yt_title)) err("YouTube title cannot contain < or >");
}

console.log(`Checked ${posts.length} posts (${posts[0]?.date} → ${posts.at(-1)?.date}).`);
console.log(`Approved: ${posts.filter((p) => p.approved).length}/${posts.length}`);
if (missingBy.size) {
  console.log("\nValues still to fill in (config/brand.json, config/offers.json or the post's \"vars\"):");
  for (const [k, ds] of [...missingBy].sort()) console.log(`  ${k.padEnd(24)} used on day ${ds.join(", ")}`);
}
if (errors.length) {
  console.log("\nErrors:");
  errors.forEach((e) => console.log("  ✗ " + e));
}
const fail = errors.length || (strict && missingBy.size);
console.log(fail ? "\n✗ Not ready." : "\n✓ Calendar is valid.");
process.exit(fail ? 1 : 0);
