// One-time Google sign-in that gets a refresh token with every permission the automation needs
// (reviews/posts/photos, Drive, YouTube, Analytics, Search Console). Nothing is typed into a web page:
// this opens a tiny local server to catch Google's redirect, swaps the code for tokens and saves the
// refresh token to a private file. Run:  node --env-file=.env src/get-google-token.mjs
// Needs: GBP_CLIENT_ID + GBP_CLIENT_SECRET in .env, and http://localhost:8765 added as an
// "Authorised redirect URI" on that OAuth client. Publish the OAuth app to "In production" first, or the
// token expires after 7 days (Testing mode).
import http from "node:http";
import { writeFileSync, mkdirSync, chmodSync } from "node:fs";
import { homedir } from "node:os";
import path from "node:path";

const e = process.env;
const PORT = 8765;
const REDIRECT = `http://localhost:${PORT}`;
const SCOPES = [
  "https://www.googleapis.com/auth/business.manage",
  "https://www.googleapis.com/auth/drive",
  "https://www.googleapis.com/auth/youtube",
  "https://www.googleapis.com/auth/analytics.readonly",
  "https://www.googleapis.com/auth/webmasters",
];
if (!e.GBP_CLIENT_ID || !e.GBP_CLIENT_SECRET) throw new Error("GBP_CLIENT_ID / GBP_CLIENT_SECRET are not set");

const url = "https://accounts.google.com/o/oauth2/v2/auth?" + new URLSearchParams({
  client_id: e.GBP_CLIENT_ID, redirect_uri: REDIRECT, response_type: "code", access_type: "offline",
  prompt: "consent", include_granted_scopes: "true", scope: SCOPES.join(" "),
});
console.log("Open this link, choose the Google account that manages Jay Ganesh, and click Allow:\n\n" + url + "\n");

const server = http.createServer(async (req, res) => {
  const q = new URL(req.url, REDIRECT).searchParams;
  if (q.get("error")) { res.end("Google said: " + q.get("error")); console.log("Sign-in refused:", q.get("error")); return server.close(); }
  if (!q.get("code")) { res.end("waiting"); return; }
  const r = await fetch("https://oauth2.googleapis.com/token", { method: "POST", body: new URLSearchParams({
    code: q.get("code"), client_id: e.GBP_CLIENT_ID, client_secret: e.GBP_CLIENT_SECRET, redirect_uri: REDIRECT, grant_type: "authorization_code" }) });
  const t = await r.json();
  if (!r.ok || !t.refresh_token) { res.end("Token exchange failed, see the terminal."); console.log("Failed:", t.error, t.error_description || "(no refresh token: remove the app at myaccount.google.com/permissions and retry)"); return server.close(); }
  const dir = path.join(homedir(), ".jayganesh"); mkdirSync(dir, { recursive: true });
  const file = path.join(dir, "google-refresh-token.txt"); writeFileSync(file, t.refresh_token + "\n"); chmodSync(file, 0o600);
  res.end("All set. You can close this tab.");
  console.log("Saved the refresh token to", file, "\nPermissions granted:\n " + t.scope.split(" ").join("\n "));
  server.close();
});
server.listen(PORT, "127.0.0.1");
