// Minimal Meta Graph API client (Instagram + Facebook Page).
export function graph({ version, token }) {
  const base = `https://graph.facebook.com/${version}`;

  async function call(method, path, params = {}, host = base) {
    const body = new URLSearchParams({ ...params, access_token: params.access_token || token });
    const url = method === "GET" ? `${host}/${path}?${body}` : `${host}/${path}`;
    const res = await fetch(url, method === "GET" ? {} : { method, body });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.error) {
      const e = data.error || {};
      const err = new Error(`Meta API ${path}: ${e.message || res.statusText} (code ${e.code ?? res.status}${e.error_subcode ? "/" + e.error_subcode : ""})`);
      err.meta = e;
      throw err;
    }
    return data;
  }

  return {
    get: (path, params) => call("GET", path, params),
    post: (path, params) => call("POST", path, params),
    postVideo: (path, params) => call("POST", path, params, `https://graph-video.facebook.com/${version}`),
  };
}

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// First line of a caption, used to spot a post that already went out
// (for example if a run posted but could not save the log).
export const fingerprint = (caption) => (caption || "").split("\n").find((l) => l.trim())?.trim().slice(0, 60) || "";
