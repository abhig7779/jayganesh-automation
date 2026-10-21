// Runs before every publish. Copies new or changed files from the shared Google Drive folder to the
// public media host (MilesWeb, over FTPS), renamed to what the publisher expects:
//   Drive: <root>/Day 5/anything.mp4          → media: day-05/video.mp4
//   Drive: <root>/day-07/poster.jpg, b.jpg   → media: day-07/1.jpg, 2.jpg (sorted by name)
//   Drive: <root>/day-02/cover.jpg           → media: day-02/cover.jpg (optional reel cover)
// A day whose files have been synced counts as approved (see isApproved in lib/config.mjs).
// Logged in state/media.json so unchanged files are not uploaded again.
import { appendFileSync } from "node:fs";
import { Readable } from "node:stream";
import { isIP } from "node:net";
import { Client } from "basic-ftp";
import { loadCalendar, loadMediaLog, saveMediaLog, dayKey } from "./lib/config.mjs";
import { drive, FOLDER } from "./lib/drive.mjs";

const e = process.env;
const cfg = {
  clientId: e.GBP_CLIENT_ID || e.GOOGLE_CLIENT_ID,
  clientSecret: e.GBP_CLIENT_SECRET || e.GOOGLE_CLIENT_SECRET,
  refreshToken: e.DRIVE_REFRESH_TOKEN || e.GBP_REFRESH_TOKEN,
  folderId: e.DRIVE_FOLDER_ID,
  ftp: { host: e.FTP_HOST, user: e.FTP_USER, password: e.FTP_PASSWORD, dir: (e.FTP_DIR || "/").replace(/\/+$/, "") || "" },
  dryRun: ["1", "true", "yes"].includes(String(e.DRY_RUN || "").toLowerCase()),
};

const summary = [];
const say = (s) => (console.log(s), summary.push(s));
const finish = (code) => {
  if (e.GITHUB_STEP_SUMMARY) appendFileSync(e.GITHUB_STEP_SUMMARY, `### Media sync${cfg.dryRun ? " · DRY RUN" : ""}\n\n${summary.map((s) => "- " + s).join("\n")}\n`);
  process.exit(code);
};

const missing = Object.entries({
  GBP_CLIENT_ID: cfg.clientId,
  GBP_CLIENT_SECRET: cfg.clientSecret,
  "DRIVE_REFRESH_TOKEN (or GBP_REFRESH_TOKEN)": cfg.refreshToken,
  DRIVE_FOLDER_ID: cfg.folderId,
  FTP_HOST: cfg.ftp.host,
  FTP_USER: cfg.ftp.user,
  FTP_PASSWORD: cfg.ftp.password,
})
  .filter(([, v]) => !v)
  .map(([k]) => k);
if (missing.length) {
  say(`Media sync not set up yet, skipping. Missing: ${missing.join(", ")}. Files already on the media host still post.`);
  finish(0);
}

const DAY_RE = /^day[\s_-]*0*(\d{1,2})$/i;
const natural = (a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: "base" });
const isJpeg = (f) => /\.jpe?g$/i.test(f.name) || f.mimeType === "image/jpeg";

// Decide the target name of each Drive file for one day.
function plan(post, files) {
  const out = [];
  const problems = [];
  const live = files.filter((f) => f.mimeType !== FOLDER && !f.name.startsWith("."));
  if (post.type === "reel") {
    const videos = live.filter((f) => /\.mp4$/i.test(f.name) || f.mimeType === "video/mp4");
    if (!videos.length) {
      const other = live.find((f) => /\.(mov|m4v|webm|avi)$/i.test(f.name));
      problems.push(other ? `${other.name} must be exported as .mp4` : "no .mp4 video yet");
    } else {
      if (videos.length > 1) problems.push(`${videos.length} videos found, using ${videos.sort(natural)[0].name}`);
      out.push({ file: videos.sort(natural)[0], target: "video.mp4" });
    }
    const cover = live.find((f) => isJpeg(f) && /^cover/i.test(f.name));
    if (cover) out.push({ file: cover, target: "cover.jpg" });
  } else {
    const images = live.filter(isJpeg).sort(natural);
    const nonJpeg = live.filter((f) => /\.(png|webp|heic|gif)$/i.test(f.name));
    if (nonJpeg.length) problems.push(`${nonJpeg.map((f) => f.name).join(", ")}: Instagram needs JPEG, re-export as .jpg`);
    const max = post.type === "image" ? 1 : 10;
    if (post.type === "carousel" && images.length === 1) problems.push("carousel needs at least 2 images");
    if (images.length > max) problems.push(`${images.length} images, using the first ${max}`);
    images.slice(0, max).forEach((f, i) => out.push({ file: f, target: `${i + 1}.jpg` }));
  }
  return { out, problems };
}

