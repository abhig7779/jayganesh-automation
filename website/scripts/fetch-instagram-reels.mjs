#!/usr/bin/env node
// Pull the newest reels from @jayganeshcar_accessories_pune into the homepage "Watch our work" section.
//
//   META_TOKEN=... IG_USER_ID=... node scripts/fetch-instagram-reels.mjs
//
// For each recent reel whose caption names real work (PPF, coating, seat covers, lights, accessories):
//   public/reels/{shortcode}.mp4      540x960 H.264, faststart (skipped when Instagram withholds the
//                                     video, e.g. licensed music; the card then uses the embed)
//   public/reels/{shortcode}.jpg      405x720 poster, plus -270/-405 .webp copies
//   lib/reels-instagram.json          id, title, car, category, timestamp (newest first)
//   lib/image-sizes.json              poster sizes for lib/media.ts
// Titles and categories come only from the caption (facts rule: nothing invented).
// Safe by design: on any API error it changes nothing and exits 0. Needs ffmpeg on PATH (or FFMPEG).
import { readFile, writeFile, unlink, stat } from "node:fs/promises";
import { existsSync } from "node:fs";
import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import path from "node:path";

const here = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(here, "..");
const REELS = path.join(ROOT, "public/reels");
const DATA = path.join(ROOT, "lib/reels-instagram.json");
const SIZES = path.join(ROOT, "lib/image-sizes.json");
const KEEP = Number(process.env.KEEP || 16); // newest work reels kept on the site
const FFMPEG = process.env.FFMPEG || "ffmpeg";
const log = (...a) => console.log("[instagram-reels]", ...a);

// Order matters: the first category whose words appear in the caption wins.
const CATEGORIES = [
  ["ppf", /\bppf\b|paint protection/i],
  ["coating", /coat(?:ing)?|cotting|graphene|ceramic|kovalent/i],
  ["interior", /seat ?cover|auto ?form|interior|steering|floor ?mat|7d mat|upholster/i],
  ["lights", /head ?light|projector|fog ?lamp|\bled\b|ambient|light upgrade|auxiliary|moto ?ranger|aozoom|alphatron|pl75|u ?type/i],
  ["accessories", /screen|android|stereo|blaupunkt|innavi|speaker|dash ?cam|spoiler|grill|mirror|splitter|bumper|black look|body ?kit|alloy/i],
];

// Car names as the shop writes them → how the site writes them.
const CARS = [
  [/innova\s*crysta/i, "Toyota Innova Crysta"],
  [/innova\s*h(?:igh|y)\s*cross/i, "Toyota Innova Hycross"],
  [/fortuner/i, "Toyota Fortuner"],
  [/legender/i, "Toyota Fortuner Legender"],
  [/glanza|g l a n z a/i, "Toyota Glanza"],
  [/scorpio\s*classic/i, "Mahindra Scorpio Classic"],
  [/scorpio\s*-?n\b/i, "Mahindra Scorpio N"],
  [/xuv\s*7\s*xo/i, "Mahindra XUV 7XO"],
  [/xuv\s*700/i, "Mahindra XUV700"],
  [/\bthar\b/i, "Mahindra Thar"],
  [/\bbe\s*6\b/i, "Mahindra BE 6"],
  [/defender/i, "Land Rover Defender"],
  [/endeavour/i, "Ford Endeavour"],
  [/harrier/i, "Tata Harrier"],
  [/punch/i, "Tata Punch"],
  [/nexon/i, "Tata Nexon"],
  [/creta/i, "Hyundai Creta"],
  [/\bi\s*20\b/i, "Hyundai i20"],
  [/venue/i, "Hyundai Venue"],
  [/seltos/i, "Kia Seltos"],
  [/sonet/i, "Kia Sonet"],
  [/honda\s*city|\bcity\b/i, "Honda City"],
  [/baleno/i, "Maruti Baleno"],
  [/grand\s*vitara/i, "Maruti Grand Vitara"],
  [/brezza/i, "Maruti Brezza"],
  [/swift/i, "Maruti Swift"],
  [/ertiga/i, "Maruti Ertiga"],
  [/duster/i, "Renault Duster"],
];

