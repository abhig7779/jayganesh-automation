"use client";

import Script from "next/script";
import { useEffect } from "react";

// Google Analytics 4 (property "Jay Ganesh Car Accessories", stream "Jay Ganesh website").
// Besides page views it records the actions that turn into customers:
// WhatsApp, call and directions clicks anywhere on the site, plus events that
// components send with track() (form leads, help questions, reel plays).
export const GA_ID = "G-Q4TQV4VMWY";
// Microsoft Clarity project "Jay Ganesh Car Accessories website".
const CLARITY_ID = "z37q5pmykc";

type Gtag = (...args: unknown[]) => void;
type GaWindow = { dataLayer?: unknown[]; gtag?: Gtag };

/**
 * Send a GA4 event; safe to call before GA has loaded or when it's blocked.
 * gtag.js loads late (lazyOnload), so until then events are queued in
 * window.dataLayer by the standard gtag stub, and gtag.js sends them on load.
 */
export function track(event: string, params: Record<string, string | number | undefined> = {}) {
  const w = window as unknown as GaWindow;
  if (!w.gtag) {
    w.dataLayer = w.dataLayer || [];
    // gtag.js only reads Arguments objects from dataLayer, not arrays.
    w.gtag = function gtag() {
      // eslint-disable-next-line prefer-rest-params
      w.dataLayer!.push(arguments);
    };
  }
  w.gtag("event", event, params);
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
      {/* The tiny inline stub + config runs early, so page_view and any track()
          events are queued in dataLayer; the 150 KB gtag.js itself waits until
          the page has loaded and gone idle (lazyOnload) and then sends the queue. */}
      <Script id="ga4" strategy="afterInteractive">
        {`window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${GA_ID}');`}
      </Script>
      <Script src={`https://www.googletagmanager.com/gtag/js?id=${GA_ID}`} strategy="lazyOnload" />
      {/* Microsoft Clarity: session recordings and heatmaps. The stub queues calls;
          the tag itself loads lazily so it never competes with the page's first paint. */}
      <Script id="clarity" strategy="lazyOnload">
        {`(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script","${CLARITY_ID}");`}
      </Script>
    </>
  );
}
