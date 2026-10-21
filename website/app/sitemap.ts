import type { MetadataRoute } from "next";
import { services } from "@/lib/services";
import { site } from "@/lib/site";

export const dynamic = "force-static";

export default function sitemap(): MetadataRoute.Sitemap {
  const paths = [
    "/", "/detailing-studio/", ...services.map((s) => `/${s.slug}/`),
    "/accessories/", "/seat-covers/", "/our-work/", "/offers/", "/visit-us/",
    "/about-us/", "/contact-us/", "/partnerships/", "/become-a-vendor/",
    "/privacy-policy/", "/terms/",
  ];
  return paths.map((p) => ({ url: site.url + p, changeFrequency: "weekly", priority: p === "/" ? 1 : 0.7 }));
}
