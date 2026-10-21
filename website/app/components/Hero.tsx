import Link from "next/link";
import GoogleRating from "./GoogleRating";
import { WhatsAppButton, IconArrow } from "./ui";
import { imageUrl, media, srcSet, type MediaKey } from "@/lib/media";

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

export default function Hero() {
  const img = media[heroImage];
  const poster = imageUrl(img.src, 1800);

  return (
    <section className="hero-x" aria-labelledby="hero-title">
      <div className="hero-x-media" aria-hidden={heroVideo ? true : undefined}>
        {heroVideo ? (
          <video className="hero-x-bg" src={heroVideo} poster={poster} autoPlay muted loop playsInline preload="metadata" />
        ) : (
          <picture>
            <source media="(max-width: 767px)" srcSet={srcSet(media[heroImageMobile].src) ?? media[heroImageMobile].src} sizes="100vw" />
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              className="hero-x-bg hero-x-kb"
              src={poster}
              srcSet={srcSet(img.src, [800, 1200, 1800, 2400])}
              sizes="100vw"
              alt={img.alt}
              fetchPriority="high"
              decoding="async"
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
