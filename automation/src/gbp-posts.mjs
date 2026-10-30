// Runs after the Instagram/Facebook/YouTube publisher. For every approved, due calendar post that
// has a "gbp" object, makes a "What's new" post on the Google Business Profile with its own SEO-named
// photo from MEDIA_BASE_URL/gbp/, SEO-written text and one button. Logged in state/posted.json under
// <day>.gbp, so a post is never made twice. Independent of the other platforms: a failed Instagram
// publish does not stop the GBP post. See GBP-POSTS.md.
//   post.gbp = { summary, cta: { actionType, url? }, image: "seo-name.jpg", alt? }
import { appendFileSync, readFileSync, writeFileSync } from "node:fs";
import { loadCalendar, loadState, saveState, loadMediaLog, isApproved, env, dayKey } from "./lib/config.mjs";
import { timing, fmtIST } from "./lib/schedule.mjs";
import { gbp } from "./lib/gbp.mjs";

const e = process.env;
const cfg = {
  ...env(),
  clientId: e.GBP_CLIENT_ID || "",
  clientSecret: e.GBP_CLIENT_SECRET || "",
  refreshToken: e.GBP_REFRESH_TOKEN || "",
  location: e.GBP_LOCATION || "",
};

// Test hooks: point at a fixture calendar and state file instead of the real ones.
const { posts } = e.GBP_CALENDAR_FILE ? JSON.parse(readFileSync(e.GBP_CALENDAR_FILE, "utf8")) : loadCalendar();
const state = e.GBP_STATE_FILE ? JSON.parse(readFileSync(e.GBP_STATE_FILE, "utf8")) : loadState();
const persist = () => (e.GBP_STATE_FILE ? writeFileSync(e.GBP_STATE_FILE, JSON.stringify(state, null, 2) + "\n") : saveState(state));
const mediaLog = loadMediaLog();

const SITE = "jayganeshcaraccessories.com";
const ACTIONS = new Set(["LEARN_MORE", "CALL", "BOOK", "ORDER", "SHOP", "SIGN_UP"]);
const rows = [];
let failures = 0;

function note(post, status, detail = "") {
  rows.push({ day: post.day, title: post.title, status, detail });
  console.log(`[${dayKey(post.day)}] gbp ${status}${detail ? " — " + detail : ""}`);
}

// A blocked post alerts (fails the run) once per problem, not every 30 minutes.
function problem(post, log, status, detail) {
  log.alerts ||= {};
  const first = log.alerts[`GBP ${status}`] !== detail;
  if (first) {
    log.alerts[`GBP ${status}`] = detail;
    failures++;
  }
  note(post, status, detail + (first ? "" : " (already reported)"));
}

