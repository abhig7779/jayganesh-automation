"use client";

import Script from "next/script";
import { useEffect } from "react";

// Google Analytics 4 (property "Jay Ganesh Car Accessories", stream "Jay Ganesh website").
// Besides page views it records the actions that turn into customers:
// WhatsApp, call and directions clicks anywhere on the site, plus events that
// components send with track() (form leads, help questions, reel plays).
export const GA_ID = "G-Q4TQV4VMWY";

type Gtag = (...args: unknown[]) => void;

/** Send a GA4 event; safe to call before GA has loaded or when it's blocked. */
export function track(event: string, params: Record<string, string | number | undefined> = {}) {
  const w = window as unknown as { gtag?: Gtag };
  w.gtag?.("event", event, params);
}

function linkEvent(a: HTMLAnchorElement): string | null {
  const tag = a.dataset.track;
  const href = a.getAttribute("href") || "";
  if (tag === "whatsapp" || href.startsWith("https://wa.me/")) return "whatsapp_click";
  if (tag === "call" || href.startsWith("tel:")) return "phone_click";
  if (tag === "directions" || href.includes("google.com/maps")) return "directions_click";
  return null;
}

export default function Analytics() {
  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      const a = (e.target as Element | null)?.closest?.("a");
      if (!a) return;
      const name = linkEvent(a as HTMLAnchorElement);
      if (name) track(name, { link_text: (a.textContent || "").trim().slice(0, 60), page_path: location.pathname });
    };
    document.addEventListener("click", onClick, { capture: true });
    return () => document.removeEventListener("click", onClick, { capture: true });
  }, []);

  return (
    <>
      <Script src={`https://www.googletagmanager.com/gtag/js?id=${GA_ID}`} strategy="afterInteractive" />
      <Script id="ga4" strategy="afterInteractive">
        {`window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${GA_ID}');`}
      </Script>
    </>
  );
}
