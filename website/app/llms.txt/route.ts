import { site } from "@/lib/site";
import { reviewsData } from "@/lib/reviews";
import { services } from "@/lib/services";
import { categories } from "@/lib/catalog";
import { serviceAreas } from "@/lib/seo";
import { dealerBrandNames, dealerBrandPath, dealerBrandsIn, dealerCategories, dealerProductName } from "@/lib/dealer-brands";

// /llms.txt (https://llmstxt.org): a plain summary of the business for AI
// assistants. Built at build time from the same data as the site, so the Google
// rating and review count always match lib/reviews.json (refreshed daily).
export const dynamic = "force-static";

export function GET() {
  const u = (p: string) => `${site.url}${p}`;
  const body = `# ${site.name}

> ${site.name} is a car accessories shop and PPF, ceramic coating and car detailing studio at ${site.addressShort}, Pune, Maharashtra, India. It is an authorised dealer in Pune for Autoform seat covers, for ${dealerBrandNames("ppf").join(", ")} paint protection film (PPF), and for ${dealerBrandNames("headlights").join(", ")} headlights. It sells and fits seat covers, lights, Android stereos, speakers, 360° cameras and dashcams, and protects and cleans cars in its own indoor studio. Rated ${site.rating} on Google from ${site.reviews} reviews (updated daily; last refreshed ${reviewsData.updatedAt}). ${site.customers} customers served. Open every day, 10 am to 8 pm.

Prices depend on the car model and are quoted on WhatsApp; the shop does not publish fixed prices online.

## Contact and location

- Name: ${site.name} (short name: ${site.shortName}; also searched as Jai Ganesh Car Accessories. There is one shop, at Kawadipat Toll Naka, Loni Kalbhor; similarly named shops elsewhere are not connected.)
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

## Authorised-dealer brands

- [PPF brands (authorised dealer)](${u(dealerCategories.ppf.hubPath)})
${dealerBrandsIn("ppf").map((b) => `- [${dealerProductName(b)} in Pune](${u(dealerBrandPath(b))}): ${b.note}`).join("\n")}
- [Headlight brands (authorised dealer)](${u(dealerCategories.headlights.hubPath)})
${dealerBrandsIn("headlights").map((b) => `- [${dealerProductName(b)} in Pune](${u(dealerBrandPath(b))}): ${b.note}`).join("\n")}

## Key pages

- [Home](${u("/")})
- [About us](${u("/about-us/")})
- [Contact us](${u("/contact-us/")})
- [Visit us and directions](${u("/visit-us/")})
- [Our work](${u("/our-work/")})
- [Offers](${u("/offers/")})
- [Partnerships for dealerships, fleets and businesses](${u("/partnerships/")})
- [Become a vendor or supplier](${u("/become-a-vendor/")})
- [Autoform seat covers in Pune (authorised dealer)](${u("/autoform-seat-covers-pune/")})
- [Car lights](${u("/car-lights/")}) and [Audio & screens](${u("/audio-screens/")}): category hubs
- [Car accessories near Hadapsar](${u("/car-accessories-hadapsar/")})
- [Car accessories near Kharadi](${u("/car-accessories-kharadi/")})

## Optional

- [Privacy policy](${u("/privacy-policy/")})
- [Terms and conditions](${u("/terms/")})
- [Sitemap](${u("/sitemap.xml")})
`;
  return new Response(body, { headers: { "Content-Type": "text/plain; charset=utf-8" } });
}
