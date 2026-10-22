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

export const ogImage = {
  url: "/images/gen/hero-desktop.jpg",
  width: 1600,
  height: 900,
  alt: `${site.name}, Kawadipat Toll Naka, Loni Kalbhor, Pune`,
};

/** Full metadata for a page: unique title, description, canonical, Open Graph and Twitter. */
export function pageMetadata({ title, description, path }: { title: string; description: string; path: string }): Metadata {
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
      images: [ogImage],
    },
    twitter: { card: "summary_large_image", title, description, images: [ogImage.url] },
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
  return {
    "@type": type,
    "@id": `${site.url}${path}#webpage`,
    name,
    description,
    url: site.url + path,
    inLanguage: "en-IN",
    isPartOf: { "@id": WEBSITE_ID },
    about: businessRef,
  };
}

/** Wrap nodes in one @graph block. */
export function graph(...nodes: object[]) {
  return { "@context": "https://schema.org", "@graph": nodes };
}
