// Uploads every .jpg in DIR to the media host's /gbp/ folder over FTPS, then checks each public URL.
//   node scripts/upload-gbp-images.mjs DIR        (DRY_RUN=true to only list)
// Env like src/sync-media.mjs: FTP_HOST, FTP_USER, FTP_PASSWORD, FTP_DIR, FTP_TLS_SELF_SIGNED, plus MEDIA_BASE_URL.
import { readdirSync, statSync, readFileSync } from "node:fs";
import { isIP } from "node:net";
import path from "node:path";
import { Client } from "basic-ftp";

try {
  for (const line of readFileSync(new URL("../.env", import.meta.url), "utf8").split("\n")) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
    if (m && m[2] && !process.env[m[1]]) process.env[m[1]] = m[2].replace(/^(["'])(.*)\1$/, "$2");
  }
} catch {}

const e = process.env;
const flag = (v) => ["1", "true", "yes"].includes(String(v || "").toLowerCase());
const dryRun = flag(e.DRY_RUN);
const dir = process.argv[2];
const base = (e.MEDIA_BASE_URL || "https://media.jayganeshcaraccessories.com").replace(/\/+$/, "");
const ftpDir = (e.FTP_DIR || "/").replace(/\/+$/, "") || "";

if (!dir) {
  console.error("Usage: node scripts/upload-gbp-images.mjs DIR");
  process.exit(1);
}
const files = readdirSync(dir).filter((f) => /\.jpe?g$/i.test(f)).sort();
if (!files.length) {
  console.error(`No .jpg files in ${dir}`);
  process.exit(1);
}
const missing = ["FTP_HOST", "FTP_USER", "FTP_PASSWORD"].filter((k) => !e[k]);
if (missing.length && !dryRun) {
  console.error(`Missing: ${missing.join(", ")}`);
  process.exit(1);
}

let bad = 0;
const ftp = new Client(60_000);
try {
  if (!dryRun) {
    await ftp.access({
      host: e.FTP_HOST,
      user: e.FTP_USER,
      password: e.FTP_PASSWORD,
      secure: true,
      secureOptions: { ...(isIP(e.FTP_HOST) ? {} : { servername: e.FTP_HOST }), rejectUnauthorized: !flag(e.FTP_TLS_SELF_SIGNED) },
    });
    await ftp.ensureDir(`${ftpDir}/gbp`);
  }
  for (const f of files) {
    const local = path.join(dir, f);
    const size = statSync(local).size;
    if (dryRun) {
      console.log(`DRY RUN  ${f} (${size} bytes) → ${ftpDir}/gbp/${f}`);
      continue;
    }
    await ftp.uploadFrom(local, `${ftpDir}/gbp/${f}`);
    const url = `${base}/gbp/${f}`;
    try {
      const r = await fetch(url, { method: "HEAD" });
      const remote = Number(r.headers.get("content-length") || 0);
      const ok = r.ok && /^image\//i.test(r.headers.get("content-type") || "") && (!remote || remote === size);
      if (!ok) bad++;
      console.log(`${ok ? "OK  " : "FAIL"} ${url}  http ${r.status}  local ${size} / remote ${remote || "?"} bytes${remote === size ? " (match)" : ""}`);
    } catch (err) {
      bad++;
      console.log(`FAIL ${url}  ${err.message}`);
    }
  }
} catch (err) {
  console.error(err.message);
  bad++;
} finally {
  ftp.close();
}
process.exit(bad ? 1 : 0);
