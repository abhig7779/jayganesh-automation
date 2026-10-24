import { createHash } from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import Link from "next/link";
import { preload } from "react-dom";
import GoogleRating from "./GoogleRating";
import HeroReveal, { type HeroFilm } from "./HeroReveal";
import { WhatsAppButton, IconArrow } from "./ui";

// Home hero: a scroll-scrubbed film of the studio reveal. The section pins
// while you scroll and the scroll drives the film's playback time (see
// HeroReveal.tsx). Static END poster for reduced motion and without JS.
// Change these constants to change the hero.
export const heroHeadline = "Premium PPF & car accessories in Pune";
/** Brand tagline (not shown in the hero any more; kept for reuse). */
export const heroTagline = "Protection, perfected.";
const heroKicker = "Loni Kalbhor · Pune · Authorised dealer";
const heroLine = "PPF, ceramic coating, detailing and accessories, fitted by the Jay Ganesh team at our Loni Kalbhor studio.";
const heroAlt = "PPF being fitted, scratches self-healing and water beading on a black G-Wagon in the Jay Ganesh studio";

// Text chapters, one per shot of the film (PPF fitting, self-healing, beading, hero).
// Their scroll ranges live in HeroReveal (cuts) and must match the film's cuts.
const chapters = [
  "Invisible armour: PPF, fitted by hand",
  "Scratches? The film heals itself",
  "Water just rolls off with ceramic",
  "Protected. Ready to drive.",
];

// Files in /public/video/: {name}.mp4 plus {name}-start/-end posters (.webp + .jpg).
const desktopName = "hero-story-desktop"; // 16:9, 22.2 s: four G-Wagon shots
const mobileName = "hero-story-mobile"; // 9:16 version of the same film

// Keep the breakpoint in one place: the <source> media queries, the preloads,
// the CSS (home.css) and HeroReveal must agree.
const phone = "(max-width: 767px)";
const desk = "(min-width: 768px)";
const motion = "(prefers-reduced-motion: no-preference)";
const still = "(prefers-reduced-motion: reduce)";
// Phone posters: 720px file at 2x DPR and up, the 540px copy below (same split as HeroReveal's <source>s).
const hiDpi = "(min-resolution: 2dppx)";
const loDpi = "(max-resolution: 1.99dppx)";

const exists = (publicPath: string) => fs.existsSync(path.join(process.cwd(), "public", publicPath));
/** Short content hash, so a re-encoded film gets a new URL and no browser or CDN keeps the old one. */
const version = (publicPath: string) =>
  createHash("sha1").update(fs.readFileSync(path.join(process.cwd(), "public", publicPath))).digest("hex").slice(0, 8);

function film(name: string): HeroFilm | null {
  const base = `/video/${name}`;
  if (!exists(`${base}.mp4`) || !exists(`${base}-start.jpg`) || !exists(`${base}-end.jpg`)) return null;
  const webp = (p: string) => (exists(p.replace(/\.jpg$/, ".webp")) ? p.replace(/\.jpg$/, ".webp") : undefined);
  // Phone posters also have a 540px-wide copy ({name}-start-540.webp, ~27 KB vs ~56 KB).
  const small = (p: string) => (exists(p.replace(/\.jpg$/, "-540.webp")) ? p.replace(/\.jpg$/, "-540.webp") : undefined);
  return {
    src: `${base}.mp4?v=${version(`${base}.mp4`)}`,
    start: { jpg: `${base}-start.jpg`, webp: webp(`${base}-start.jpg`), webpSmall: small(`${base}-start.jpg`) },
    end: { jpg: `${base}-end.jpg`, webp: webp(`${base}-end.jpg`), webpSmall: small(`${base}-end.jpg`) },
  };
}

export default function Hero() {
  // Decided at build time: phones get the portrait film once its files exist,
  // else a static, non-pinned hero with the desktop END poster.
  const desktop = film(desktopName)!;
  const mobile = film(mobileName);

  // The poster <img> is the LCP element. Ask for the one this screen will
  // show from <head>, with the same media queries as the <picture> sources.
  // Phone posters with a 540px copy: same split by DPR as the <picture> sources.
  const pre = (poster: HeroFilm["start"], media: string, phonePoster = false) => {
    if (phonePoster && poster.webp && poster.webpSmall) {
      preload(poster.webp, { as: "image", type: "image/webp", media: `${media} and ${hiDpi}`, fetchPriority: "high" });
      preload(poster.webpSmall, { as: "image", type: "image/webp", media: `${media} and ${loDpi}`, fetchPriority: "high" });
      return;
    }
    preload(poster.webp ?? poster.jpg, { as: "image", type: poster.webp ? "image/webp" : undefined, media, fetchPriority: "high" });
  };
  pre(desktop.start, `${desk} and ${motion}`);
  if (mobile) {
    pre(mobile.start, `${phone} and ${motion}`, true);
    pre(mobile.end, `${phone} and ${still}`, true);
    pre(desktop.end, `${desk} and ${still}`);
  } else {
    pre(desktop.end, `${phone}, ${still}`);
  }

  return (
    <HeroReveal
      desktop={desktop}
      mobile={mobile}
      alt={heroAlt}
      kicker={heroKicker}
      title={<h1 id="hero-title">{heroHeadline}</h1>}
      chapters={chapters}
      queries={{ phone, still }}
    >
      <p className="hero-x-line">{heroLine}</p>
      <div className="hero-v-foot">
        <div className="hero-x-actions">
          <WhatsAppButton message="Hi Jay Ganesh, I'd like a quote for my car.">Get a quote on WhatsApp</WhatsAppButton>
          <Link className="btn btn--ghost" href="/our-work/">See our work <IconArrow /></Link>
        </div>
        <GoogleRating variant="hero" />
      </div>
    </HeroReveal>
  );
}
