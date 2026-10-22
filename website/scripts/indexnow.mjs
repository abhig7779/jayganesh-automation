#!/usr/bin/env node
// Submit every URL in the sitemap to IndexNow (Bing, Yandex, Seznam, Naver...).
// Run after a deploy:  node scripts/indexnow.mjs
// Reads out/sitemap.xml when present (after `npm run build`), otherwise the live sitemap.
// The key file public/<KEY>.txt must be live at https://jayganeshcaraccessories.com/<KEY>.txt.
import { readFile } from "node:fs/promises";
import { existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";

const HOST = "jayganeshcaraccessories.com";
const SITE = `https://${HOST}`;
const KEY = "4a5d228707e343e0159efdf7f26b99d5";
const KEY_LOCATION = `${SITE}/${KEY}.txt`;

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const localSitemap = path.join(root, "out", "sitemap.xml");

async function sitemapXml() {
  if (existsSync(localSitemap)) return readFile(localSitemap, "utf8");
  const res = await fetch(`${SITE}/sitemap.xml`);
  if (!res.ok) throw new Error(`Could not fetch sitemap: ${res.status}`);
  return res.text();
}

const xml = await sitemapXml();
const urlList = [...xml.matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/g)].map((m) => m[1]).filter((u) => u.startsWith(SITE));
if (!urlList.length) throw new Error("No URLs found in the sitemap.");

const res = await fetch("https://api.indexnow.org/indexnow", {
  method: "POST",
  headers: { "Content-Type": "application/json; charset=utf-8" },
  body: JSON.stringify({ host: HOST, key: KEY, keyLocation: KEY_LOCATION, urlList }),
});
console.log(`IndexNow: submitted ${urlList.length} URLs, response ${res.status} ${res.statusText}`);
if (res.status >= 400) {
  console.error(await res.text());
  process.exit(1);
}
