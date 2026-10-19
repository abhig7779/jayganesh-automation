// Fills {{KEY}} placeholders. Values come from config/brand.json, config/offers.json
// and the post's own "vars". A key that is null or unknown is reported as missing.
const RE = /\{\{([A-Z0-9_]+)\}\}/g;

export function render(text, vars) {
  const missing = new Set();
  let out = text || "";
  // Several passes so values that contain placeholders (FOOTER → WHATSAPP) resolve too.
  for (let pass = 0; pass < 5; pass++) {
    let changed = false;
    out = out.replace(RE, (m, key) => {
      const v = vars[key];
      if (v === null || v === undefined || v === "") {
        missing.add(key);
        return m;
      }
      changed = true;
      return String(v);
    });
    if (!changed) break;
  }
  return { text: out, missing: [...missing] };
}

export function renderPost(post, baseVars) {
  const vars = { ...baseVars, ...(post.vars || {}) };
  const parts = {
    caption: render(post.caption, vars),
    first_comment: render(post.first_comment, vars),
  };
  if (post.youtube) {
    parts.yt_title = render(post.youtube.title, vars);
    parts.yt_description = render(
      `${post.youtube.description}\n\n${post.caption.includes("{{HASHTAGS}}") ? "{{HASHTAGS}}" : ""}`.trim(),
      vars,
    );
  }
  const missing = [...new Set(Object.values(parts).flatMap((p) => p.missing))];
  const out = Object.fromEntries(Object.entries(parts).map(([k, v]) => [k, v.text]));
  return { ...out, missing };
}

export const countHashtags = (s) => (s.match(/(^|\s)#[\p{L}\p{N}_]+/gu) || []).length;
