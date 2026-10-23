#!/usr/bin/env node
// Share cards (Open Graph / Twitter images), one per page, 1200×630 JPEG.
//
//   npm run build            (runs `next build`, then this script)
//   node scripts/og-cards.mjs [outDir]   (re-run on an existing out/ folder)
//
// Runs AFTER `next build` on the static export in out/. For every built page
// it reads what the page itself says, so nothing is listed twice:
//   - og:image        → which file to write (set by lib/seo.ts ogCard(), e.g. /og/garware-ppf-pune.jpg)
//   - <h1>            → the headline (falls back to the <title> without "| Jay Ganesh")
//   - .ph-bg <img>    → the page's own hero photo (PageHero); otherwise the studio render
//   - .dealer-lockup  → the brand's logo on authorised-dealer brand pages
// The rating comes from lib/reviews.json, the same file the site shows.
//
// Rendering: satori + resvg via next/og (ships with Next), then sharp (installed
// with Next) for the photo, logos and the JPEG. Fonts are local OFL files in
// scripts/og-fonts/, so the cards look the same on any build machine.
// Exits 1 if a page's card cannot be made, so a broken og:image never ships.

import { readFile, readdir, mkdir, writeFile } from "node:fs/promises";
import { existsSync } from "node:fs";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import path from "node:path";

const require = createRequire(import.meta.url);
const { ImageResponse } = require("next/og");
const sharp = require("sharp");

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, "..");
const outDir = path.resolve(root, process.argv[2] ?? "out");
const publicDir = path.join(root, "public");

const W = 1200;
const H = 630;
const YELLOW = "#FFE500";
const INK = "#0b0c0f";
const SITE_URL = "https://jayganeshcaraccessories.com";
const HOME_PHOTO = "/images/gen/hero-desktop.jpg";

/* ---------- helpers ---------- */

const decode = (s) =>
  s
    .replace(/&#x([0-9a-f]+);/gi, (_, h) => String.fromCodePoint(parseInt(h, 16)))
    .replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(Number(d)))
    .replace(/&quot;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&");

const text = (html) => decode(html.replace(/<!--.*?-->/gs, "").replace(/<br\s*\/?>/gi, " ").replace(/<[^>]+>/g, "")).replace(/\s+/g, " ").trim();

const meta = (html, prop) => {
  const m = html.match(new RegExp(`<meta[^>]+(?:property|name)="${prop.replace(/:/g, "\\:")}"[^>]*>`, "i"));
  const c = m?.[0].match(/content="([^"]*)"/);
  return c ? decode(c[1]) : undefined;
};

async function* htmlFiles(dir) {
  for (const e of await readdir(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) {
      if (["_next", "og", "404", "_not-found"].includes(e.name)) continue; // 404 pages share the home card
      yield* htmlFiles(p);
    } else if (e.name.endsWith(".html") && !/^(404|_not-found)\./.test(e.name)) yield p;
  }
}

const dataUri = (buf, type) => `data:${type};base64,${buf.toString("base64")}`;

