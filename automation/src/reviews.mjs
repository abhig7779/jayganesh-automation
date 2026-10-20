// Runs every 2 hours on GitHub Actions. Finds Google reviews without an owner reply and:
//   4–5★ → Claude writes a reply and it is posted straight away
//   1–3★ → Claude writes a draft and a GitHub issue asks a person to approve or rewrite it
// Replied and drafted reviews are logged in state/reviews.json.
import { appendFileSync, readFileSync, writeFileSync, existsSync } from "node:fs";
import path from "node:path";
import { ROOT, loadVars } from "./lib/config.mjs";
import { gbp, STARS } from "./lib/gbp.mjs";
import { replyWriter } from "./lib/reply-writer.mjs";

const e = process.env;
const cfg = {
  clientId: e.GBP_CLIENT_ID || e.GOOGLE_CLIENT_ID || e.YT_CLIENT_ID,
  clientSecret: e.GBP_CLIENT_SECRET || e.GOOGLE_CLIENT_SECRET || e.YT_CLIENT_SECRET,
  refreshToken: e.GBP_REFRESH_TOKEN,
  location: e.GBP_LOCATION, // accounts/123/locations/456
  minAutoStars: Number(e.AUTO_REPLY_MIN_STARS || 4),
  sinceDays: Number(e.REVIEW_SINCE_DAYS || 30),
  maxPerRun: Number(e.MAX_REPLIES_PER_RUN || 10),
  dryRun: ["1", "true", "yes"].includes(String(e.DRY_RUN || "").toLowerCase()),
};

const STATE = path.join(ROOT, "state/reviews.json");
const state = existsSync(STATE) ? JSON.parse(readFileSync(STATE, "utf8")) : {};
state.replied ||= {};
state.drafts ||= {};
const save = () => !cfg.dryRun && writeFileSync(STATE, JSON.stringify(state, null, 2) + "\n");

const rows = [];
const log = (r) => {
  rows.push(r);
  console.log(`${"★".repeat(r.stars)}${"☆".repeat(5 - r.stars)} ${r.name.padEnd(22)} ${r.status}${r.detail ? " — " + r.detail : ""}`);
};

// Not set up yet: skip quietly (exit 0) so the schedule doesn't send a failure email every 2 hours.
const missing = Object.entries({
  GBP_CLIENT_ID: cfg.clientId,
  GBP_CLIENT_SECRET: cfg.clientSecret,
  GBP_REFRESH_TOKEN: cfg.refreshToken,
  ANTHROPIC_API_KEY: e.ANTHROPIC_API_KEY,
})
  .filter(([, v]) => !v)
  .map(([k]) => k);
if (missing.length) {
  const msg = `Review replies not set up yet, skipping. Add these repository secrets: ${missing.join(", ")} (see README → Google review auto-replies).`;
  console.log(msg);
  if (e.GITHUB_STEP_SUMMARY) appendFileSync(e.GITHUB_STEP_SUMMARY, `### Google reviews · skipped\n\n${msg}\n`);
  process.exit(0);
}
const api = gbp(cfg);

if (!cfg.location) {
  console.log("GBP_LOCATION is not set. Locations this Google account manages:");
  for (const a of await api.accounts()) {
    for (const l of await api.locations(a.name)) console.log(`  ${l.title} → GBP_LOCATION=${a.name}/${l.name}`);
  }
  console.log("Save the right one as the GBP_LOCATION variable and run again.");
  process.exit(1);
}

async function openIssue(review, stars, draft) {
  const repo = e.GITHUB_REPOSITORY;
  if (!repo || !e.GITHUB_TOKEN) return null;
  const who = review.reviewer?.displayName || "Anonymous";
  const body = [
    `<!-- review: ${review.name} -->`,
    `**${"★".repeat(stars)}${"☆".repeat(5 - stars)} from ${who}** · ${new Date(review.createTime).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" })}`,
    "",
    `> ${(review.comment || "(rating only, no text)").replace(/\n/g, "\n> ")}`,
    "",
    "### Suggested reply",
    "<!-- draft:start -->",
    draft,
    "<!-- draft:end -->",
    "",
    "### What to do",
    "- Comment **`/approve`** to post the suggested reply as it is.",
    "- Or comment **`/reply`** followed by your own text on the next line to post that instead.",
    "- Call the customer first if the problem needs fixing. Close the issue to skip replying.",
  ].join("\n");
  const res = await fetch(`https://api.github.com/repos/${repo}/issues`, {
    method: "POST",
    headers: { Authorization: `Bearer ${e.GITHUB_TOKEN}`, Accept: "application/vnd.github+json" },
    body: JSON.stringify({ title: `${stars}★ Google review from ${who} needs a reply`, body, labels: ["review"] }),
  });
  const d = await res.json();
  if (!res.ok) throw new Error(`Could not open GitHub issue: ${d.message}`);
  return d.number;
}

const vars = loadVars();
const writer = replyWriter({ phone: vars.WHATSAPP });
const all = await api.reviews(cfg.location);
const since = Date.now() - cfg.sinceDays * 864e5;
const recentReplies = all.filter((r) => r.reviewReply?.comment).map((r) => r.reviewReply.comment);
const todo = all.filter(
  (r) => !r.reviewReply && new Date(r.updateTime || r.createTime) > since && !state.replied[r.reviewId] && !state.drafts[r.reviewId],
);
console.log(`${all.length} reviews read, ${todo.length} without a reply in the last ${cfg.sinceDays} days.`);

let failures = 0;
for (const review of todo.slice(0, cfg.maxPerRun)) {
  const stars = STARS[review.starRating] || 0;
  const name = review.reviewer?.isAnonymous ? "" : review.reviewer?.displayName || "";
  const row = { stars, name: name || "Anonymous" };
  try {
    const text = await writer.write({ name, stars, comment: review.comment, recentReplies });
    recentReplies.unshift(text);
    if (stars >= cfg.minAutoStars) {
      if (!cfg.dryRun) await api.reply(review.name, text);
      state.replied[review.reviewId] = { at: new Date().toISOString(), stars, auto: true };
      log({ ...row, status: cfg.dryRun ? "DRY RUN (would post)" : "REPLIED", detail: text.split("\n")[0] });
    } else {
      const issue = cfg.dryRun ? null : await openIssue(review, stars, text);
      state.drafts[review.reviewId] = { at: new Date().toISOString(), stars, issue };
      log({ ...row, status: cfg.dryRun ? "DRY RUN (would ask)" : "NEEDS APPROVAL", detail: issue ? `issue #${issue}` : text.split("\n")[0] });
    }
    save();
  } catch (err) {
    failures++;
    log({ ...row, status: "FAILED", detail: err.message });
  }
}
if (todo.length > cfg.maxPerRun) console.log(`${todo.length - cfg.maxPerRun} more will be handled on the next run.`);

if (e.GITHUB_STEP_SUMMARY) {
  appendFileSync(
    e.GITHUB_STEP_SUMMARY,
    [`### Google reviews${cfg.dryRun ? " · DRY RUN" : ""}`, `${todo.length} without a reply.`, "", ...rows.map((r) => `- ${"★".repeat(r.stars)} **${r.name}** — ${r.status}${r.detail ? `: ${r.detail}` : ""}`)].join("\n") + "\n",
  );
}
process.exit(failures ? 1 : 0);
