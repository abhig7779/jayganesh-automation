"use client";

import Image from "next/image";
import { useEffect, useRef, useState } from "react";
import { site, whatsapp } from "@/lib/site";
import { IconWhatsApp } from "./ui";

// Floating WhatsApp button with a small chat card, in WhatsApp's own colours.
// On phones the bottom action bar already has WhatsApp, so this shows from
// tablet width up.
export default function WhatsAppWidget() {
  const [open, setOpen] = useState(false);
  const box = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    const onClick = (e: MouseEvent) => { if (box.current && !box.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener("keydown", onKey);
    document.addEventListener("mousedown", onClick);
    return () => { document.removeEventListener("keydown", onKey); document.removeEventListener("mousedown", onClick); };
  }, [open]);

  return (
    <div className="wa-widget" ref={box}>
      {open && (
        <div className="wa-card" role="dialog" aria-label="Chat with Jay Ganesh on WhatsApp">
          <div className="wa-card-head">
            <span className="wa-avatar"><Image src="/logo-64.webp" alt="" width={40} height={28} /></span>
            <div>
              <strong>{site.name}</strong>
              <span>Replies during shop hours · 10 am – 8 pm</span>
            </div>
            <button type="button" className="wa-close" onClick={() => setOpen(false)} aria-label="Close">×</button>
          </div>
          <div className="wa-card-body">
            <p className="wa-bubble">
              Namaskar! 👋 Send us your car model and what you need. We&apos;ll reply with options and a price.
            </p>
          </div>
          <a className="wa-start" href={whatsapp()} target="_blank" rel="noopener noreferrer" data-track="whatsapp">
            <IconWhatsApp />
            Start chat
          </a>
        </div>
      )}
      <button
        type="button"
        className="wa-fab"
        aria-expanded={open}
        aria-label={open ? "Close WhatsApp chat" : "Chat on WhatsApp"}
        onClick={() => setOpen((v) => !v)}
      >
        <IconWhatsApp />
      </button>
    </div>
  );
}