// Returns a list of rule violations; empty means the post is allowed on GBP.
function validateGbp(g) {
  const errs = [];
  const s = typeof g?.summary === "string" ? g.summary.trim() : "";
  if (!s) errs.push("summary is missing");
  if (s.length > 1500) errs.push(`summary is ${s.length} characters (max 1,500)`);
  if (/\+\s*91|(?<!\d)(?:\d[\s.-]?){10,}(?!\d)/.test(s)) errs.push("summary has a phone number");
  if (/https?:\/\/|www\.|\b[a-z0-9-]+\.(?:com|in|org|net|co|io)\b/i.test(s)) errs.push("summary has a URL");
  if (/(^|\s)#\S/.test(s)) errs.push("summary has a hashtag");
  if ((s.match(/\p{Extended_Pictographic}/gu) || []).length > 1) errs.push("summary has more than one emoji");
  if (!/^[a-z0-9][a-z0-9-]*\.jpe?g$/i.test(g?.image || "")) errs.push("image must be a plain file name ending in .jpg");
  const t = g?.cta?.actionType;
  if (!ACTIONS.has(t)) errs.push(`cta.actionType must be one of ${[...ACTIONS].join(", ")}`);
  else if (t !== "CALL") {
    try {
      const u = new URL(g.cta.url);
      if (u.protocol !== "https:" || (u.hostname !== SITE && u.hostname !== `www.${SITE}`)) throw 0;
    } catch {
      errs.push(`cta.url must be https://${SITE}/…`);
    }
  }
  return errs;
}

const withUtm = (url, key) => {
  const u = new URL(url);
  if (!u.searchParams.has("utm_source")) u.searchParams.set("utm_source", "google");
  if (!u.searchParams.has("utm_medium")) u.searchParams.set("utm_medium", "gbp");
  if (!u.searchParams.has("utm_campaign")) u.searchParams.set("utm_campaign", key);
  return u.toString();
};

async function checkImage(url) {
  try {
    const r = await fetch(url, { method: "HEAD" });
    if (!r.ok) return `image not found at ${url} (${r.status})`;
    const type = r.headers.get("content-type") || "";
    if (!/^image\//i.test(type)) return `${url} is not an image (${type || "no content-type"})`;
    return "";
  } catch (err) {
    return `could not reach ${url}: ${err.message}`;
  }
}

let client = null;

for (const post of posts) {
  if (!post.gbp) continue;
  if (cfg.forceDay && post.day !== cfg.forceDay) continue;
  const key = dayKey(post.day);
  const log = (state[key] ||= {});
  if (log.gbp?.name) continue;

  const t = timing(post, cfg.now, cfg.graceHours);
  if (!cfg.forceDay && t.state === "upcoming") continue;
  if (!cfg.forceDay && t.state === "missed") {
    problem(post, log, "MISSED", `was due ${fmtIST(t.at)} IST; post it by hand or run with FORCE_DAY=${post.day}`);
    continue;
  }
  if (cfg.requireApproval && !isApproved(post, mediaLog)) {
    problem(post, log, "WAITING", `due ${fmtIST(t.at)} IST but the post is not approved yet`);
    continue;
  }

  const errs = validateGbp(post.gbp);
  if (errs.length) {
    problem(post, log, "BLOCKED", errs.join("; "));
    continue;
  }
  if (!cfg.mediaBaseUrl) {
    problem(post, log, "NO MEDIA", "MEDIA_BASE_URL is not set");
    continue;
  }
  const imageUrl = `${cfg.mediaBaseUrl}/gbp/${post.gbp.image}`;
  const imgErr = await checkImage(imageUrl);
  if (imgErr) {
    problem(post, log, "NO MEDIA", imgErr);
    continue;
  }

  const summary = post.gbp.summary.trim();
  const cta = post.gbp.cta.actionType === "CALL" ? { actionType: "CALL" } : { actionType: post.gbp.cta.actionType, url: withUtm(post.gbp.cta.url, key) };
  if (cfg.dryRun) {
    note(post, "DRY RUN", `${cta.actionType} · ${post.gbp.image} · ${summary.split("\n")[0]}`);
    continue;
  }
  try {
    if (!cfg.clientId || !cfg.clientSecret || !cfg.refreshToken || !cfg.location) throw new Error("GBP_CLIENT_ID, GBP_CLIENT_SECRET, GBP_REFRESH_TOKEN and GBP_LOCATION are required");
    client ||= gbp({ clientId: cfg.clientId, clientSecret: cfg.clientSecret, refreshToken: cfg.refreshToken });
    const result = await client.createLocalPost({ location: cfg.location, summary, cta, imageUrl });
    log.gbp = { ...result, at: new Date().toISOString() };
    delete log.gbp_error;
    persist();
    note(post, "POSTED", result.url || result.name);
  } catch (err) {
    failures++;
    log.gbp_error = { message: err.message, at: new Date().toISOString() };
    persist();
    note(post, "FAILED", err.message);
  }
}

if (!rows.length) console.log(`No Google Business Profile posts due at ${fmtIST(cfg.now)} IST.`);
if (!cfg.dryRun) persist();

if (e.GITHUB_STEP_SUMMARY) {
  const md = [
    `### Google Business Profile · ${fmtIST(cfg.now)} IST${cfg.dryRun ? " · DRY RUN" : ""}`,
    rows.length ? "| Day | Post | Status | Detail |\n|---|---|---|---|" : "Nothing due.",
    ...rows.map((x) => `| ${x.day} | ${x.title} | ${x.status} | ${String(x.detail).replace(/\|/g, "/")} |`),
  ].join("\n");
  appendFileSync(e.GITHUB_STEP_SUMMARY, md + "\n");
}

process.exit(failures ? 1 : 0);