// The work, as a short label. First match wins; brand names only when the caption says them.
const WORK = [
  [/ultraguard/i, "Ultraguard PPF"],
  [/garware.*ppf|ppf.*garware/i, "Garware PPF"],
  [/dhc.*ppf|ppf.*dhc/i, "DHC PPF"],
  [/\bppf\b|paint protection/i, "PPF"],
  [/kovalent/i, "Kovalent graphene coating"],
  [/graphene/i, "graphene coating"],
  [/ceramic/i, "ceramic coating"],
  [/auto ?form/i, "Autoform seat covers"],
  [/seat ?cover/i, "seat covers"],
  [/steering/i, "steering upgrade"],
  [/ambient/i, "ambient lighting"],
  [/alphatron/i, "Moto Ranger Alphatron headlights"],
  [/moto ?ranger/i, "Moto Ranger headlights"],
  [/aozoom/i, "Aozoom headlights"],
  [/pl75/i, "DHC PL75 projectors"],
  [/dhc.*(head ?light|projector)|u ?type/i, "DHC headlights"],
  [/auxiliary/i, "auxiliary lights"],
  [/projector/i, "projector headlights"],
  [/head ?light|light upgrade/i, "headlight upgrade"],
  [/blaupunkt/i, "Blaupunkt screen"],
  [/screen|android|stereo/i, "touchscreen stereo"],
  [/spoiler/i, "sporty spoiler"],
  [/grill/i, "new grille"],
  [/mirror|splitter/i, "mirror covers and splitter"],
  [/black look/i, "all-black look"],
];

function skip(reason) {
  log(`No change: ${reason}`);
  process.exit(0);
}

// The caption's first line says what the reel is; the rest (hashtags, offers) only fills gaps.
const firstMatch = (list, first, all, pick) => (list.find(([a, b]) => (pick === 0 ? b : a).test(first)) ?? list.find(([a, b]) => (pick === 0 ? b : a).test(all)))?.[pick === 0 ? 0 : 1];

function classify(caption) {
  const all = String(caption || "");
  const first = all.split("\n")[0];
  const category = firstMatch(CATEGORIES, first, all, 0);
  if (!category) return null;
  const car = firstMatch(CARS, first, all, 1);
  const work = firstMatch(WORK, first, all, 1);
  const part = first.match(/part\s*(\d+)/i)?.[1];
  const label = work ? work[0].toUpperCase() + work.slice(1) : null;
  const title = [car, label && car ? work : label].filter(Boolean).join(" · ") + (part && part !== "1" ? `, part ${part}` : "");
  return title ? { category, car, title } : null;
}

async function download(url, file) {
  const res = await fetch(url, { signal: AbortSignal.timeout(120_000) });
  if (!res.ok) throw new Error(`download ${res.status}`);
  await writeFile(file, Buffer.from(await res.arrayBuffer()));
}

const ff = (...args) => execFileSync(FFMPEG, ["-v", "error", "-y", ...args], { stdio: ["ignore", "ignore", "inherit"] });

