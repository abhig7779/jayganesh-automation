import { preload } from "react-dom";
import GoogleRating from "./GoogleRating";
import { Crumbs } from "./ui";
import { heroSources, type HeroImageKey } from "@/lib/heroes";

// Inner-page hero: the same cinematic language as the home hero, a little
// shorter. Full-bleed, art-directed photo (16:9 on desktop, 4:5 on phones),
// dark gradients so the white copy always reads, and the copy at the bottom
// left: crumbs, eyebrow, H1, lead, the page's actions and the Google rating.
// Always dark, in both themes. Without `image` (legal pages) it renders a
// compact, photo-less version with the same typography.
//
// Server component (it reads public/ at build time), so it lives outside
// ui.tsx, which client components import.

// Keep the breakpoint in one place: <source> media and the preloads must agree.
const mobileQuery = "(max-width: 767px)";
const desktopQuery = "(min-width: 768px)";

// Leads longer than this get a slightly smaller size on desktop. (On phones
// every text lead is clamped to three lines in CSS.)
const LONG_LEAD = 220;

export function PageHero({
  crumbs,
  eyebrow,
  title,
  lead,
  children,
  image,
  imageAlt,
}: {
  crumbs: { label: string; href?: string }[];
  eyebrow?: string;
  title: string;
  lead?: React.ReactNode;
  /** Extra hero content under the lead: notes, price, actions. */
  children?: React.ReactNode;
  /** Kept for compatibility; heroes no longer show a side photo or card. */
  media?: React.ReactNode;
  /** The page's full-bleed photo. Omit for the compact, photo-less hero. */
  image?: HeroImageKey;
  /** Page-specific alt text for the photo (e.g. with a brand name); defaults to the photo's own alt. */
  imageAlt?: string;
}) {
  const longLead = typeof lead === "string" && lead.length > LONG_LEAD;
  const copy = (
    <div className="ph-copy">
      <Crumbs items={crumbs} />
      {eyebrow && <p className="label eyebrow ph-eyebrow">{eyebrow}</p>}
      <h1 className="ph-title">{title}</h1>
      {lead &&
        (typeof lead === "string" ? (
          <p className={longLead ? "ph-lead ph-lead--long" : "ph-lead"}>{lead}</p>
        ) : (
          <div className="ph-lead">{lead}</div>
        ))}
      {children}
    </div>
  );

  if (!image) {
    return (
      <section className="ph ph--plain">
        <div className="wrap ph-inner">{copy}</div>
      </section>
    );
  }

  const src = heroSources(image);
  const wideOnPhones = src.mobile.width > src.mobile.height;

  // The photo is the LCP element: ask for the right file from <head>, with the
  // same srcset/sizes/media as the <picture>, so it is never fetched twice.
  // href is only a fallback for browsers without imagesrcset: the largest candidate.
  const largest = (set: string) => set.split(",").pop()!.trim().split(" ")[0];
  if (src.mobile.webp) preload(largest(src.mobile.webp), { as: "image", type: "image/webp", imageSrcSet: src.mobile.webp, imageSizes: "100vw", media: mobileQuery, fetchPriority: "high" });
  if (src.desktop.webp) preload(largest(src.desktop.webp), { as: "image", type: "image/webp", imageSrcSet: src.desktop.webp, imageSizes: "100vw", media: desktopQuery, fetchPriority: "high" });

  return (
    <section className={["ph ph--photo", wideOnPhones && "ph--wide-mobile"].filter(Boolean).join(" ")} data-hero={image}>
      <div className="ph-media">
        <picture>
          {src.mobile.webp && <source media={mobileQuery} type="image/webp" srcSet={src.mobile.webp} sizes="100vw" />}
          <source media={mobileQuery} srcSet={src.mobile.jpgSrcSet ?? src.mobile.jpg} sizes="100vw" />
          {src.desktop.webp && <source type="image/webp" srcSet={src.desktop.webp} sizes="100vw" />}
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            className="ph-bg"
            src={src.desktop.jpg}
            srcSet={src.desktop.jpgSrcSet}
            sizes="100vw"
            alt={imageAlt ?? src.alt}
            width={src.desktop.width}
            height={src.desktop.height}
            loading="eager"
            fetchPriority="high"
          />
        </picture>
        <span className="ph-shade" aria-hidden />
      </div>

      <div className="wrap ph-inner">
        {copy}
        <div className="ph-rating">
          <GoogleRating variant="hero" />
        </div>
      </div>
    </section>
  );
}

export default PageHero;
