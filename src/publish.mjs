// Runs every 30 minutes on GitHub Actions. Publishes any approved post whose time has come
// and whose media is in Cloudflare R2. Each platform is logged in state/posted.json, so a
// post that failed on one platform is retried on the next run without repeating the others.
import { appendFileSync } from "node:fs";
import { loadCalendar, loadVars, loadState, saveState, env, dayKey } from "./lib/config.mjs";
import { renderPost } from "./lib/render.mjs";
import { timing, fmtIST } from "./lib/schedule.mjs";
import { findMedia } from "./lib/media.mjs";
import { instagram } from "./lib/instagram.mjs";
import { facebook } from "./lib/facebook.mjs";
import { youtube } from "./lib/youtube.mjs";

const cfg = env();
const { posts } = loadCalendar();
const vars = loadVars();
const state = loadState();
const rows = [];
let failures = 0;

const clients = {
  instagram: () => {
    if (!cfg.metaToken || !cfg.igUserId) throw new Error("META_TOKEN and IG_USER_ID are required for Instagram");
    return instagram({ version: cfg.graphVersion, token: cfg.metaToken, igUserId: cfg.igUserId });
  },
  facebook: () => {
    if (!cfg.metaToken || !cfg.fbPageId) throw new Error("META_TOKEN and FB_PAGE_ID are required for Facebook");
    return facebook({ version: cfg.graphVersion, token: cfg.metaToken, pageId: cfg.fbPageId });
  },
  youtube: () => {
    if (!cfg.ytClientId || !cfg.ytClientSecret || !cfg.ytRefreshToken) throw new Error("YT_CLIENT_ID, YT_CLIENT_SECRET and YT_REFRESH_TOKEN are required for YouTube");
    return youtube({ clientId: cfg.ytClientId, clientSecret: cfg.ytClientSecret, refreshToken: cfg.ytRefreshToken, privacy: cfg.ytPrivacy });
  },
};
const made = {};
const client = (p) => (made[p] ||= clients[p]());

// A blocked post alerts (fails the run, so GitHub emails) once per problem, not every 30 minutes.
function problem(post, log, platform, status, detail) {
  log.alerts ||= {};
  const first = log.alerts[status] !== detail;
  if (first) {
    log.alerts[status] = detail;
    failures++;
  }
  note(post, platform, status, detail + (first ? "" : " (already reported)"));
}

function note(post, platform, status, detail = "") {
  rows.push({ day: post.day, title: post.title, platform, status, detail });
  console.log(`[${dayKey(post.day)}] ${platform.padEnd(9)} ${status}${detail ? " — " + detail : ""}`);
}

for (const post of posts) {
  if (cfg.forceDay && post.day !== cfg.forceDay) continue;
  const key = dayKey(post.day);
  const log = (state[key] ||= {});
  const platforms = post.platforms.filter((p) => !cfg.onlyPlatforms.length || cfg.onlyPlatforms.includes(p));
  const pending = platforms.filter((p) => !log[p]?.id);
  if (!pending.length) continue;

  const t = timing(post, cfg.now, cfg.graceHours);
  if (!cfg.forceDay && t.state === "upcoming") continue;
  if (!cfg.forceDay && t.state === "missed") {
    problem(post, log, pending.join("+"), "MISSED", `was due ${fmtIST(t.at)} IST; post it by hand or run with FORCE_DAY=${post.day}`);
    continue;
  }

  if (cfg.requireApproval && !post.approved) {
    problem(post, log, pending.join("+"), "WAITING", `due ${fmtIST(t.at)} IST but "approved" is false in content/calendar.json`);
    continue;
  }

  const r = renderPost(post, vars);
  if (r.missing.length) {
    problem(post, log, pending.join("+"), "BLOCKED", `fill in: ${r.missing.join(", ")}`);
    continue;
  }

  const media = await findMedia(post, cfg.mediaBaseUrl);
  if (!media.ready) {
    problem(post, log, pending.join("+"), "NO MEDIA", media.reason);
    continue;
  }

  for (const platform of pending) {
    if (platform === "youtube" && post.type !== "reel") continue;
    const content = platform === "youtube" ? { title: r.yt_title, description: r.yt_description } : r.caption;
    if (cfg.dryRun) {
      note(post, platform, "DRY RUN", platform === "youtube" ? r.yt_title : r.caption.split("\n")[0]);
      continue;
    }
    try {
      const c = client(platform);
      const p = { ...post, first_comment: r.first_comment };
      const existing = await c.alreadyPosted(platform === "youtube" ? r.yt_title : r.caption);
      const result = existing ? { ...existing, recovered: true } : await c.publish(p, media, content);
      log[platform] = { ...result, at: new Date().toISOString() };
      delete log[`${platform}_error`];
      saveState(state);
      note(post, platform, existing ? "ALREADY LIVE" : "POSTED", result.url || result.id);
    } catch (err) {
      failures++;
      log[`${platform}_error`] = { message: err.message, at: new Date().toISOString() };
      saveState(state);
      note(post, platform, "FAILED", err.message);
    }
  }
}

if (!rows.length) console.log(`Nothing due at ${fmtIST(cfg.now)} IST.`);
if (!cfg.dryRun) saveState(state);

if (process.env.GITHUB_STEP_SUMMARY) {
  const md = [
    `### Jay Ganesh publisher · ${fmtIST(cfg.now)} IST${cfg.dryRun ? " · DRY RUN" : ""}`,
    rows.length ? "| Day | Post | Platform | Status | Detail |\n|---|---|---|---|---|" : "Nothing due.",
    ...rows.map((x) => `| ${x.day} | ${x.title} | ${x.platform} | ${x.status} | ${String(x.detail).replace(/\|/g, "/")} |`),
  ].join("\n");
  appendFileSync(process.env.GITHUB_STEP_SUMMARY, md + "\n");
}

process.exit(failures ? 1 : 0);
