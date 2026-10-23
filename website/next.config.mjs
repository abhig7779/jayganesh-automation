import fs from "node:fs";
import path from "node:path";

// Purpose-made renders (public/images/gen/new-*.jpg) that are ready: the JPEG
// and its 480/800/1024 WebP copies all exist. lib/media.ts uses each one once
// it is listed here and a close local photo until then, so the build never
// breaks while files are still being made. Checked at build time; passed as an
// env value because lib/media.ts is bundled for the browser too.
const genDir = path.join(process.cwd(), "public", "images", "gen");
const genReady = fs
  .readdirSync(genDir)
  .filter((f) => /^new-.+\.jpg$/.test(f))
  .map((f) => f.replace(/\.jpg$/, ""))
  .filter((name) => [480, 800, 1024].every((w) => fs.existsSync(path.join(genDir, `${name}-${w}.webp`))));

/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // Every page is static, so the site is exported to plain files in out/ and
  // served by Cloudflare Pages without a Node server.
  output: "export",
  trailingSlash: true,
  images: { unoptimized: true },
  env: { JG_GEN_READY: genReady.join(",") },
  // Inline the CSS into each page's HTML instead of <link>ing 5 render-blocking
  // stylesheets (production builds only).
  experimental: { inlineCss: true },
};

export default nextConfig;
