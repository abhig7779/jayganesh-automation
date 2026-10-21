// Two jobs:
//   node src/check.mjs setup      → checks every token and prints the IDs to save in GitHub
//   node src/check.mjs readiness  → checks the next 36 hours of posts (media, approval, values)
import { appendFileSync } from "node:fs";
import { loadCalendar, loadVars, loadMediaLog, isApproved, env, dayKey } from "./lib/config.mjs";
import { renderPost } from "./lib/render.mjs";
import { scheduledAt, fmtIST } from "./lib/schedule.mjs";
import { findMedia } from "./lib/media.mjs";
import { graph } from "./lib/graph.mjs";
import { youtube } from "./lib/youtube.mjs";

const mode = process.argv[2] || "setup";
const cfg = env();
const lines = [];
const say = (ok, msg) => {
  const l = `${ok === true ? "✓" : ok === false ? "✗" : "•"} ${msg}`;
  lines.push(l);
  console.log(l);
};
let bad = 0;
const check = async (label, fn) => {
  try {
    const msg = await fn();
    say(true, `${label}${msg ? ": " + msg : ""}`);
  } catch (e) {
    bad++;
    say(false, `${label}: ${e.message}`);
  }
};

if (mode === "setup") {
  await check("Media storage (MEDIA_BASE_URL)", async () => {
    if (!cfg.mediaBaseUrl) throw new Error("not set — add it under Settings → Secrets and variables → Actions → Variables");
    const r = await fetch(cfg.mediaBaseUrl + "/day-01/1.jpg", { method: "HEAD" });
    return `${cfg.mediaBaseUrl} reachable (day-01/1.jpg: ${r.ok ? "found" : "not uploaded yet"})`;
  });

  // Upload a throwaway file over FTPS from wherever this runs, check it is public, then delete it.
  // Uses no Drive files and no day folder, so it never approves or triggers a post.
  if (process.env.FTP_HOST && process.env.FTP_USER && process.env.FTP_PASSWORD) {
    await check("Media upload (FTP)", async () => {
      const { Client } = await import("basic-ftp");
      const { Readable } = await import("node:stream");
      const { isIP } = await import("node:net");
      const e = process.env;
      const dir = (e.FTP_DIR || "/").replace(/\/+$/, "");
      const selfSigned = ["1", "true", "yes"].includes(String(e.FTP_TLS_SELF_SIGNED || "").toLowerCase());
      const name = `_ftp-check-${Date.now()}.txt`;
      const c = new Client(30_000);
      try {
        await c.access({ host: e.FTP_HOST, user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { ...(isIP(e.FTP_HOST) ? {} : { servername: e.FTP_HOST }), rejectUnauthorized: !selfSigned } });
        await c.uploadFrom(Readable.from(Buffer.from("ftp check")), `${dir}/${name}`);
        const r = await fetch(`${cfg.mediaBaseUrl}/${name}`);
        await c.remove(`${dir}/${name}`);
        if (!r.ok) throw new Error(`uploaded, but ${cfg.mediaBaseUrl}/${name} returned ${r.status} — FTP_DIR may point at the wrong folder`);
        return `login, upload and public URL all work (FTP_DIR=${e.FTP_DIR || "/"}); test file removed`;
      } finally {
        c.close();
      }
    });
  } else {
    say(null, "Media upload (FTP): FTP_HOST / FTP_USER / FTP_PASSWORD not set, skipped");
  }

  if (!cfg.metaToken) {
    bad++;
    say(false, "META_TOKEN secret not set");
  } else {
    const g = graph({ version: cfg.graphVersion, token: cfg.metaToken });
    await check("Meta token", async () => {
      const me = await g.get("me", { fields: "id,name" });
      return `belongs to ${me.name} (${me.id})`;
    });
    if (!cfg.fbPageId) {
      await check("Facebook Pages this token can manage", async () => {
        const { data = [] } = await g.get("me/accounts", { fields: "id,name,instagram_business_account{id,username}" });
        if (!data.length) throw new Error("none — assign the Jay Ganesh Page to the system user");
        return data.map((p) => `${p.name} → FB_PAGE_ID=${p.id}${p.instagram_business_account ? `, IG_USER_ID=${p.instagram_business_account.id} (@${p.instagram_business_account.username})` : ""}`).join(" · ");
      });
    } else {
      await check("Facebook Page", async () => {
        const p = await g.get(cfg.fbPageId, { fields: "name,access_token,instagram_business_account{id,username}" });
        if (!p.access_token) throw new Error("no Page token — give the system user full control of the Page");
        const ig = p.instagram_business_account;
        return `${p.name}${ig ? ` · linked Instagram @${ig.username} → IG_USER_ID=${ig.id}` : " · no Instagram account linked to this Page"}`;
      });
    }
    if (cfg.igUserId) {
      await check("Instagram account", async () => {
        const a = await g.get(cfg.igUserId, { fields: "username,followers_count,media_count" });
        const lim = await g.get(`${cfg.igUserId}/content_publishing_limit`, { fields: "quota_usage,config" });
        return `@${a.username}, ${a.followers_count} followers · API posts used in last 24h: ${lim.data?.[0]?.quota_usage ?? "?"}/${lim.data?.[0]?.config?.quota_total ?? 100}`;
      });
    }
  }

  if (!cfg.ytRefreshToken) {
    bad++;
    say(false, "YT_CLIENT_ID / YT_CLIENT_SECRET / YT_REFRESH_TOKEN secrets not set");
  } else {
    await check("YouTube", async () => {
      const yt = youtube({ clientId: cfg.ytClientId, clientSecret: cfg.ytClientSecret, refreshToken: cfg.ytRefreshToken, privacy: cfg.ytPrivacy });
      try {
        const ch = await yt.channel();
        return ch ? `channel "${ch.snippet.title}" · uploads will be ${cfg.ytPrivacy}` : "token works, but no channel found on this Google account";
      } catch (e) {
        if (/insufficient|scope/i.test(e.message)) return "token works (upload only; add the youtube.readonly scope to see the channel name and enable duplicate checks)";
        throw e;
      }
    });
  }
}

if (mode === "readiness") {
  const { posts } = loadCalendar();
  const vars = loadVars();
  const horizon = cfg.now.getTime() + 36 * 36e5;
  const soon = posts.filter((p) => {
    const t = scheduledAt(p).getTime();
    return t >= cfg.now.getTime() - 36e5 && t <= horizon;
  });
  if (!soon.length) say(null, "No posts in the next 36 hours.");
  for (const p of soon) {
    const issues = [];
    if (cfg.requireApproval && !isApproved(p, loadMediaLog())) issues.push("files not in the Google Drive folder yet");
    const r = renderPost(p, vars);
    if (r.missing.length) issues.push(`fill in ${r.missing.join(", ")}`);
    const m = await findMedia(p, cfg.mediaBaseUrl);
    if (!m.ready) issues.push(m.reason);
    const label = `${dayKey(p.day)} · ${fmtIST(scheduledAt(p))} IST · ${p.format} · ${p.title}`;
    if (issues.length) {
      bad++;
      say(false, `${label} — ${issues.join("; ")}`);
    } else say(true, `${label} — ready`);
  }
}

if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, `### ${mode === "setup" ? "Setup check" : "Next 36 hours"}\n\n${lines.map((l) => "- " + l).join("\n")}\n`);
process.exit(bad ? 1 : 0);
