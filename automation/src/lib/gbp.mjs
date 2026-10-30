// Google Business Profile reviews (My Business API v4 for reviews, v1 for accounts/locations).
// Needs an OAuth refresh token with the https://www.googleapis.com/auth/business.manage scope
// and a Google Cloud project that has been granted Business Profile API access.
export function gbp({ clientId, clientSecret, refreshToken }) {
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

  async function api(url, init = {}) {
    const res = await fetch(url, {
      ...init,
      headers: { ...(init.headers || {}), Authorization: `Bearer ${await token()}`, "Content-Type": "application/json" },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = data.error?.message || res.statusText;
      const hint = res.status === 429 || /quota/i.test(msg) ? " — the project may not have Business Profile API access yet (quota 0)" : "";
      throw new Error(`Business Profile API: ${msg} (${res.status})${hint}`);
    }
    return data;
  }

  async function accounts() {
    const d = await api("https://mybusinessaccountmanagement.googleapis.com/v1/accounts");
    return d.accounts || [];
  }

  async function locations(accountName) {
    const d = await api(`https://mybusinessbusinessinformation.googleapis.com/v1/${accountName}/locations?readMask=name,title,storefrontAddress&pageSize=100`);
    return d.locations || [];
  }

  // One location with only the fields in readMask (e.g. "latlng,storefrontAddress"). Read-only.
  async function location(locationPath, readMask) {
    return api(`https://mybusinessbusinessinformation.googleapis.com/v1/${locationPath.replace(/^accounts\/[^/]+\//, "")}?readMask=${encodeURIComponent(readMask)}`);
  }

  // locationPath looks like "accounts/123/locations/456"
  async function reviews(locationPath, { max = 100 } = {}) {
    const out = [];
    let pageToken = "";
    do {
      const q = new URLSearchParams({ pageSize: "50", orderBy: "updateTime desc", ...(pageToken ? { pageToken } : {}) });
      const d = await api(`https://mybusiness.googleapis.com/v4/${locationPath}/reviews?${q}`);
      out.push(...(d.reviews || []));
      pageToken = d.nextPageToken || "";
    } while (pageToken && out.length < max);
    return out;
  }

  async function reply(reviewName, comment) {
    return api(`https://mybusiness.googleapis.com/v4/${reviewName}/reply`, { method: "PUT", body: JSON.stringify({ comment }) });
  }

  // "What's new" post with a photo and one button. locationPath looks like "accounts/123/locations/456".
  // cta is { actionType, url? }; CALL needs no url. Returns the post's resource name and its search URL.
  async function createLocalPost({ location, summary, cta, imageUrl }) {
    const body = {
      languageCode: "en",
      topicType: "STANDARD",
      summary,
      callToAction: cta.actionType === "CALL" ? { actionType: "CALL" } : { actionType: cta.actionType, url: cta.url },
      media: [{ mediaFormat: "PHOTO", sourceUrl: imageUrl }],
    };
    const d = await api(`https://mybusiness.googleapis.com/v4/${location}/localPosts`, { method: "POST", body: JSON.stringify(body) });
    if (!d.name) throw new Error("Business Profile API: post created but no resource name returned");
    return { name: d.name, url: d.searchUrl || "" };
  }

  return { accounts, locations, location, reviews, reply, createLocalPost };
}

export const STARS = { ONE: 1, TWO: 2, THREE: 3, FOUR: 4, FIVE: 5 };
