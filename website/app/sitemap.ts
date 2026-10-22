import type { MetadataRoute } from "next";
import { services } from "@/lib/services";
import { site } from "@/lib/site";

export const dynamic = "force-static";

type Entry = { path: string; priority: number; changeFrequency: "daily" | "weekly" | "monthly" | "yearly" };

// Every route in app/. Add new pages here.
const routes: Entry[] = [
  { path: "/", priority: 1, changeFrequency: "daily" }, // rating and reviews refresh daily
  { path: "/detailing-studio/", priority: 0.9, changeFrequency: "weekly" },
  ...services.map((s): Entry => ({ path: `/${s.slug}/`, priority: 0.9, changeFrequency: "weekly" })),
  { path: "/accessories/", priority: 0.9, changeFrequency: "weekly" },
  { path: "/seat-covers/", priority: 0.9, changeFrequency: "weekly" },
  { path: "/our-work/", priority: 0.7, changeFrequency: "weekly" },
  { path: "/offers/", priority: 0.7, changeFrequency: "weekly" },
  { path: "/visit-us/", priority: 0.7, changeFrequency: "monthly" },
  { path: "/about-us/", priority: 0.7, changeFrequency: "monthly" },
  { path: "/contact-us/", priority: 0.7, changeFrequency: "monthly" },
  { path: "/partnerships/", priority: 0.6, changeFrequency: "monthly" },
  { path: "/become-a-vendor/", priority: 0.6, changeFrequency: "monthly" },
  { path: "/privacy-policy/", priority: 0.3, changeFrequency: "yearly" },
  { path: "/terms/", priority: 0.3, changeFrequency: "yearly" },
];

export default function sitemap(): MetadataRoute.Sitemap {
  const lastModified = new Date(); // build date
  return routes.map((r) => ({ url: site.url + r.path, lastModified, changeFrequency: r.changeFrequency, priority: r.priority }));
}
