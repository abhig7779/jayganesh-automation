// Offline test of src/gbp-posts.mjs: runs it as a child process against a temp calendar and state
// file with fetch mocked, so nothing real is read, posted or written.   node scripts/test-gbp-posts.mjs
import { spawnSync } from "node:child_process";
import { mkdtempSync, writeFileSync, readFileSync, existsSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const dir = mkdtempSync(path.join(tmpdir(), "gbp-test-"));
const calls = path.join(dir, "calls.jsonl");

// Preloaded into the child: fake Google + media host. Images named missing-*.jpg 404; notimage-*.jpg is HTML.
writeFileSync(path.join(dir, "mock.mjs"), `
import { appendFileSync } from "node:fs";
const log = (o) => appendFileSync(${JSON.stringify(calls)}, JSON.stringify(o) + "\\n");
globalThis.fetch = async (url, init = {}) => {
  url = String(url);
  log({ method: init.method || "GET", url, body: init.body && !(init.body instanceof URLSearchParams) ? init.body : undefined });
  if (url.includes("oauth2.googleapis.com")) return Response.json({ access_token: "tok" });
  if (url.includes("/localPosts")) return Response.json({ name: "accounts/1/locations/2/localPosts/999", searchUrl: "https://local.google.com/place?id=1" });
  if (url.includes("/gbp/")) {
    if (url.includes("/gbp/missing-")) return new Response(null, { status: 404 });
    return new Response(null, { status: 200, headers: { "content-type": url.includes("/gbp/notimage-") ? "text/html" : "image/jpeg" } });
  }
  throw new Error("unexpected fetch " + url);
};
`);

const good = { summary: "Fresh paint protection film installed on a hatchback at our studio in Loni Kalbhor, Pune. Book a slot to keep your paint glossy.", cta: { actionType: "LEARN_MORE", url: "https://jayganeshcaraccessories.com/ppf-pune" }, image: "ppf-installation-loni-kalbhor-pune.jpg" };
const mk = (day, extra = {}, gbp = {}, time = "10:00") => ({ day, date: "2026-10-27", time, title: `T${day}`, type: "image", platforms: ["instagram"], approved: true, ...extra, gbp: { ...good, ...gbp } });
const cases = [
  ["valid post goes through", mk(1), "POSTED"],
  ["valid post, Instagram failed earlier", mk(2, {}, {}), "POSTED", { instagram_error: { message: "boom" } }],
  ["CALL button needs no url", mk(3, {}, { cta: { actionType: "CALL" } }), "POSTED"],
  ["phone number", mk(4, {}, { summary: "Call us on 98765 43210 for PPF in Pune." }), "BLOCKED"],
  ["+91 number", mk(5, {}, { summary: "Call +91 98765 43210 today." }), "BLOCKED"],
  ["URL in text", mk(6, {}, { summary: "Visit jayganeshcaraccessories.com for PPF." }), "BLOCKED"],
  ["hashtag", mk(7, {}, { summary: "PPF in Pune #ppf" }), "BLOCKED"],
  ["two emojis", mk(8, {}, { summary: "Shiny paint ✨ 🚗" }), "BLOCKED"],
  ["over 1500 characters", mk(9, {}, { summary: "PPF in Loni Kalbhor. ".repeat(80) }), "BLOCKED"],
  ["bad CTA type", mk(10, {}, { cta: { actionType: "BUY_NOW", url: good.cta.url } }), "BLOCKED"],
  ["CTA url on another site", mk(11, {}, { cta: { actionType: "LEARN_MORE", url: "https://example.com/x" } }), "BLOCKED"],
  ["image 404", mk(12, {}, { image: "missing-ppf-loni-kalbhor-pune.jpg" }), "NO MEDIA"],
  ["image is not an image", mk(13, {}, { image: "notimage-ppf-loni-kalbhor-pune.jpg" }), "NO MEDIA"],
  ["unapproved post", mk(14, { approved: false }), "WAITING"],
  ["not yet due", mk(15, {}, {}, "20:00"), null],
  ["already posted", mk(16), null, { gbp: { name: "accounts/1/locations/2/localPosts/1", url: "", at: "x" } }],
];

function run(name, posts, state, env = {}) {
  writeFileSync(path.join(dir, "calendar.json"), JSON.stringify({ posts }));
  writeFileSync(path.join(dir, "state.json"), JSON.stringify(state));
  writeFileSync(calls, "");
  const r = spawnSync(process.execPath, ["--import", path.join(dir, "mock.mjs"), path.join(here, "../src/gbp-posts.mjs")], {
    encoding: "utf8",
    env: {
      PATH: process.env.PATH,
      GBP_CALENDAR_FILE: path.join(dir, "calendar.json"),
      GBP_STATE_FILE: path.join(dir, "state.json"),
      MEDIA_BASE_URL: "https://media.test",
      NOW: "2026-10-27T05:00:00Z", // 10:30 IST
      GBP_CLIENT_ID: "id", GBP_CLIENT_SECRET: "secret", GBP_REFRESH_TOKEN: "rt", GBP_LOCATION: "accounts/1/locations/2",
      ...env,
    },
  });
  const fetched = readFileSync(calls, "utf8").split("\n").filter(Boolean).map((l) => JSON.parse(l));
  return { r, fetched, state: JSON.parse(readFileSync(path.join(dir, "state.json"), "utf8")) };
}

let fail = 0;
const check = (ok, what) => {
  if (!ok) fail++;
  console.log(`${ok ? "  ok  " : " FAIL "} ${what}`);
};

const realBefore = ["content/calendar.json", "state/posted.json"].map((f) => readFileSync(path.join(here, "..", f), "utf8"));

// Live path (mocked Google), one post per run so each outcome is isolated.
for (const [label, post, want, stateExtra] of cases) {
  const { r, fetched, state } = run(label, [post], { [`day-${String(post.day).padStart(2, "0")}`]: stateExtra || {} });
  const k = `day-${String(post.day).padStart(2, "0")}`;
  const posts = fetched.filter((c) => c.method === "POST" && c.url.includes("/localPosts"));
  if (want === "POSTED") {
    const body = posts[0] && JSON.parse(posts[0].body);
    check(r.status === 0 && posts.length === 1 && state[k].gbp?.name?.endsWith("/999"), `${label}: posted and logged`);
    check(body?.media?.[0]?.sourceUrl === `https://media.test/gbp/${post.gbp.image}` && body.topicType === "STANDARD", `${label}: body has photo url`);
    if (post.gbp.cta.actionType === "CALL") check(!body.callToAction.url, `${label}: no url on CALL`);
    else check(/utm_source=google&utm_medium=gbp&utm_campaign=day-/.test(body.callToAction.url), `${label}: utm added`);
  } else if (want) {
    check(r.status === 1 && !posts.length && r.stdout.includes(want) && !state[k].gbp, `${label}: ${want}, nothing created, run fails`);
  } else {
    check(r.status === 0 && !posts.length && !r.stdout.includes("gbp POSTED"), `${label}: skipped quietly`);
  }
}

// Several posts in one run: a bad one must not stop a good one, and the run still exits 1.
{
  const { r, state } = run("mixed", [mk(21, {}, { summary: "Call 9876543210" }), mk(22)], {});
  check(r.status === 1 && state["day-22"].gbp?.name && !state["day-21"].gbp, "mixed run: bad post fails, good post still posted, exit 1");
  const again = run("rerun", [mk(22)], state);
  check(again.r.status === 0 && !again.fetched.some((c) => c.url.includes("/localPosts")), "re-run does not post twice");
}

// FORCE_DAY ignores the time; DRY_RUN creates nothing and leaves state untouched.
{
  const f = run("force", [mk(30, {}, {}, "23:00")], {}, { FORCE_DAY: "30" });
  check(f.r.status === 0 && f.state["day-30"].gbp?.name, "FORCE_DAY posts a not-yet-due post");
  const d = run("dry", [mk(31)], {}, { DRY_RUN: "true" });
  check(d.r.status === 0 && d.r.stdout.includes("DRY RUN") && !d.fetched.some((c) => c.url.includes("/localPosts") || c.url.includes("oauth2")) && !d.state["day-31"]?.gbp, "DRY_RUN: validated, no post, no token, no state");
}

const realAfter = ["content/calendar.json", "state/posted.json"].map((f) => readFileSync(path.join(here, "..", f), "utf8"));
check(realBefore.every((s, i) => s === realAfter[i]), "real calendar.json and posted.json untouched");

rmSync(dir, { recursive: true, force: true });
console.log(fail ? `\n${fail} FAILED` : "\nAll passed");
process.exit(fail ? 1 : 0);