/** Public path → file on disk (out/ first, then public/). */
function localFile(publicPath) {
  const clean = decodeURIComponent(publicPath.split(/[?#]/)[0]);
  for (const base of [outDir, publicDir]) {
    const f = path.join(base, clean);
    if (existsSync(f)) return f;
  }
  return null;
}

/* ---------- headline sizing ---------- */
// Archivo Black is wide: about 0.62em per character on average for mixed case.
const TEXT_W = 700;
const CHAR_EM = 0.62;

function lineCount(words, size) {
  const max = TEXT_W / (size * CHAR_EM);
  let lines = 1;
  let len = 0;
  for (const w of words) {
    const add = len === 0 ? w.length : len + 1 + w.length;
    if (add > max && len > 0) {
      lines++;
      len = w.length;
    } else len = add;
  }
  return lines;
}

/** Largest size (72 → 40 px) that keeps the headline to 3 lines (2 when it's short enough). */
function headlineSize(headline) {
  const words = headline.split(" ");
  for (let s = 72; s >= 40; s -= 2) if (lineCount(words, s) <= 2) return s;
  for (let s = 60; s >= 40; s -= 2) if (lineCount(words, s) <= 3) return s;
  return 40;
}

/* ---------- shared assets ---------- */

const fonts = [
  { name: "Archivo Black", data: await readFile(path.join(here, "og-fonts/ArchivoBlack-Regular.ttf")), weight: 900, style: "normal" },
  { name: "Inter", data: await readFile(path.join(here, "og-fonts/Inter-SemiBold.ttf")), weight: 600, style: "normal" },
];

const reviews = JSON.parse(await readFile(path.join(root, "lib/reviews.json"), "utf8"));
const rating = Number(reviews.rating).toFixed(1);
const count = Math.round(reviews.count);
const countLabel = count > 0 ? ` (${count.toLocaleString("en-IN")} reviews)` : "";

// Logo: 690×480 transparent PNG, drawn 120 px high. Rasterised at 2× and let
// resvg downsample, so edges stay crisp.
const LOGO_H = 120;
const logoMeta = await sharp(path.join(publicDir, "logo.png")).metadata();
const logoW = Math.round((logoMeta.width / logoMeta.height) * LOGO_H);
const logoUri = dataUri(await sharp(path.join(publicDir, "logo.png")).resize({ height: LOGO_H * 2 }).png().toBuffer(), "image/png");

const photoCache = new Map();
async function photo(publicPath) {
  if (!photoCache.has(publicPath)) {
    const f = localFile(publicPath) ?? localFile(HOME_PHOTO);
    const buf = await sharp(f).resize(W, H, { fit: "cover", position: "right" }).jpeg({ quality: 90 }).toBuffer();
    photoCache.set(publicPath, dataUri(buf, "image/jpeg"));
  }
  return photoCache.get(publicPath);
}

const BRAND_H = 40;
async function brandLogo(src) {
  const f = localFile(src);
  if (!f) return null;
  const buf = await sharp(f, { density: 300 }).resize({ height: BRAND_H * 2 }).png().toBuffer();
  const m = await sharp(buf).metadata();
  return { uri: dataUri(buf, "image/png"), w: Math.round((m.width / m.height) * BRAND_H) };
}

/* ---------- card ---------- */

const el = (type, style, children) => ({ type, props: { style, children } });

const star = {
  type: "svg",
  props: {
    width: 26,
    height: 26,
    viewBox: "0 0 24 24",
    style: { marginRight: 10 },
    children: { type: "path", props: { fill: YELLOW, d: "M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.8z" } },
  },
};

function card({ headline, bg, dealer, brand }) {
  const size = headlineSize(headline);
  const chips = [];
  if (dealer) {
    chips.push(
      el(
        "div",
        { display: "flex", alignItems: "center", height: 52, padding: "0 20px", borderRadius: 999, background: YELLOW, color: INK, fontFamily: "Inter", fontWeight: 600, fontSize: 22, letterSpacing: 0.3 },
        "Authorised dealer",
      ),
    );
  }
  if (brand) {
    chips.push(
      el("div", { display: "flex", alignItems: "center", height: 52, padding: "0 18px", marginLeft: 12, borderRadius: 999, background: brand.chip }, [
        { type: "img", props: { src: brand.uri, width: brand.w, height: BRAND_H, style: { width: brand.w, height: BRAND_H } } },
      ]),
    );
  }

  return el("div", { display: "flex", width: W, height: H, position: "relative", background: INK }, [
    { type: "img", props: { src: bg, width: W, height: H, style: { position: "absolute", left: 0, top: 0, width: W, height: H } } },
    el("div", {
      position: "absolute",
      left: 0,
      top: 0,
      width: W,
      height: H,
      backgroundImage: "linear-gradient(90deg, rgba(11,12,15,0.97) 0%, rgba(11,12,15,0.9) 38%, rgba(11,12,15,0.55) 64%, rgba(11,12,15,0.15) 100%)",
    }),
    el("div", {
      position: "absolute",
      left: 0,
      top: 0,
      width: W,
      height: H,
      backgroundImage: "linear-gradient(0deg, rgba(11,12,15,0.75) 0%, rgba(11,12,15,0) 34%)",
    }),
    el("div", { position: "absolute", left: 0, top: 0, width: W, height: 8, background: YELLOW }),
    el("div", { position: "absolute", left: 64, top: 48, bottom: 48, width: 760, display: "flex", flexDirection: "column" }, [
      { type: "img", props: { src: logoUri, width: logoW, height: LOGO_H, style: { width: logoW, height: LOGO_H } } },
      el("div", { display: "flex", flexDirection: "column", justifyContent: "center", flexGrow: 1 }, [
        ...(chips.length ? [el("div", { display: "flex", alignItems: "center", marginBottom: 20 }, chips)] : []),
        el(
          "div",
          { display: "flex", width: TEXT_W, fontFamily: "Archivo Black", fontSize: size, lineHeight: 1.08, color: "#ffffff", letterSpacing: -0.5 },
          headline,
        ),
        el("div", { width: 96, height: 6, marginTop: 26, background: YELLOW, borderRadius: 3 }),
      ]),
      el("div", { display: "flex", alignItems: "center", fontFamily: "Inter", fontWeight: 600, fontSize: 26, color: "#ffffff" }, [
        star,
        `${rating} on Google${countLabel}`,
        el("span", { color: YELLOW, margin: "0 14px" }, "·"),
        el("span", { color: "rgba(255,255,255,0.85)" }, "Loni Kalbhor, Pune"),
      ]),
    ]),
  ]);
}

/* ---------- pages ---------- */

async function pageSpec(file) {
  const html = await readFile(file, "utf8");
  const ogUrl = meta(html, "og:image");
  if (!ogUrl) throw new Error(`no og:image in ${path.relative(outDir, file)}`);
  const target = new URL(ogUrl, SITE_URL).pathname;
  if (!target.startsWith("/og/")) return null; // not a generated card

  const title = text(html.match(/<title[^>]*>(.*?)<\/title>/s)?.[1] ?? "").replace(/\s*\|\s*Jay Ganesh.*$/i, "");
  let headline = text(html.match(/<h1[^>]*>(.*?)<\/h1>/s)?.[1] ?? "");
  // The homepage card uses the page title (it says what the shop is), not the hero H1.
  if (!headline || headline.length > 80 || target === "/og/home.jpg") headline = title;

  // "Garware PPF in Pune – authorised dealer": the yellow badge says the second half.
  let dealer = false;
  const m = headline.match(/^(.*?)\s*[–—-]\s*authorised dealer$/i);
  if (m) {
    headline = m[1];
    dealer = true;
  }

  const bg = html.match(/<img[^>]*class="ph-bg"[^>]*>/)?.[0].match(/src="([^"]+)"/)?.[1] ?? HOME_PHOTO;

  let brand = null;
  const lockup = html.match(/class="dealer-lockup"[\s\S]*?<\/div>/)?.[0];
  if (lockup) {
    const ink = lockup.match(/brand-logo--(dark|light|white-mono|color)/)?.[1];
    const src = lockup.match(/<img[^>]*src="(\/brands\/[^"]+)"/)?.[1];
    if (src) {
      const logo = await brandLogo(src);
      // White chip for dark or full-colour ink; a dark glass chip for white ink.
      if (logo) brand = { ...logo, chip: ink === "light" || ink === "white-mono" ? "rgba(0,0,0,0.55)" : "#ffffff" };
    }
  }
  return { target, headline, bg, dealer, brand, page: path.relative(outDir, file) };
}

if (!existsSync(outDir)) {
  console.error(`og-cards: ${outDir} not found. Run next build first.`);
  process.exit(1);
}

const specs = new Map();
for await (const f of htmlFiles(outDir)) {
  const s = await pageSpec(f);
  if (s && !specs.has(s.target)) specs.set(s.target, s);
}

let failed = 0;
let bytes = 0;
for (const s of specs.values()) {
  try {
    const png = Buffer.from(await new ImageResponse(card({ ...s, bg: await photo(s.bg) }), { width: W, height: H, fonts }).arrayBuffer());
    const jpg = await sharp(png).jpeg({ quality: 82, mozjpeg: true, chromaSubsampling: "4:4:4" }).toBuffer();
    const dest = path.join(outDir, s.target);
    await mkdir(path.dirname(dest), { recursive: true });
    await writeFile(dest, jpg);
    bytes += jpg.length;
  } catch (e) {
    failed++;
    console.error(`og-cards: ${s.target} (${s.page}) failed: ${e.message}`);
  }
}
console.log(`og-cards: ${specs.size - failed} share cards in ${path.relative(root, outDir)}/og/ (avg ${Math.round(bytes / Math.max(1, specs.size - failed) / 1024)} KB)`);
if (failed) process.exit(1);
