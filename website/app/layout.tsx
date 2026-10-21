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
import { themeScript } from "./components/ThemeToggle";
import { site } from "@/lib/site";

// Headings: Sora, a precise, slightly technical sans set in sentence case.
// Body: Hanken Grotesk, easy to read at small sizes. Hind covers Marathi and
// Hindi text, so Devanagari never falls back to a system font.
const sora = Sora({ subsets: ["latin"], weight: ["400", "500", "600", "700"], variable: "--font-sora", display: "swap" });
const hanken = Hanken_Grotesk({ subsets: ["latin"], weight: ["400", "500", "600"], variable: "--font-hanken", display: "swap" });
const hind = Hind({ subsets: ["devanagari"], weight: ["400", "600"], variable: "--font-hind", display: "swap" });

export const metadata: Metadata = {
  metadataBase: new URL(site.url),
  title: {
    default: "Car Accessories in Pune & PPF Detailing Studio | Jay Ganesh, Loni Kalbhor",
    template: `%s | ${site.name}`,
  },
  description:
    "Seat covers, lights, Android stereos and dashcams, plus a PPF, ceramic coating and detailing studio on the Pune–Solapur road, Loni Kalbhor. Rated 4.9 on Google.",
  icons: { icon: "/logo.png" },
  openGraph: { type: "website", siteName: site.name, locale: "en_IN", images: ["/logo.png"] },
  alternates: { canonical: "/" },
};

export const viewport: Viewport = {
  themeColor: [
    { media: "(prefers-color-scheme: dark)", color: "#0f1013" },
    { media: "(prefers-color-scheme: light)", color: "#f6f6f4" },
  ],
  colorScheme: "dark light",
};

const business = {
  "@context": "https://schema.org",
  "@type": ["AutoPartsStore", "AutoRepair"],
  name: site.name,
  url: site.url,
  image: `${site.url}/logo.png`,
  telephone: site.phoneE164,
  address: {
    "@type": "PostalAddress",
    streetAddress: "Kawadipat, Toll Naka, Pune–Solapur Road",
    addressLocality: site.locality,
    addressRegion: site.region,
    ...(site.postalCode ? { postalCode: site.postalCode } : {}),
    addressCountry: "IN",
  },
  openingHoursSpecification: [{
    "@type": "OpeningHoursSpecification",
    dayOfWeek: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
    opens: site.openingHours.opens,
    closes: site.openingHours.closes,
  }],
  sameAs: [site.instagramHref, site.youtubeHref, site.facebookHref],
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en-IN" data-theme="dark" suppressHydrationWarning className={`${sora.variable} ${hanken.variable} ${hind.variable}`}>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeScript }} />
      </head>
      <body>
        <JsonLd data={business} />
        <Header />
        <main id="main">{children}</main>
        <Footer />
        <WhatsAppWidget />
      </body>
    </html>
  );
}
