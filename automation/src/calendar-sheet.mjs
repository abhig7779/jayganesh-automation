// Writes the content calendar to a Google Sheet the team can open: one row per post with a preview,
// date, time, platforms, status and links. Rows turn green and read "✅ Posted" once the post is live
// (from state/posted.json). Runs after every publish, so the sheet follows the posting log.
//   node --env-file=.env src/calendar-sheet.mjs          (CALENDAR_SHEET_ID must be set)
//   CREATE=1 node --env-file=.env src/calendar-sheet.mjs (first time: makes the sheet in DRIVE_FOLDER_ID)
// Needs the Google Sheets API enabled in the Cloud project; the OAuth token's drive scope is enough.
import { loadCalendar, loadState, dayKey } from "./lib/config.mjs";

const e = process.env;
const MEDIA = (e.MEDIA_BASE_URL || "https://media.jayganeshcaraccessories.com").replace(/\/+$/, "");
const TAB = "Calendar";

async function token() {
  const r = await fetch("https://oauth2.googleapis.com/token", {
    method: "POST",
    body: new URLSearchParams({
      client_id: e.GBP_CLIENT_ID,
      client_secret: e.GBP_CLIENT_SECRET,
      refresh_token: e.GBP_REFRESH_TOKEN,
      grant_type: "refresh_token",
    }),
  });
  const d = await r.json();
  if (!d.access_token) throw new Error(`Google token refresh failed: ${d.error_description || d.error}`);
  return d.access_token;
}

