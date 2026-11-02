#!/usr/bin/env node
// Refresh lib/reviews.json from Google.
//
// Preferred: the shop's own Google Business Profile API (exact review count, newest reviews):
//   GBP_CLIENT_ID=... GBP_CLIENT_SECRET=... GBP_REFRESH_TOKEN=... GBP_LOCATION=accounts/…/locations/… node scripts/fetch-reviews.mjs
// Fallback: Places API (New):
//   GOOGLE_MAPS_API_KEY=... [GOOGLE_PLACE_ID=...] node scripts/fetch-reviews.mjs
//
// Updates rating + review count and merges Google's latest reviews into the saved list.
// Safe by design: on any error or an implausible answer it logs why and exits 0
// WITHOUT touching the file, so the site always keeps its last good data.
// Node 20+, no dependencies.

import { readFile, writeFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const here = path.dirname(fileURLToPath(import.meta.url));
const DATA_FILE = path.resolve(here, "../lib/reviews.json");
const SEARCH_QUERY = "Jay Ganesh Car Accessories Loni Kalbhor Pune";
// Keep the full imported history; the daily refresh only adds new ones.
const MAX_REVIEWS = 400;
const TIMEOUT_MS = 20_000;

const log = (...a) => console.log("[fetch-reviews]", ...a);

/** Leave the file alone and exit successfully. */
function skip(reason) {
  log(`No change: ${reason}`);
  process.exit(0);
}

async function api(url, { method = "GET", fieldMask, body, key }) {
  const res = await fetch(url, {
    method,
    headers: {
      "X-Goog-Api-Key": key,
      "X-Goog-FieldMask": fieldMask,
      ...(body ? { "Content-Type": "application/json" } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
    signal: AbortSignal.timeout(TIMEOUT_MS),
  });
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${url.split("?")[0]} → HTTP ${res.status}: ${text.slice(0, 400)}`);
  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`Bad JSON from ${url.split("?")[0]}`);
  }
}

async function resolvePlaceId(key) {
  const data = await api("https://places.googleapis.com/v1/places:searchText", {
    method: "POST",
    key,
    fieldMask: "places.id,places.displayName",
    body: { textQuery: SEARCH_QUERY },
  });
  const places = Array.isArray(data.places) ? data.places : [];
  if (!places.length) throw new Error(`Text Search found no place for "${SEARCH_QUERY}"`);
  const match = places.find((p) => /jay\s*ganesh/i.test(p.displayName?.text ?? "")) ?? places[0];
  log(`Resolved place: ${match.displayName?.text ?? "?"} → ${match.id}`);
  log(`Save this as the GOOGLE_PLACE_ID secret so the lookup is skipped next time: ${match.id}`);
  if (places.length > 1) {
    log("Other candidates:", places.filter((p) => p !== match).map((p) => `${p.displayName?.text} (${p.id})`).join(", "));
  }
  return match.id;
}

const norm = (s) => String(s ?? "").toLowerCase().replace(/\s+/g, " ").trim();
const dedupeKey = (r) => `${norm(r.name)}|${norm(r.text).slice(0, 40)}`;

function slug(s) {
  return norm(s).normalize("NFKD").replace(/[^\p{L}\p{N}]+/gu, "-").replace(/^-+|-+$/g, "") || "review";
}

function relativeToDays(when) {
  const m = String(when ?? "").toLowerCase().match(/(a|an|\d+)\s+(minute|hour|day|week|month|year)s?\s+ago/);
  if (!m) return null;
  const n = m[1] === "a" || m[1] === "an" ? 1 : Number(m[1]);
  return n * { minute: 1 / 1440, hour: 1 / 24, day: 1, week: 7, month: 30.44, year: 365.25 }[m[2]];
}

/** Sort key: real publish time, else the saved estimate, else estimated from the label at `base`. */
function sortTime(r, base) {
  for (const iso of [r.publishTime, r.approxTime && `${r.approxTime}T12:00:00Z`]) {
    if (!iso) continue;
    const t = Date.parse(iso);
    if (!Number.isNaN(t)) return t;
  }
  const days = relativeToDays(r.when);
  const b = Date.parse(`${base}T12:00:00Z`);
  return days == null || Number.isNaN(b) ? 0 : b - days * 86_400_000;
}

function mapGoogleReview(g) {
  const name = g?.authorAttribution?.displayName?.trim();
  const text = (g?.text?.text ?? g?.originalText?.text ?? "").trim();
  const stars = Number(g?.rating);
  if (!name || !text || !Number.isFinite(stars)) return null;
  return {
    id: slug(name),
    name,
    stars: Math.round(stars),
    text,
    when: g.relativePublishTimeDescription ?? "",
    publishTime: g.publishTime ?? null,
    // Reviewer's Google profile picture, shown on their own review card.
    ...(g?.authorAttribution?.photoUri ? { photo: g.authorAttribution.photoUri } : {}),
  };
}

const STARS = { ONE: 1, TWO: 2, THREE: 3, FOUR: 4, FIVE: 5 };

// Google adds a machine translation to non-English reviews; keep only the first part.
const stripTranslation = (t) => String(t ?? "").split(/\n*\((?:Translated by Google|Original)\)\n*/)[0].trim();

function mapGbpReview(g) {
  const name = g?.reviewer?.displayName?.trim();
  const text = stripTranslation(g?.comment);
  const stars = STARS[g?.starRating];
  if (!name || g?.reviewer?.isAnonymous || !text || !stars) return null;
  return {
    id: slug(name),
    name,
    stars,
    text,
    when: "",
    publishTime: g.createTime ?? null,
    ...(g?.reviewer?.profilePhotoUrl ? { photo: g.reviewer.profilePhotoUrl } : {}),
  };
}

/** Google Business Profile API: { rating, count, reviews } or null when not configured. */
async function fromBusinessProfile() {
  const { GBP_CLIENT_ID: id, GBP_CLIENT_SECRET: secret, GBP_REFRESH_TOKEN: refresh, GBP_LOCATION: location } = process.env;
  if (!id || !secret || !refresh || !location) return null;
  const tok = await fetch("https://oauth2.googleapis.com/token", {
    method: "POST",
    body: new URLSearchParams({ client_id: id, client_secret: secret, refresh_token: refresh, grant_type: "refresh_token" }),
    signal: AbortSignal.timeout(TIMEOUT_MS),
  }).then((r) => r.json());
  if (!tok.access_token) throw new Error(`Google token refresh failed: ${tok.error_description || tok.error || "no token"}`);
  let rating, count, pageToken = "";
  const reviews = [];
  // Newest first; two pages (100 reviews) is plenty for a daily refresh.
  for (let page = 0; page < 2; page++) {
    const q = new URLSearchParams({ pageSize: "50", orderBy: "updateTime desc", ...(pageToken ? { pageToken } : {}) });
    const res = await fetch(`https://mybusiness.googleapis.com/v4/${location.trim()}/reviews?${q}`, {
      headers: { Authorization: `Bearer ${tok.access_token}` },
      signal: AbortSignal.timeout(TIMEOUT_MS),
    });
    const d = await res.json();
    if (!res.ok) throw new Error(`Business Profile reviews → HTTP ${res.status}: ${d.error?.message ?? ""}`);
    rating ??= d.averageRating;
    count ??= d.totalReviewCount;
    reviews.push(...(d.reviews ?? []));
    pageToken = d.nextPageToken ?? "";
    if (!pageToken) break;
  }
  log(`Business Profile: rating ${rating}, ${count} reviews, ${reviews.length} fetched.`);
  return { rating, count, reviews: reviews.map(mapGbpReview).filter(Boolean) };
}

