import Link from "next/link";
import { preload } from "react-dom";
import GoogleRating from "./GoogleRating";
import { WhatsAppButton, IconArrow } from "./ui";
import { imageUrl, media, srcSet, webpSrcSet, type MediaKey } from "@/lib/media";

// Home hero: full-bleed studio shot (or film, once it exists), with the
// headline, one line and two actions at the bottom left.
// Change these three constants to change the hero.
export const heroHeadline = "Protection, perfected.";
const heroLine = "PPF, ceramic coating and detailing in our new studio, by the team eastern Pune already trusts.";
// Set to "/video/hero.mp4" once the studio film is in /public/video/.
const heroVideo: string | null = null;
// Dark, low-key shot: the left side is near black, so the text sits on it.
const heroImage: MediaKey = "studioHero";
// Portrait version for phones, so the car isn't cropped on a tall screen.
const heroImageMobile: MediaKey = "studioHeroMobile";

// Phones get the portrait shot, desktops the wide one. Keep the breakpoint in
// one place: the <source> media queries and the preloads must agree.
const mobileQuery = "(max-width: 767px)";
const desktopQuery = "(min-width: 768px)";

export default function Hero() {
  const img = media[heroImage];
  const mobile = media[heroImageMobile];
  const poster = imageUrl(img.src, 1600);
  // The hero photo is the LCP element: WebP, at most 800px wide on phones.
  const mobileWebp = webpSrcSet(mobile.src, 800);
  const desktopWebp = webpSrcSet(img.src);

  // Ask for the right hero file from <head>, before the body is parsed. Same
  // srcset/sizes/media as the <picture> below, so it is never fetched twice.
  // href is only a fallback for browsers without imagesrcset: the largest candidate.
  const largest = (set: string) => set.split(",").pop()!.trim().split(" ")[0];
  if (!heroVideo) {
    if (mobileWebp) preload(largest(mobileWebp), { as: "image", type: "image/webp", imageSrcSet: mobileWebp, imageSizes: "100vw", media: mobileQuery, fetchPriority: "high" });
    if (desktopWebp) preload(largest(desktopWebp), { as: "image", type: "image/webp", imageSrcSet: desktopWebp, imageSizes: "100vw", media: desktopQuery, fetchPriority: "high" });
  }

  return (
    <section className="hero-x" aria-labelledby="hero-title">
      <div className="hero-x-media" aria-hidden={heroVideo ? true : undefined}>
        {heroVideo ? (
          <video className="hero-x-bg" src={heroVideo} poster={poster} autoPlay muted loop playsInline preload="metadata" />
        ) : (
          <picture>
            {mobileWebp && <source media={mobileQuery} type="image/webp" srcSet={mobileWebp} sizes="100vw" />}
            <source media={mobileQuery} srcSet={imageUrl(mobile.src, 800)} />
            {desktopWebp && <source type="image/webp" srcSet={desktopWebp} sizes="100vw" />}
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              className="hero-x-bg hero-x-kb"
              src={poster}
              srcSet={srcSet(img.src)}
              sizes="100vw"
              alt={img.alt}
              width={1600}
              height={900}
              loading="eager"
              fetchPriority="high"
            />
          </picture>
        )}
        <span className="hero-x-shade" aria-hidden />
      </div>

      <div className="wrap hero-x-inner">
        <div className="hero-x-copy">
          <p className="hero-x-kicker">Loni Kalbhor · Pune</p>
          <h1 id="hero-title">{heroHeadline}</h1>
          <p className="hero-x-line">{heroLine}</p>
          <div className="hero-x-actions">
            <WhatsAppButton message="Hi Jay Ganesh, I'd like a quote for my car.">Get a quote on WhatsApp</WhatsAppButton>
            <Link className="btn btn--ghost" href="/our-work/">See our work <IconArrow /></Link>
          </div>
          <GoogleRating variant="hero" />
        </div>
      </div>

      <a className="hero-x-cue" href="#studio">
        <span className="sr-only">Scroll to the studio services</span>
        <span className="hero-x-cue-line" aria-hidden />
      </a>
    </section>
  );
}
