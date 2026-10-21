// Creates this calendar's folders in the shared Google Drive folder (safe to run again: existing
// folders are reused, nothing is deleted):
//   <root>/2026-10-26 → 2026-11-24 · Month 1/
//     Day 01 · 26 Oct (Mon) · Carousel · Abhi/
//       1 Raw/                              raw clips/photos for editing (never posted)
//       2 Edited (posts automatically)/     final .mp4 or .jpg — copying it here approves the post
//       Brief                               what to make for this day
// Writes the links to content/drive-folders.json (used by the Excel calendar).
//   node --env-file=.env src/make-drive-folders.mjs [--dry-run]
import { writeFileSync } from "node:fs";
import path from "node:path";
import { ROOT, loadCalendar } from "./lib/config.mjs";
import { drive, FOLDER } from "./lib/drive.mjs";

const e = process.env;
const dryRun = process.argv.includes("--dry-run");
const g = drive({
  clientId: e.GBP_CLIENT_ID || e.GOOGLE_CLIENT_ID,
  clientSecret: e.GBP_CLIENT_SECRET || e.GOOGLE_CLIENT_SECRET,
  refreshToken: e.DRIVE_REFRESH_TOKEN || e.GBP_REFRESH_TOKEN,
});
const rootId = e.DRIVE_FOLDER_ID;
if (!rootId) throw new Error("DRIVE_FOLDER_ID is not set");

const { posts, period } = loadCalendar();
// Prafull edits the music reels; Abhi makes everything else.
const ownerOf = (p) => (p.format === "Music-edit reel" ? "Prafull" : "Abhi");
const fmtDay = (iso) => {
  const d = new Date(`${iso}T12:00:00+05:30`);
  return `${d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", timeZone: "Asia/Kolkata" })} (${d.toLocaleDateString("en-GB", { weekday: "short", timeZone: "Asia/Kolkata" })})`;
};
const monthName = `${posts[0].date} → ${posts.at(-1).date} · Month 1`;
const RAW = "1 Raw";
const EDITED = "2 Edited (posts automatically)";

async function ensureFolder(name, parentId) {
  const found = String(parentId).startsWith("dry-") ? null : (await g.children(parentId)).find((f) => f.mimeType === FOLDER && f.name === name);
  if (found) return { ...found, url: `https://drive.google.com/drive/folders/${found.id}`, existed: true };
  if (dryRun) return { id: `dry-${name}`, name, url: "(dry run)", existed: false };
  const made = await g.createFolder(name, parentId);
  return { ...made, url: `https://drive.google.com/drive/folders/${made.id}`, existed: false };
}

function brief(p, owner) {
  const kind =
    p.type === "reel"
      ? "One vertical video, 9:16 (1080×1920), .mp4, under 90 s. Optional cover image named cover.jpg."
      : p.type === "carousel"
        ? "2–10 images, .jpg, 1080×1350, named in order (1.jpg, 2.jpg …)."
        : "One image, .jpg, 1080×1350.";
  return [
    `Day ${p.day} · ${fmtDay(p.date)} · posts at ${p.time} IST`,
    `Owner: ${owner}`,
    `Format: ${p.format} (${p.business})`,
    `Post: ${p.title}`,
    p.occasion ? `Occasion: ${p.occasion}` : "",
    "",
    `Opening line of the caption: ${p.caption.split("\n").find((l) => l.trim()) || ""}`,
    "",
    "How to deliver:",
    `• Put raw clips or photos in "${RAW}".`,
    `• Put the FINAL file(s) only in "${EDITED}". ${kind}`,
    "• Anything in the Edited folder is approved and posts automatically at the time above, so add it only when it's final.",
    "• Instagram needs .mp4 for video and .jpg for images (not .mov, .png or .heic).",
  ]
    .filter((l) => l !== null)
    .join("\n");
}

const month = await ensureFolder(monthName, rootId);
console.log(`${month.existed ? "•" : "+"} ${monthName}`);
const out = { period, month: { id: month.id, url: month.url, name: monthName }, days: {} };

for (const p of posts) {
  const owner = ownerOf(p);
  const name = `Day ${String(p.day).padStart(2, "0")} · ${fmtDay(p.date)} · ${p.format} · ${owner}`;
  const day = await ensureFolder(name, month.id);
  const raw = await ensureFolder(RAW, day.id);
  const edited = await ensureFolder(EDITED, day.id);
  if (!day.existed && !dryRun) await g.createText("Brief", day.id, brief(p, owner));
  out.days[p.day] = { name, owner, url: day.url, raw: raw.url, edited: edited.url };
  console.log(`${day.existed ? "•" : "+"} ${name}`);
}

if (!dryRun) {
  writeFileSync(path.join(ROOT, "content/drive-folders.json"), JSON.stringify(out, null, 2) + "\n");
  console.log(`\nSaved links to content/drive-folders.json · month folder: ${month.url}`);
}
