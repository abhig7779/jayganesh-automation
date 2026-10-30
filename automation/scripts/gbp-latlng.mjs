// Prints the shop's latitude/longitude from the Business Information API (read-only), for gbp-image.py --lat --lng.
//   node scripts/gbp-latlng.mjs        (needs GBP_CLIENT_ID, GBP_CLIENT_SECRET, GBP_REFRESH_TOKEN, GBP_LOCATION; reads .env if present)
import { readFileSync } from "node:fs";
import { gbp } from "../src/lib/gbp.mjs";

try {
  for (const line of readFileSync(new URL("../.env", import.meta.url), "utf8").split("\n")) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
    if (m && m[2] && !process.env[m[1]]) process.env[m[1]] = m[2].replace(/^(["'])(.*)\1$/, "$2");
  }
} catch {}

const e = process.env;
const missing = ["GBP_CLIENT_ID", "GBP_CLIENT_SECRET", "GBP_REFRESH_TOKEN", "GBP_LOCATION"].filter((k) => !e[k]);
if (missing.length) {
  console.error(`Missing: ${missing.join(", ")}`);
  process.exit(1);
}
try {
  const g = gbp({ clientId: e.GBP_CLIENT_ID, clientSecret: e.GBP_CLIENT_SECRET, refreshToken: e.GBP_REFRESH_TOKEN });
  const loc = await g.location(e.GBP_LOCATION, "latlng,storefrontAddress");
  if (!loc.latlng) throw new Error("this location has no latlng on file");
  console.log(`lat=${loc.latlng.latitude} lng=${loc.latlng.longitude}`);
  console.log(`--lat ${loc.latlng.latitude} --lng ${loc.latlng.longitude}`);
  if (loc.storefrontAddress) console.log((loc.storefrontAddress.addressLines || []).concat(loc.storefrontAddress.locality || "").join(", "));
} catch (err) {
  console.error(err.message);
  process.exit(1);
}
