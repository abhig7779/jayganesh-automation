import type { Metadata, Viewport } from "next";
import { Hanken_Grotesk, Hind, Sora } from "next/font/google";
import "./brand.css";
import "./styles/base.css";
import "./styles/chrome.css";
import "./styles/components.css";
import "./styles/pages.css";
import "./styles/home.css";
import Header from "./components/Header";
import Footer from "./components/Footer";
import { JsonLd } from "./components/ui";
import WhatsAppWidget from "./components/WhatsAppWidget";
import HelpWidget from "./components/HelpWidget";
import Analytics from "./components/Analytics";
import { themeScript } from "./components/ThemeToggle";
import { site } from "@/lib/site";
import { reviewsData } from "@/lib/reviews";
import { services } from "@/lib/services";
import { categories } from "@/lib/catalog";
import { dealerBrandNames, dealerBrandPath, dealerBrands, dealerCategories, dealerProductName } from "@/lib/dealer-brands";
import { BUSINESS_ID, WEBSITE_ID, areaServedLd, graph, ogImage } from "@/lib/seo";

// Headings: Sora, a precise, slightly technical sans set in sentence case.
// Body: Hanken Grotesk, easy to read at small sizes. Hind covers Marathi and
// Hindi text, so Devanagari never falls back to a system font.
const sora = Sora({ subsets: ["latin"], weight: ["400", "500", "600", "700"], variable: "--font-sora", display: "swap" });
const hanken = Hanken_Grotesk({ subsets: ["latin"], weight: ["400", "500", "600"], variable: "--font-hanken", display: "swap" });
const hind = Hind({ subsets: ["devanagari"], weight: ["400", "600"], variable: "--font-hind", display: "swap" });

const homeTitle = "Car Accessories in Pune & PPF Studio | Jay Ganesh";
const homeDescription = `Car accessories and car decor: seat covers, lights, stereos, dashcams, plus a PPF and detailing studio at Loni Kalbhor, Pune. Rated ${site.rating} on Google.`;

export const metadata: Metadata = {
  metadataBase: new URL(site.url),
  title: {
    default: homeTitle,
    template: `%s | ${site.name}`,
  },
  description: homeDescription,
  applicationName: site.name,
  icons: { icon: [{ url: "/favicon.ico", sizes: "any" }, { url: "/icon-192.png", type: "image/png", sizes: "192x192" }], apple: "/apple-touch-icon.png" },
  openGraph: {
    type: "website",
    siteName: site.name,
    locale: "en_IN",
    title: homeTitle,
    description: homeDescription,
    url: "/",
    images: [ogImage],
  },
  twitter: { card: "summary_large_image", title: homeTitle, description: homeDescription, images: [{ url: ogImage.url, alt: ogImage.alt, width: ogImage.width, height: ogImage.height }] },
  alternates: { canonical: "/" },
};

export const viewport: Viewport = {
  themeColor: [
    { media: "(prefers-color-scheme: dark)", color: "#0f1013" },
    { media: "(prefers-color-scheme: light)", color: "#f6f6f4" },
  ],
  colorScheme: "dark light",
};

const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