async function main() {
  const token = process.env.META_TOKEN?.trim();
  const ig = process.env.IG_USER_ID?.trim();
  if (!token || !ig) skip("META_TOKEN or IG_USER_ID is not set.");
  const v = process.env.GRAPH_VERSION || "v23.0";
  const fields = "id,shortcode,caption,media_product_type,permalink,timestamp,thumbnail_url,media_url";
  const res = await fetch(`https://graph.facebook.com/${v}/${ig}/media?fields=${fields}&limit=50&access_token=${token}`, {
    signal: AbortSignal.timeout(30_000),
  }).catch((e) => skip(`Instagram API: ${e.message}`));
  const body = await res.json().catch(() => ({}));
  if (!res.ok || body.error) skip(`Instagram API: ${body.error?.message || res.status}`);

  const picked = [];
  for (const m of body.data || []) {
    if (m.media_product_type !== "REELS") continue;
    const id = m.shortcode || m.permalink?.match(/\/reel\/([^/]+)/)?.[1];
    const c = id && classify(m.caption);
    if (!c) continue;
    picked.push({ id, ...c, timestamp: m.timestamp, media_url: m.media_url, thumbnail_url: m.thumbnail_url });
    if (picked.length >= KEEP) break;
  }
  if (!picked.length) skip("no work reels found in the latest posts.");

  const prev = existsSync(DATA) ? JSON.parse(await readFile(DATA, "utf8")) : [];
  const sizes = JSON.parse(await readFile(SIZES, "utf8"));
  const out = [];
  for (const r of picked) {
    const mp4 = path.join(REELS, `${r.id}.mp4`);
    const jpg = path.join(REELS, `${r.id}.jpg`);
    let video = existsSync(mp4);
    try {
      if (!video && r.media_url) {
        const tmp = path.join(REELS, `${r.id}.src.mp4`);
        await download(r.media_url, tmp);
        ff("-i", tmp, "-vf", "scale=540:960:force_original_aspect_ratio=increase,crop=540:960", "-c:v", "libx264", "-preset", "slow", "-crf", "27",
          "-pix_fmt", "yuv420p", "-c:a", "aac", "-b:a", "80k", "-movflags", "+faststart", mp4);
        await unlink(tmp);
        video = true;
        log(`video  ${r.id}  ${r.title}`);
      }
      if (!existsSync(jpg)) {
        const src = path.join(REELS, `${r.id}.src.jpg`);
        if (r.thumbnail_url) await download(r.thumbnail_url, src);
        else if (video) ff("-ss", "1", "-i", mp4, "-frames:v", "1", src);
        else throw new Error("no thumbnail");
        ff("-i", src, "-vf", "scale=405:720:force_original_aspect_ratio=increase,crop=405:720", "-q:v", "3", jpg);
        await unlink(src);
        log(`poster ${r.id}`);
      }
      for (const w of [270, 405]) {
        const webp = path.join(REELS, `${r.id}-${w}.webp`);
        if (!existsSync(webp)) ff("-i", jpg, "-vf", `scale=${w}:-2`, "-quality", "78", webp);
      }
    } catch (e) {
      log(`skipped ${r.id}: ${e.message}`);
      continue;
    }
    sizes[`/reels/${r.id}.jpg`] = { w: 405, h: 720, jpg800: null, webp: [270, 405] };
    out.push({ id: r.id, title: r.title, ...(r.car ? { car: r.car } : {}), category: r.category, video, timestamp: r.timestamp });
  }

  // Remove files of auto reels that fell off the list (hand-picked reels in lib/reels.ts are never touched).
  const manual = await readFile(path.join(ROOT, "lib/reels.ts"), "utf8");
  for (const old of prev) {
    if (out.some((r) => r.id === old.id) || manual.includes(`"${old.id}"`)) continue;
    for (const f of [`${old.id}.mp4`, `${old.id}.jpg`, `${old.id}-270.webp`, `${old.id}-405.webp`]) {
      await unlink(path.join(REELS, f)).catch(() => {});
    }
    delete sizes[`/reels/${old.id}.jpg`];
    log(`removed ${old.id}`);
  }

  if (JSON.stringify(out) === JSON.stringify(prev)) skip(`already up to date (${out.length} reels).`);
  await writeFile(DATA, `${JSON.stringify(out, null, 2)}\n`);
  // Same layout as scripts/optimize-images.py writes: one entry per line.
  const line = (v) => JSON.stringify(v).replace(/([,:])/g, "$1 ");
  await writeFile(SIZES, `{\n${Object.entries(sizes).map(([k, v]) => `  ${JSON.stringify(k)}: ${line(v)}`).join(",\n")}\n}\n`);
  const mb = (await Promise.all(out.filter((r) => r.video).map((r) => stat(path.join(REELS, `${r.id}.mp4`))))).reduce((s, f) => s + f.size, 0) / 1e6;
  log(`Saved ${out.length} reels (${out.filter((r) => r.video).length} self-hosted videos, ${mb.toFixed(1)} MB).`);
}

main().catch((e) => skip(`error: ${e?.message ?? e}`));
