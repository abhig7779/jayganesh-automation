import type { Metadata } from "next";
import { site } from "./site";

// Shared SEO helpers: page metadata (canonical, Open Graph, Twitter) and
// JSON-LD nodes. The business itself is described once, in app/layout.tsx,
// under BUSINESS_ID; every other node points at it by @id.

export const BUSINESS_ID = `${site.url}/#business`;
export const WEBSITE_ID = `${site.url}/#website`;
export const businessRef = { "@id": BUSINESS_ID };

/** Areas the shop serves, eastern Pune first. */
export const serviceAreas = ["Loni Kalbhor", "Hadapsar", "Manjri", "Uruli Kanchan", "Kharadi", "Wagholi", "Fursungi", "Pune"];

export const areaServedLd = serviceAreas.map((a) =>
  a === "Pune"
    ? { "@type": "City", name: "Pune" }
    : { "@type": "Place", name: `${a}, Pune`, containedInPlace: { "@type": "City", name: "Pune" } },
);

/**
 * The page's own share card (Open Graph / Twitter), 1200×630, drawn after the
 * build by scripts/og-cards.mjs from the built page: its H1, hero photo and,
 * on brand pages, the brand logo. "/" → /og/home.jpg,
 * "/car-lights/headlights/" → /og/car-lights/headlights.jpg.
 */
export function ogCard(path: string, alt: string) {
  const slug = path.replace(/^\/+|\/+$/g, "") || "home";
  return { url: `/og/${slug}.jpg`, width: 1200, height: 630, alt };
}

/** "Garware PPF Pune – Authorised Dealer | Jay Ganesh" → "Garware PPF Pune – Authorised Dealer". */
export const shortTitle = (title: string) => title.replace(/\s*\|\s*Jay Ganesh.*$/i, "").trim();

/** Default card alt: the page's short title, then the business and place. */
export const ogAlt = (title: string) => `${shortTitle(title)} – ${site.name}, Loni Kalbhor, Pune`;

/** Homepage card; also the root default (404 page). */
export const ogImage = ogCard("/", `${site.name} – car accessories and PPF studio at Loni Kalbhor, Pune`);

/** Full metadata for a page: unique title, description, canonical, Open Graph and Twitter. */
export function pageMetadata({ title, description, path, imageAlt }: { title: string; description: string; path: string; imageAlt?: string }): Metadata {
  const card = ogCard(path, imageAlt ?? ogAlt(title));
  return {
    title: { absolute: title },
    description,
    alternates: { canonical: path },
    openGraph: {
      type: "website",
      siteName: site.name,
      locale: "en_IN",
      title,
      description,
      url: path,
      images: [card],
    },
    twitter: { card: "summary_large_image", title, description, images: [{ url: card.url, alt: card.alt, width: card.width, height: card.height }] },
  };
}

type Crumb = { name: string; path: string };

/** BreadcrumbList that always starts at Home. */
export function breadcrumbLd(items: Crumb[]) {
  const all = [{ name: "Home", path: "/" }, ...items];
  return {
    "@type": "BreadcrumbList",
    itemListElement: all.map((c, i) => ({ "@type": "ListItem", position: i + 1, name: c.name, item: site.url + c.path })),
  };
}

/** FAQPage built from the same array the visible <Faq> renders, so the text always matches. */
export function faqLd(faqs: { q: string; a: string }[], path: string) {
  return {
    "@type": "FAQPage",
    "@id": `${site.url}${path}#faq`,
    mainEntity: faqs.map((f) => ({ "@type": "Question", name: f.q, acceptedAnswer: { "@type": "Answer", text: f.a } })),
  };
}

export function serviceLd({ name, serviceType, description, path }: { name: string; serviceType: string; description: string; path: string }) {
  return {
    "@type": "Service",
    "@id": `${site.url}${path}#service`,
    name,
    serviceType,
    description,
    url: site.url + path,
    provider: businessRef,
    areaServed: areaServedLd,
  };
}

export function webPageLd({ type = "WebPage", name, description, path }: { type?: string; name: string; description: string; path: string }) {
  // The page's branded share card is its main image, so Google's search thumbnail
  // shows the designed card (logo, title, rating) rather than a plain photo from the page.
  const card = ogCard(path, ogAlt(name));
  const image = { "@type": "ImageObject", "@id": `${site.url}${path}#primaryimage`, url: site.url + card.url, contentUrl: site.url + card.url, width: card.width, height: card.height, caption: card.alt };
  return {
    "@type": type,
    "@id": `${site.url}${path}#webpage`,
    name,
    description,
    url: site.url + path,
    inLanguage: "en-IN",
    isPartOf: { "@id": WEBSITE_ID },
    about: businessRef,
    primaryImageOfPage: image,
    image,
  };
}

/** Wrap nodes in one @graph block. */
export function graph(...nodes: object[]) {
  return { "@context": "https://schema.org", "@graph": nodes };
}
