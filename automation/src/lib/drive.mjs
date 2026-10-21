// Google Drive (read-only): lists the day folders the team drops content into and downloads files.
// Auth: OAuth refresh token for marutikalbhor760@gmail.com with the drive.readonly scope
// (the same token can also carry business.manage for review replies).
export function drive({ clientId, clientSecret, refreshToken }) {
  let accessToken = null;

  async function token() {
    if (accessToken) return accessToken;
    const res = await fetch("https://oauth2.googleapis.com/token", {
      method: "POST",
      body: new URLSearchParams({ client_id: clientId, client_secret: clientSecret, refresh_token: refreshToken, grant_type: "refresh_token" }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(`Google token refresh failed: ${data.error_description || data.error || res.status}`);
    accessToken = data.access_token;
    return accessToken;
  }

  async function list(q) {
    const out = [];
    let pageToken = "";
    do {
      const params = new URLSearchParams({
        q,
        fields: "nextPageToken,files(id,name,mimeType,size,md5Checksum,modifiedTime)",
        pageSize: "200",
        supportsAllDrives: "true",
        includeItemsFromAllDrives: "true",
        ...(pageToken ? { pageToken } : {}),
      });
      const res = await fetch(`https://www.googleapis.com/drive/v3/files?${params}`, { headers: { Authorization: `Bearer ${await token()}` } });
      const d = await res.json();
      if (!res.ok) throw new Error(`Drive API: ${d.error?.message || res.statusText} (${res.status})`);
      out.push(...(d.files || []));
      pageToken = d.nextPageToken || "";
    } while (pageToken);
    return out;
  }

  const children = (folderId) => list(`'${folderId}' in parents and trashed = false`);

  async function download(fileId) {
    const res = await fetch(`https://www.googleapis.com/drive/v3/files/${fileId}?alt=media&supportsAllDrives=true`, {
      headers: { Authorization: `Bearer ${await token()}` },
    });
    if (!res.ok) throw new Error(`Drive download failed (${res.status})`);
    return Buffer.from(await res.arrayBuffer());
  }

  // Writing needs the drive.file scope (files this app creates).
  async function createFolder(name, parentId) {
    const res = await fetch("https://www.googleapis.com/drive/v3/files?supportsAllDrives=true&fields=id,name,webViewLink", {
      method: "POST",
      headers: { Authorization: `Bearer ${await token()}`, "Content-Type": "application/json" },
      body: JSON.stringify({ name, mimeType: FOLDER, ...(parentId ? { parents: [parentId] } : {}) }),
    });
    const d = await res.json();
    if (!res.ok) throw new Error(`Drive create "${name}": ${d.error?.message || res.statusText} (${res.status})`);
    return d;
  }

  async function createText(name, parentId, text) {
    const boundary = "jg" + Math.random().toString(36).slice(2);
    const meta = { name, mimeType: "application/vnd.google-apps.document", parents: [parentId] };
    const body = `--${boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n${JSON.stringify(meta)}\r\n--${boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n${text}\r\n--${boundary}--`;
    const res = await fetch("https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,webViewLink", {
      method: "POST",
      headers: { Authorization: `Bearer ${await token()}`, "Content-Type": `multipart/related; boundary=${boundary}` },
      body,
    });
    const d = await res.json();
    if (!res.ok) throw new Error(`Drive create "${name}": ${d.error?.message || res.statusText} (${res.status})`);
    return d;
  }

  return { children, download, createFolder, createText };
}

export const FOLDER = "application/vnd.google-apps.folder";