async function fromPlaces() {
  const key = process.env.GOOGLE_MAPS_API_KEY?.trim();
  if (!key) return null;
  const placeId = process.env.GOOGLE_PLACE_ID?.trim() || (await resolvePlaceId(key));
  const d = await api(`https://places.googleapis.com/v1/places/${encodeURIComponent(placeId)}?languageCode=en`, {
    key,
    fieldMask: "rating,userRatingCount,reviews",
  });
  return { rating: d.rating, count: d.userRatingCount, reviews: (Array.isArray(d.reviews) ? d.reviews : []).map(mapGoogleReview).filter(Boolean) };
}

async function main() {

  let current;
  try {
    current = JSON.parse(await readFile(DATA_FILE, "utf8"));
  } catch (e) {
    skip(`could not read ${DATA_FILE}: ${e.message}`);
  }
  const today = new Date().toISOString().slice(0, 10);

  const details = (await fromBusinessProfile()) ?? (await fromPlaces());
  if (!details) skip("neither the Business Profile login (GBP_*) nor GOOGLE_MAPS_API_KEY is set.");

  // Plausibility checks: never replace good data with something worse.
  const rating = Number(details.rating);
  const count = Number(details.count);
  if (!Number.isFinite(rating) || rating <= 0 || rating > 5) skip(`rating missing or invalid (${details.rating}).`);
  if (!Number.isInteger(count) || count <= 0) skip(`review count missing or invalid (${details.count}).`);
  if (Number.isFinite(current.count) && count < current.count) {
    skip(`count went down (${current.count} → ${count}); keeping the saved data.`);
  }

  // Merge reviews. Fresh Google copies win over saved ones (newer text / exact publishTime).
  const fetched = details.reviews;
  const byKey = new Map();
  for (const r of current.reviews ?? []) byKey.set(dedupeKey(r), r);
  for (const r of fetched) {
    const k = dedupeKey(r);
    const prev = byKey.get(k);
    byKey.set(k, prev ? { ...prev, ...r, id: prev.id, text: r.text.length >= String(prev.text).length ? r.text : prev.text } : r);
  }

  // Saved reviews' labels were relative to the last refresh; fresh ones to today.
  const fresh = new Set(fetched);
  const refDate = current.updatedAt || today;
  const merged = [...byKey.values()]
    .filter((r) => r.stars >= 4 && r.stars <= 5 && String(r.text ?? "").trim())
    .map((r, i) => ({ r, i, t: sortTime(r, fresh.has(r) ? today : refDate) }))
    .sort((a, b) => b.t - a.t || a.i - b.i)
    .slice(0, MAX_REVIEWS)
    .map(({ r, t }) => {
      // Without a real publishTime, keep a dated estimate so the label can age correctly.
      if (r.publishTime) return { ...r, approxTime: undefined };
      return { ...r, approxTime: r.approxTime || (t ? new Date(t).toISOString().slice(0, 10) : undefined) };
    });

  // Unique ids.
  const seen = new Set();
  for (const r of merged) {
    let id = r.id || slug(r.name);
    for (let n = 2; seen.has(id); n++) id = `${r.id || slug(r.name)}-${n}`;
    seen.add(id);
    r.id = id;
  }

  const next = {
    rating: Math.round(rating * 10) / 10,
    count,
    updatedAt: current.updatedAt,
    source: "google",
    reviews: merged.map(({ id, name, stars, text, when, publishTime, approxTime, topics, photo }) => ({
      id,
      name,
      stars,
      text,
      when,
      publishTime: publishTime ?? null,
      ...(publishTime || !approxTime ? {} : { approxTime }),
      // Keep the saved topic tags and the reviewer's photo.
      ...(topics ? { topics } : {}),
      ...(photo ? { photo } : {}),
    })),
  };

  const changed = JSON.stringify({ ...next, updatedAt: "" }) !== JSON.stringify({ ...current, updatedAt: "" });
  // The site words review ages relative to updatedAt, so refresh the date weekly even when nothing else moved.
  const staleDays = (Date.parse(today) - Date.parse(current.updatedAt)) / 86_400_000;
  if (!changed && !(staleDays >= 7)) skip(`already up to date (rating ${next.rating}, ${next.count} reviews).`);

  next.updatedAt = today;
  await writeFile(DATA_FILE, `${JSON.stringify(next, null, 2)}\n`, "utf8");
  log(
    `Updated: rating ${current.rating} → ${next.rating}, count ${current.count} → ${next.count}, ` +
      `${fetched.length} fetched, ${next.reviews.length} saved.`,
  );
}

main().catch((e) => skip(`error: ${e?.message ?? e}`));