const g = drive(cfg);
const { posts } = loadCalendar();
const byDay = new Map(posts.map((p) => [p.day, p]));
const log = loadMediaLog();

const folders = (await g.children(cfg.folderId)).filter((f) => f.mimeType === FOLDER);
const dayFolders = folders.map((f) => ({ f, day: Number(f.name.trim().match(DAY_RE)?.[1]) })).filter((x) => byDay.has(x.day));
const ignored = folders.filter((f) => !DAY_RE.test(f.name.trim())).map((f) => f.name);
if (ignored.length) say(`Ignored folders (name them "day-01" … "day-30"): ${ignored.join(", ")}`);
if (!dayFolders.length) {
  say("No day folders in the Drive folder yet.");
  finish(0);
}

let ftp = null;
async function ftpClient() {
  if (ftp) return ftp;
  ftp = new Client(60_000);
  // FTP_TLS_SELF_SIGNED=true: shared hosts often use a self-signed FTPS certificate. The link stays
  // encrypted; only the certificate's issuer check is skipped.
  const selfSigned = ["1", "true", "yes"].includes(String(e.FTP_TLS_SELF_SIGNED || "").toLowerCase());
  await ftp.access({
    host: cfg.ftp.host,
    user: cfg.ftp.user,
    password: cfg.ftp.password,
    secure: true,
    secureOptions: { ...(isIP(cfg.ftp.host) ? {} : { servername: cfg.ftp.host }), rejectUnauthorized: !selfSigned },
  });
  return ftp;
}

let failures = 0;
for (const { f, day } of dayFolders.sort((a, b) => a.day - b.day)) {
  const post = byDay.get(day);
  const key = dayKey(day);
  const { out, problems } = plan(post, await g.children(f.id));
  problems.forEach((p) => say(`${key}: ${p}`));
  for (const { file, target } of out) {
    const path = `${key}/${target}`;
    const prev = log.files[path];
    if (prev && prev.driveId === file.id && prev.md5 === file.md5Checksum) continue;
    if (cfg.dryRun) {
      say(`${path} ← ${file.name} (would upload, ${Math.round(Number(file.size || 0) / 1e6)} MB)`);
      continue;
    }
    try {
      const bytes = await g.download(file.id);
      const c = await ftpClient();
      await c.ensureDir(`${cfg.ftp.dir}/${key}`);
      await c.uploadFrom(Readable.from(bytes), `${cfg.ftp.dir}/${key}/${target}`);
      log.files[path] = { driveId: file.id, name: file.name, md5: file.md5Checksum, size: bytes.length, at: new Date().toISOString() };
      saveMediaLog(log);
      say(`${path} ← ${file.name} uploaded`);
    } catch (err) {
      failures++;
      say(`${path} ← ${file.name} FAILED: ${err.message}`);
    }
  }
  // Remove log entries for slots that no longer have a file (e.g. a carousel went from 5 to 4 images).
  const keep = new Set(out.map((o) => `${key}/${o.target}`));
  for (const p of Object.keys(log.files)) if (p.startsWith(key + "/") && !keep.has(p)) delete log.files[p];
}
if (!cfg.dryRun) saveMediaLog(log);
ftp?.close();
if (!summary.length) say("Everything in Drive is already on the media host.");
finish(failures ? 1 : 0);
