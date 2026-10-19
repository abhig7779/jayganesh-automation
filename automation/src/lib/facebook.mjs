import { graph, fingerprint } from "./graph.mjs";

// Facebook Page posting with a Page token derived from the system-user token.
export function facebook({ version, token, pageId }) {
  let pageToken = null;
  const g = graph({ version, token });

  async function pg() {
    if (!pageToken) pageToken = (await g.get(pageId, { fields: "access_token" })).access_token;
    if (!pageToken) throw new Error("Could not get a Page access token. Is the Page assigned to the system user?");
    return graph({ version, token: pageToken });
  }

  async function alreadyPosted(caption) {
    const fp = fingerprint(caption);
    if (!fp) return null;
    const p = await pg();
    const { data = [] } = await p.get(`${pageId}/posts`, { fields: "id,message,permalink_url,created_time", limit: 15 });
    const since = Date.now() - 3 * 864e5;
    const hit = data.find((m) => new Date(m.created_time) > since && fingerprint(m.message) === fp);
    return hit ? { id: hit.id, url: hit.permalink_url } : null;
  }

  async function publish(post, media, caption) {
    const p = await pg();
    if (post.type === "reel") {
      const { id } = await p.postVideo(`${pageId}/videos`, { file_url: media.video, description: caption, title: post.title, published: "true" });
      return { id, url: `https://www.facebook.com/${pageId}/videos/${id}` };
    }
    if (media.images.length === 1) {
      const { post_id, id } = await p.post(`${pageId}/photos`, { url: media.images[0], caption, published: "true" });
      return { id: post_id || id, url: `https://www.facebook.com/${post_id || id}` };
    }
    const attached = [];
    for (const url of media.images) {
      const { id } = await p.post(`${pageId}/photos`, { url, published: "false" });
      attached.push(JSON.stringify({ media_fbid: id }));
    }
    const params = { message: caption };
    attached.forEach((a, i) => (params[`attached_media[${i}]`] = a));
    const { id } = await p.post(`${pageId}/feed`, params);
    return { id, url: `https://www.facebook.com/${id}` };
  }

  return { publish, alreadyPosted };
}
