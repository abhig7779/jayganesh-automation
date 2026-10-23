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
const heroAlt = "A black SUV revealed under the LED light tunnel of the Jay Ganesh detailing studio, headlights on";

// Text chapters on the left, one per third of the scroll.
const chapters = ["Unveiled in our own studio", "Protected with PPF & ceramic coating", "Lit up, polished, ready to drive"];

// Files in /public/video/: {name}.mp4 plus {name}-start/-end posters (.webp + .jpg).
const desktopName = "hero-reveal-desktop"; // 16:9, car on the right
const mobileName = "hero-reveal-mobile"; // 9:16, car in the lower half

// Keep the breakpoint in one place: the <source> media queries, the preloads,
// the CSS (home.css) and HeroReveal must agree.
const phone = "(max-width: 767px)";
const desk = "(min-width: 768px)";
const motion = "(prefers-reduced-motion: no-preference)";
const still = "(prefers-reduced-motion: reduce)";

const exists = (publicPath: string) => fs.existsSync(path.join(process.cwd(), "public", publicPath));

function film(name: string): HeroFilm | null {
  const base = `/video/${name}`;
  if (!exists(`${base}.mp4`) || !exists(`${base}-start.jpg`) || !exists(`${base}-end.jpg`)) return null;
  const webp = (p: string) => (exists(p.replace(/\.jpg$/, ".webp")) ? p.replace(/\.jpg$/, ".webp") : undefined);
  return {
    src: `${base}.mp4`,
    start: { jpg: `${base}-start.jpg`, webp: webp(`${base}-start.jpg`) },
    end: { jpg: `${base}-end.jpg`, webp: webp(`${base}-end.jpg`) },
  };
}

export default function Hero() {
  // Decided at build time: phones get the portrait film once its files exist,
  // else a static, non-pinned hero with the desktop END poster.
  const desktop = film(desktopName)!;
  const mobile = film(mobileName);

  // The poster <img> is the LCP element. Ask for the one this screen will
  // show from <head>, with the same media queries as the <picture> sources.
  const pre = (poster: HeroFilm["start"], media: string) =>
    preload(poster.webp ?? poster.jpg, { as: "image", type: poster.webp ? "image/webp" : undefined, media, fetchPriority: "high" });
  pre(desktop.start, `${desk} and ${motion}`);
  if (mobile) {
    pre(mobile.start, `${phone} and ${motion}`);
    pre(mobile.end, `${phone} and ${still}`);
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
