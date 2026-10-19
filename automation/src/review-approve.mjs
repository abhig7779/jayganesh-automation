// Runs when someone comments /approve or /reply on a review issue.
// Posts the reply to Google, comments back on the issue and closes it.
import { readFileSync, writeFileSync, existsSync } from "node:fs";
import path from "node:path";
import { ROOT } from "./lib/config.mjs";
import { gbp } from "./lib/gbp.mjs";

const e = process.env;
const issueBody = e.ISSUE_BODY || "";
const comment = (e.COMMENT_BODY || "").trim();

const reviewName = issueBody.match(/<!-- review: (\S+) -->/)?.[1];
if (!reviewName) throw new Error("This issue has no review reference.");

let text;
if (/^\/approve\b/i.test(comment)) {
  text = issueBody.match(/<!-- draft:start -->\n([\s\S]*?)\n<!-- draft:end -->/)?.[1]?.trim();
} else if (/^\/reply\b/i.test(comment)) {
  text = comment.replace(/^\/reply\b/i, "").trim();
}
if (!text) throw new Error("Nothing to post: use /approve, or /reply followed by the reply text.");

const api = gbp({
  clientId: e.GBP_CLIENT_ID || e.GOOGLE_CLIENT_ID || e.YT_CLIENT_ID,
  clientSecret: e.GBP_CLIENT_SECRET || e.GOOGLE_CLIENT_SECRET || e.YT_CLIENT_SECRET,
  refreshToken: e.GBP_REFRESH_TOKEN,
});
await api.reply(reviewName, text);

const STATE = path.join(ROOT, "state/reviews.json");
const state = existsSync(STATE) ? JSON.parse(readFileSync(STATE, "utf8")) : {};
state.replied ||= {};
const reviewId = reviewName.split("/").pop();
state.replied[reviewId] = { at: new Date().toISOString(), auto: false, issue: Number(e.ISSUE_NUMBER) };
writeFileSync(STATE, JSON.stringify(state, null, 2) + "\n");

const gh = (url, init) =>
  fetch(`https://api.github.com/repos/${e.GITHUB_REPOSITORY}${url}`, {
    ...init,
    headers: { Authorization: `Bearer ${e.GITHUB_TOKEN}`, Accept: "application/vnd.github+json" },
  });
await gh(`/issues/${e.ISSUE_NUMBER}/comments`, { method: "POST", body: JSON.stringify({ body: `✅ Reply posted on Google:\n\n> ${text.replace(/\n/g, "\n> ")}` }) });
await gh(`/issues/${e.ISSUE_NUMBER}`, { method: "PATCH", body: JSON.stringify({ state: "closed", state_reason: "completed" }) });
console.log("Reply posted:", text);
