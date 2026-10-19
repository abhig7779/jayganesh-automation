// YouTube Shorts upload (YouTube Data API v3, resumable upload).
// A vertical video of 3 minutes or less with #Shorts in the title is treated as a Short.
export function youtube({ clientId, clientSecret, refreshToken, privacy }) {
  let accessToken = null;

  async function token() {
    if (accessToken) return accessToken;
    const res = await fetch("https://oauth2.googleapis.com/token", {
      method: "POST",
      body: new URLSearchParams({ client_id: clientId, client_secret: clientSecret, refresh_token: refreshToken, grant_type: "refresh_token" }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(`YouTube token refresh failed: ${data.error_description || data.error || res.status}`);
    accessToken = data.access_token;
    return accessToken;
  }

  async function api(url, init = {}) {
    const res = await fetch(url, { ...init, headers: { ...(init.headers || {}), Authorization: `Bearer ${await token()}` } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(`YouTube API: ${data.error?.message || res.statusText} (${res.status})`);
    return data;
  }

  async function channel() {
    const d = await api("https://www.googleapis.com/youtube/v3/channels?part=snippet,contentDetails&mine=true");
    return d.items?.[0] || null;
  }

  async function alreadyPosted(title) {
    try {
      const ch = await channel();
      const uploads = ch?.contentDetails?.relatedPlaylists?.uploads;
      if (!uploads) return null;
      const d = await api(`https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=15&playlistId=${uploads}`);
      const since = Date.now() - 3 * 864e5;
      const hit = (d.items || []).find((i) => i.snippet.title === title && new Date(i.snippet.publishedAt) > since);
      return hit ? { id: hit.snippet.resourceId.videoId, url: `https://youtube.com/shorts/${hit.snippet.resourceId.videoId}` } : null;
    } catch {
      return null; // readonly scope not granted: skip the duplicate check
    }
  }

  async function publish(post, media, { title, description }) {
    const file = await fetch(media.video);
    if (!file.ok) throw new Error(`Could not download ${media.video} (${file.status})`);
    const bytes = Buffer.from(await file.arrayBuffer());

    const meta = {
      snippet: { title: title.slice(0, 100), description: description.slice(0, 5000), tags: post.youtube.tags || [], categoryId: "2" },
      status: { privacyStatus: privacy, selfDeclaredMadeForKids: false, containsSyntheticMedia: Boolean(post.ai) },
    };
    const init = await fetch("https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${await token()}`,
        "Content-Type": "application/json; charset=UTF-8",
        "X-Upload-Content-Length": String(bytes.length),
        "X-Upload-Content-Type": "video/mp4",
      },
      body: JSON.stringify(meta),
    });
    if (!init.ok) {
      const d = await init.json().catch(() => ({}));
      throw new Error(`YouTube upload start failed: ${d.error?.message || init.statusText} (${init.status})`);
    }
    const uploadUrl = init.headers.get("location");
    const up = await fetch(uploadUrl, { method: "PUT", headers: { "Content-Type": "video/mp4", "Content-Length": String(bytes.length) }, body: bytes });
    const d = await up.json().catch(() => ({}));
    if (!up.ok || !d.id) throw new Error(`YouTube upload failed: ${d.error?.message || up.statusText} (${up.status})`);
    return { id: d.id, url: `https://youtube.com/shorts/${d.id}`, privacy: d.status?.privacyStatus };
  }

  return { publish, alreadyPosted, channel };
}
