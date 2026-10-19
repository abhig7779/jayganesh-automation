import { dayKey } from "./config.mjs";

// Media lives in Cloudflare R2 under one folder per day:
//   day-02/video.mp4   (reels; optional day-02/cover.jpg)
//   day-07/1.jpg       (single image)
//   day-01/1.jpg … 10.jpg  (carousel, in order)
// Instagram's API accepts JPEG images only, so images must be .jpg.

async function head(url) {
  try {
    const r = await fetch(url, { method: "HEAD" });
    return r.ok ? { ok: true, size: Number(r.headers.get("content-length") || 0), type: r.headers.get("content-type") || "" } : { ok: false, status: r.status };
  } catch (err) {
    return { ok: false, status: String(err.message || err) };
  }
}

export async function findMedia(post, baseUrl) {
  if (!baseUrl) return { ready: false, reason: "MEDIA_BASE_URL is not set" };
  const dir = `${baseUrl}/${dayKey(post.day)}`;

  if (post.type === "reel") {
    const video = `${dir}/video.mp4`;
    const v = await head(video);
    if (!v.ok) return { ready: false, reason: `missing ${dayKey(post.day)}/video.mp4` };
    const cover = `${dir}/cover.jpg`;
    const c = await head(cover);
    return { ready: true, video, size: v.size, cover: c.ok ? cover : null };
  }

  if (post.type === "image") {
    const img = `${dir}/1.jpg`;
    const h = await head(img);
    return h.ok ? { ready: true, images: [img] } : { ready: false, reason: `missing ${dayKey(post.day)}/1.jpg` };
  }

  if (post.type === "carousel") {
    const images = [];
    for (let i = 1; i <= 10; i++) {
      const url = `${dir}/${i}.jpg`;
      if (!(await head(url)).ok) break;
      images.push(url);
    }
    if (images.length < 2) return { ready: false, reason: `carousel needs ${dayKey(post.day)}/1.jpg and 2.jpg at least (found ${images.length})` };
    return { ready: true, images };
  }

  return { ready: false, reason: `unknown post type "${post.type}"` };
}
