import { graph, sleep, fingerprint } from "./graph.mjs";

// Instagram content publishing: create a container, wait until it is processed, publish.
export function instagram({ version, token, igUserId }) {
  const g = graph({ version, token });

  async function waitReady(containerId, { timeoutMs = 15 * 60e3, everyMs = 10e3 } = {}) {
    const start = Date.now();
    for (;;) {
      const s = await g.get(containerId, { fields: "status_code,status" });
      if (s.status_code === "FINISHED") return;
      if (s.status_code === "ERROR" || s.status_code === "EXPIRED") throw new Error(`Instagram processing failed: ${s.status || s.status_code}`);
      if (Date.now() - start > timeoutMs) throw new Error("Instagram processing timed out after 15 minutes");
      await sleep(everyMs);
    }
  }

  const extras = (post) => {
    const p = {};
    if (post.collaborators?.length) p.collaborators = JSON.stringify(post.collaborators.slice(0, 3));
    if (post.location_id) p.location_id = post.location_id;
    return p;
  };

  async function alreadyPosted(caption) {
    const fp = fingerprint(caption);
    if (!fp) return null;
    const { data = [] } = await g.get(`${igUserId}/media`, { fields: "id,caption,permalink,timestamp", limit: 15 });
    const since = Date.now() - 3 * 864e5;
    return data.find((m) => new Date(m.timestamp) > since && fingerprint(m.caption) === fp) || null;
  }

  async function publish(post, media, caption) {
    let creation;
    if (post.type === "reel") {
      const p = { media_type: "REELS", video_url: media.video, caption, share_to_feed: "true", ...extras(post) };
      if (media.cover) p.cover_url = media.cover;
      creation = (await g.post(`${igUserId}/media`, p)).id;
      await waitReady(creation);
    } else if (post.type === "image") {
      creation = (await g.post(`${igUserId}/media`, { image_url: media.images[0], caption, ...extras(post) })).id;
      await waitReady(creation, { timeoutMs: 3 * 60e3, everyMs: 4e3 });
    } else {
      const children = [];
      for (const url of media.images) {
        const id = (await g.post(`${igUserId}/media`, { image_url: url, is_carousel_item: "true" })).id;
        await waitReady(id, { timeoutMs: 3 * 60e3, everyMs: 4e3 });
        children.push(id);
      }
      creation = (await g.post(`${igUserId}/media`, { media_type: "CAROUSEL", children: children.join(","), caption, ...extras(post) })).id;
      await waitReady(creation, { timeoutMs: 3 * 60e3, everyMs: 4e3 });
    }
    const { id } = await g.post(`${igUserId}/media_publish`, { creation_id: creation });
    const { permalink } = await g.get(id, { fields: "permalink" });
    if (post.first_comment) await g.post(`${id}/comments`, { message: post.first_comment });
    return { id, url: permalink };
  }

  return { publish, alreadyPosted };
}