const tok = await token();
async function api(url, method = "GET", body) {
  const r = await fetch(url, {
    method,
    headers: { Authorization: `Bearer ${tok}`, ...(body ? { "Content-Type": "application/json" } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  });
  const d = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(`${method} ${url.split("?")[0]}: ${d.error?.message || r.status}`);
  return d;
}
const SHEETS = "https://sheets.googleapis.com/v4/spreadsheets";

let id = e.CALENDAR_SHEET_ID;
if (!id) {
  if (!e.CREATE) {
    console.log("CALENDAR_SHEET_ID is not set; skipping the calendar sheet.");
    process.exit(0);
  }
  const made = await api(SHEETS, "POST", { properties: { title: "Jay Ganesh · Content calendar" }, sheets: [{ properties: { title: TAB } }] });
  id = made.spreadsheetId;
  if (e.DRIVE_FOLDER_ID) {
    await api(`https://www.googleapis.com/drive/v3/files/${id}?addParents=${e.DRIVE_FOLDER_ID}&removeParents=root&supportsAllDrives=true`, "PATCH", {});
  }
  console.log(`Created sheet ${id}: https://docs.google.com/spreadsheets/d/${id}/edit  (save it as the CALENDAR_SHEET_ID variable)`);
}

const { posts } = loadCalendar();
const state = loadState();
const now = Date.now();
const PLAT = { instagram: "Instagram", facebook: "Facebook", youtube: "YouTube", gbp: "Google" };
const ist = (iso) =>
  new Date(iso).toLocaleString("en-GB", { timeZone: "Asia/Kolkata", day: "2-digit", month: "short", hour: "numeric", minute: "2-digit", hour12: true });
const kind = (p) => (p.type === "reel" ? (/car-edit/i.test(p.format) ? "Car-edit reel" : "Polished reel") : "Creative");
const q = (s) => String(s ?? "").replace(/"/g, '""');
const link = (u, label) => (u ? `=HYPERLINK("${q(u)}","${label}")` : "");

const rows = [];
const status = [];
for (const p of posts) {
  const log = state[dayKey(p.day)] || {};
  const targets = [...p.platforms, ...(p.gbp ? ["gbp"] : [])];
  const done = targets.filter((t) => log[t]?.url || log[t]?.id || log[t]?.name);
  const due = new Date(`${p.date}T${p.time}:00+05:30`).getTime();
  let s, label;
  if (done.length && done.length >= p.platforms.length) {
    s = "posted";
    label = `✅ Posted${done.length < targets.length ? ` (${done.map((t) => PLAT[t]).join(", ")})` : ""}`;
  } else if (done.length) {
    s = "partial";
    label = `🟡 Partly posted (${done.map((t) => PLAT[t]).join(", ")})`;
  } else if (!p.approved) {
    s = p.type === "reel" ? "footage" : "waiting";
    label = p.type === "reel" ? "🎬 Needs footage" : "⏳ Waiting for approval";
  } else if (due < now - 3 * 3600_000) {
    s = "missed";
    label = "⚠️ Missed, not posted";
  } else {
    s = "scheduled";
    label = "🕒 Scheduled";
  }
  const preview = `${MEDIA}/${dayKey(p.day)}/${p.type === "reel" ? "cover.jpg" : "1.jpg"}`;
  const at = done.map((t) => log[t]?.at).filter(Boolean).sort()[0];
  const d = new Date(`${p.date}T12:00:00+05:30`);
  rows.push([
    `=IMAGE("${preview}")`,
    d.toLocaleDateString("en-GB", { weekday: "short", day: "2-digit", month: "short", timeZone: "Asia/Kolkata" }),
    new Date(`${p.date}T${p.time}:00+05:30`).toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit", timeZone: "Asia/Kolkata" }),
    kind(p),
    p.title.replace(/ \(needs footage\)$/, ""),
    targets.map((t) => PLAT[t]).join(" + "),
    label,
    at ? ist(at) : "",
    link(log.instagram?.url, "Instagram"),
    link(log.facebook?.url, "Facebook"),
    link(log.youtube?.url, "YouTube"),
    link(log.gbp?.searchUrl || log.gbp?.url, "Google"),
    (p.caption || "").split("\n").find((l) => l.trim()) || "",
  ]);
  status.push(s);
}
const header = ["Preview", "Date", "Time (IST)", "Type", "Post", "Goes to", "Status", "Posted at", "Instagram", "Facebook", "YouTube", "Google", "Caption (first line)"];

const meta = await api(`${SHEETS}/${id}?fields=sheets.properties`);
let sheet = meta.sheets.find((s) => s.properties.title === TAB)?.properties;
if (!sheet) sheet = (await api(`${SHEETS}/${id}:batchUpdate`, "POST", { requests: [{ addSheet: { properties: { title: TAB } } }] })).replies[0].addSheet.properties;
const sid = sheet.sheetId;

await api(`${SHEETS}/${id}/values/${TAB}!A1:Z1000:clear`, "POST", {});
await api(`${SHEETS}/${id}/values/${TAB}!A1?valueInputOption=USER_ENTERED`, "PUT", { values: [header, ...rows] });

const rgb = (h) => ({ red: parseInt(h.slice(1, 3), 16) / 255, green: parseInt(h.slice(3, 5), 16) / 255, blue: parseInt(h.slice(5, 7), 16) / 255 });
const FILL = { posted: "#D4F4DD", partial: "#FFF4C2", scheduled: "#FFFFFF", waiting: "#F1F3F4", footage: "#FDE2E1", missed: "#FFE0B2" };
const widths = [150, 95, 85, 110, 260, 220, 210, 120, 90, 90, 90, 80, 360];
const requests = [
  { unmergeCells: { range: { sheetId: sid } } },
  { updateSheetProperties: { properties: { sheetId: sid, gridProperties: { frozenRowCount: 1 } }, fields: "gridProperties.frozenRowCount" } },
  ...widths.map((px, i) => ({ updateDimensionProperties: { range: { sheetId: sid, dimension: "COLUMNS", startIndex: i, endIndex: i + 1 }, properties: { pixelSize: px }, fields: "pixelSize" } })),
  { updateDimensionProperties: { range: { sheetId: sid, dimension: "ROWS", startIndex: 0, endIndex: 1 }, properties: { pixelSize: 36 }, fields: "pixelSize" } },
  { updateDimensionProperties: { range: { sheetId: sid, dimension: "ROWS", startIndex: 1, endIndex: rows.length + 1 }, properties: { pixelSize: 150 }, fields: "pixelSize" } },
  {
    repeatCell: {
      range: { sheetId: sid, startRowIndex: 0, endRowIndex: 1 },
      cell: { userEnteredFormat: { backgroundColor: rgb("#0F1013"), textFormat: { bold: true, foregroundColor: rgb("#FFE500") }, verticalAlignment: "MIDDLE" } },
      fields: "userEnteredFormat(backgroundColor,textFormat,verticalAlignment)",
    },
  },
  {
    repeatCell: {
      range: { sheetId: sid, startRowIndex: 1, endRowIndex: rows.length + 1 },
      cell: { userEnteredFormat: { verticalAlignment: "MIDDLE", wrapStrategy: "WRAP" } },
      fields: "userEnteredFormat(verticalAlignment,wrapStrategy)",
    },
  },
  ...status.map((s, i) => ({
    repeatCell: {
      range: { sheetId: sid, startRowIndex: i + 1, endRowIndex: i + 2 },
      cell: { userEnteredFormat: { backgroundColor: rgb(FILL[s]), textFormat: { bold: s === "posted" } } },
      fields: "userEnteredFormat(backgroundColor,textFormat.bold)",
    },
  })),
  // Clear old rows' colours below the table.
  {
    repeatCell: {
      range: { sheetId: sid, startRowIndex: rows.length + 1, endRowIndex: 1000 },
      cell: { userEnteredFormat: {} },
      fields: "userEnteredFormat",
    },
  },
];
await api(`${SHEETS}/${id}:batchUpdate`, "POST", { requests });
const count = (k) => status.filter((s) => s === k).length;
console.log(
  `Calendar sheet updated: ${rows.length} posts · ${count("posted")} posted · ${count("scheduled")} scheduled · ${count("footage")} need footage` +
    (count("missed") ? ` · ${count("missed")} missed` : ""),
);
