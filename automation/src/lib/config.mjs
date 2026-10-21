import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";

export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

const readJson = (rel) => JSON.parse(readFileSync(path.join(ROOT, rel), "utf8"));

export function loadCalendar() {
  return readJson("content/calendar.json");
}

export function loadVars() {
  const strip = (o) => Object.fromEntries(Object.entries(o).filter(([k]) => !k.startsWith("_")));
  return { ...strip(readJson("config/brand.json")), ...strip(readJson("config/offers.json")) };
}

const STATE = "state/posted.json";
export function loadState() {
  return readJson(STATE);
}
export function saveState(state) {
  writeFileSync(path.join(ROOT, STATE), JSON.stringify(state, null, 2) + "\n");
}

// Files copied from Google Drive to the media host (written by src/sync-media.mjs).
const MEDIA_LOG = "state/media.json";
export function loadMediaLog() {
  try {
    return { files: {}, ...readJson(MEDIA_LOG) };
  } catch {
    return { files: {} };
  }
}
export function saveMediaLog(log) {
  writeFileSync(path.join(ROOT, MEDIA_LOG), JSON.stringify(log, null, 2) + "\n");
}

// A post is approved if "approved" is true in the calendar, or if the team has dropped its
// files into the shared Google Drive folder and they have been synced to the media host.
export function isApproved(post, mediaLog = loadMediaLog()) {
  if (post.approved) return true;
  const prefix = `${dayKey(post.day)}/`;
  return Object.keys(mediaLog.files).some((p) => p.startsWith(prefix));
}

const flag = (v, dflt) => (v === undefined || v === "" ? dflt : ["1", "true", "yes"].includes(String(v).toLowerCase()));

export function env() {
  const e = process.env;
  return {
    mediaBaseUrl: (e.MEDIA_BASE_URL || "").replace(/\/+$/, ""),
    metaToken: e.META_TOKEN || "",
    igUserId: e.IG_USER_ID || "",
    fbPageId: e.FB_PAGE_ID || "",
    graphVersion: e.GRAPH_VERSION || "v23.0",
    ytClientId: e.YT_CLIENT_ID || "",
    ytClientSecret: e.YT_CLIENT_SECRET || "",
    ytRefreshToken: e.YT_REFRESH_TOKEN || "",
    ytPrivacy: e.YT_PRIVACY || "public",
    dryRun: flag(e.DRY_RUN, false),
    requireApproval: flag(e.REQUIRE_APPROVAL, true),
    forceDay: e.FORCE_DAY ? Number(e.FORCE_DAY) : null,
    onlyPlatforms: (e.ONLY_PLATFORMS || "").split(",").map((s) => s.trim()).filter(Boolean),
    graceHours: Number(e.GRACE_HOURS || 24),
    now: e.NOW ? new Date(e.NOW) : new Date(),
  };
}

export const dayKey = (day) => `day-${String(day).padStart(2, "0")}`;