// The one canonical description of the business. Every other JSON-LD node on the
// site points here with { "@id": BUSINESS_ID } instead of repeating these details.
const business = {
  "@type": ["AutoPartsStore", "AutoRepair"],
  "@id": BUSINESS_ID,
  name: site.name,
  // Other ways people spell and search for the shop (Google search data, Oct 2026).
  alternateName: [site.shortName, "Jai Ganesh Car Accessories", "Jayganesh Car Accessories", "Jay Ganesh Car Decor"],
  description:
    `Car accessories and car decor shop with a PPF, ceramic coating and detailing studio at Kawadipat Toll Naka on the Pune–Solapur Road, Loni Kalbhor, Pune. Authorised dealer of Autoform seat covers, of ${dealerBrandNames("ppf").join(", ")} paint protection film, and of ${dealerBrandNames("headlights").join(", ")} headlights.`,
  // Brands the shop is an authorised dealer for (lib/dealer-brands.ts), plus Autoform.
  brand: [
    { "@type": "Brand", name: "Autoform" },
    ...[...new Map(dealerBrands.map((b) => [b.name, b])).values()].map((b) => ({ "@type": "Brand", name: b.name, ...(b.url ? { url: b.url } : {}) })),
  ],
  slogan: site.tagline,
  url: `${site.url}/`,
  logo: `${site.url}/logo.png`,
  // The studio photo itself (no text over it), then the logo.
  image: [`${site.url}/images/gen/hero-desktop.jpg`, `${site.url}/logo.png`],
  telephone: site.phoneE164,
  address: {
    "@type": "PostalAddress",
    streetAddress: "Kawadipat, Toll Naka, Pune–Solapur Road",
    addressLocality: site.locality,
    addressRegion: site.region,
    ...(site.postalCode ? { postalCode: site.postalCode } : {}),
    addressCountry: "IN",
  },
  // The shop's Google Maps pin.
  geo: { "@type": "GeoCoordinates", latitude: 18.4929465, longitude: 73.99336 },
  hasMap: site.mapsHref,
  openingHoursSpecification: [{
    "@type": "OpeningHoursSpecification",
    dayOfWeek: days,
    opens: site.openingHours.opens,
    closes: site.openingHours.closes,
  }],
  areaServed: areaServedLd,
  // Live from lib/reviews.json (refreshed daily from Google).
  aggregateRating: {
    "@type": "AggregateRating",
    ratingValue: Number(reviewsData.rating.toFixed(1)),
    reviewCount: Math.round(reviewsData.count),
    bestRating: 5,
    worstRating: 1,
  },
  contactPoint: {
    "@type": "ContactPoint",
    telephone: site.phoneE164,
    contactType: "customer service",
    areaServed: "IN",
    availableLanguage: ["English", "Hindi", "Marathi"],
  },
  sameAs: [site.instagramHref, site.youtubeHref, site.facebookHref],
  // No prices: none are confirmed yet (lib/prices.ts).
  hasOfferCatalog: {
    "@type": "OfferCatalog",
    name: "Car accessories and detailing studio services",
    itemListElement: [
      {
        "@type": "OfferCatalog",
        name: "Detailing studio",
        url: `${site.url}/detailing-studio/`,
        itemListElement: services.map((s) => ({
          "@type": "Offer",
          itemOffered: { "@type": "Service", "@id": `${site.url}/${s.slug}/#service`, name: s.menuLabel, url: `${site.url}/${s.slug}/` },
        })),
      },
      {
        "@type": "OfferCatalog",
        name: "Car accessories, fitted at the shop",
        url: `${site.url}/accessories/`,
        itemListElement: [
          ...categories.map((c) => ({
            "@type": "Offer",
            itemOffered: { "@type": "Service", name: c.name, description: c.blurb, url: `${site.url}${c.href ?? `/accessories/#${c.key}`}` },
          })),
          {
            "@type": "Offer",
            itemOffered: { "@type": "Service", "@id": `${site.url}/autoform-seat-covers-pune/#service`, name: "Autoform seat covers", url: `${site.url}/autoform-seat-covers-pune/` },
          },
        ],
      },
      ...(["ppf", "headlights"] as const).map((cat) => ({
        "@type": "OfferCatalog",
        name: `Authorised dealer: ${dealerCategories[cat].hubLabel.toLowerCase()}`,
        url: `${site.url}${dealerCategories[cat].hubPath}`,
        itemListElement: dealerBrands
          .filter((b) => b.category === cat)
          .map((b) => ({
            "@type": "Offer",
            itemOffered: {
              "@type": "Service",
              "@id": `${site.url}${dealerBrandPath(b)}#service`,
              name: dealerProductName(b),
              url: `${site.url}${dealerBrandPath(b)}`,
              brand: { "@type": "Brand", name: b.name },
            },
          })),
      })),
    ],
  },
};

const website = {
  "@type": "WebSite",
  "@id": WEBSITE_ID,
  name: site.name,
  url: `${site.url}/`,
  inLanguage: "en-IN",
  publisher: { "@id": BUSINESS_ID },
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en-IN" data-theme="dark" suppressHydrationWarning className={`${sora.variable} ${hanken.variable} ${hind.variable}`}>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeScript }} />
      </head>
      <body>
        <JsonLd data={graph(business, website)} />
        <Header />
        <main id="main">{children}</main>
        <Footer />
        <WhatsAppWidget />
        <HelpWidget />
        <Analytics />
      </body>
    </html>
  );
}
