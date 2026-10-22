import { site } from "@/lib/site";
import { reviewsData } from "@/lib/reviews";
import { services } from "@/lib/services";
import { categories } from "@/lib/catalog";
import { serviceAreas } from "@/lib/seo";

// /llms.txt (https://llmstxt.org): a plain summary of the business for AI
// assistants. Built at build time from the same data as the site, so the Google
// rating and review count always match lib/reviews.json (refreshed daily).
export const dynamic = "force-static";

export function GET() {
  const u = (p: string) => `${site.url}${p}`;
  const body = `# ${site.name}

> ${site.name} is a car accessories shop and PPF, ceramic coating and car detailing studio at ${site.addressShort}, Pune, Maharashtra, India. It sells and fits seat covers, lights, Android stereos, speakers, 360° cameras and dashcams, and protects and cleans cars in its own indoor studio. Rated ${site.rating} on Google from ${site.reviews} reviews (updated daily; last refreshed ${reviewsData.updatedAt}). ${site.customers} customers served. Open every day, 10 am to 8 pm.

Prices depend on the car model and are quoted on WhatsApp; the shop does not publish fixed prices online.

## Contact and location

- Name: ${site.name} (short name: ${site.shortName})
- Address: ${site.address}, India
- Phone and WhatsApp: ${site.phoneDisplay}
- WhatsApp link: https://wa.me/${site.whatsappNumber}
- Hours: open 7 days a week, 10:00 am to 8:00 pm
- Google Maps: ${site.mapsHref}
- Areas served: ${serviceAreas.join(", ")}
- Instagram: ${site.instagramHref}
- YouTube: ${site.youtubeHref}
- Facebook: ${site.facebookHref}

## Detailing studio services

- [Detailing studio](${u("/detailing-studio/")}): overview of PPF, ceramic coating, detailing and new-car protection
${services.map((s) => `- [${s.menuLabel}](${u(`/${s.slug}/`)}): ${s.description}`).join("\n")}

## Car accessories

- [All accessories](${u("/accessories/")}): accessories chosen for your car model and fitted at the shop
${categories.map((c) => `- [${c.name}](${u(c.href ?? `/accessories/#${c.key}`)}): ${c.blurb}`).join("\n")}

## Key pages

- [Home](${u("/")})
- [About us](${u("/about-us/")})
- [Contact us](${u("/contact-us/")})
- [Visit us and directions](${u("/visit-us/")})
- [Our work](${u("/our-work/")})
- [Offers](${u("/offers/")})
- [Partnerships for dealerships, fleets and businesses](${u("/partnerships/")})
- [Become a vendor or supplier](${u("/become-a-vendor/")})

## Optional

- [Privacy policy](${u("/privacy-policy/")})
- [Terms and conditions](${u("/terms/")})
- [Sitemap](${u("/sitemap.xml")})
`;
  return new Response(body, { headers: { "Content-Type": "text/plain; charset=utf-8" } });
}
