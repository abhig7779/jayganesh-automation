import type { Metadata, Viewport } from "next";
import { Archivo, Hind } from "next/font/google";
import "./brand.css";
import "./globals.css";
import { site } from "@/lib/site";

const archivo = Archivo({
  subsets: ["latin"],
  axes: ["wdth"],
  variable: "--font-archivo",
  display: "swap",
});

const hind = Hind({
  subsets: ["latin", "devanagari"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-hind",
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: `${site.name} | Loni Kalbhor, Pune`,
    template: `%s | ${site.name}`,
  },
  description:
    "Car accessories, seat covers, lighting, audio, PPF and detailing on Pune–Solapur Road, Loni Kalbhor. Rated 4.9 on Google. New website coming soon.",
  icons: { icon: "/logo.png" },
};

export const viewport: Viewport = {
  themeColor: "#0f1013",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" data-theme="dark" className={`${archivo.variable} ${hind.variable}`}>
      <body>{children}</body>
    </html>
  );
}
