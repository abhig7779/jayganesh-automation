"use client";

import "../styles/help.css";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { site, whatsapp } from "@/lib/site";
import { IconWhatsApp } from "./ui";
import { track } from "./Analytics";

// Floating help chat (bottom right): tap a ready question, get a ready answer,
// then carry on in WhatsApp with the message already filled in. No AI and no
// server: every answer below is written from confirmed shop facts.

type Answer = {
  q: string;
  a: string;
  wa: string;
  link?: { href: string; label: string; external?: boolean };
};

const answers: Answer[] = [
  {
    q: "What services do you offer?",
    a: "PPF, ceramic and graphene coating and detailing in our studio, plus seat covers, headlights, ambient lighting, Android stereos, 360° cameras and dashcams, fitted at the shop.",
    wa: "Hi Jay Ganesh, I'd like to know about your services for my car.",
    link: { href: "/detailing-studio/", label: "See studio services" },
  },
  {
    q: "How much does PPF cost?",
    a: "It depends on your car model and how much of the car you want covered. Send us your model on WhatsApp and we'll reply with options and a price.",
    wa: "Hi Jay Ganesh, what is the PPF price for my car? Model: ",
    link: { href: "/ppf-pune/", label: "About PPF" },
  },
  {
    q: "Do you have seat covers for my car?",
    a: "Yes. Seat covers are chosen for your exact model and fitted by our team. Tell us your car and we'll send designs.",
    wa: "Hi Jay Ganesh, I'm looking for seat covers. My car is: ",
    link: { href: "/seat-covers/", label: "Seat covers" },
  },
  {
    q: "Where is the shop?",
    a: `${site.addressShort}, Pune. You'll find us right at the toll naka on the Pune–Solapur Road.`,
    wa: "Hi Jay Ganesh, please share your location.",
    link: { href: site.mapsHref, label: "Open in Google Maps", external: true },
  },
  {
    q: "What are your timings?",
    a: `${site.hours}. Send a message any time and we'll reply during shop hours.`,
    wa: "Hi Jay Ganesh, I'd like to visit the shop.",
  },
  {
    q: "How do I book?",
    a: "Send your car model, the service you want and a day that suits you on WhatsApp, or call us. We'll confirm a slot.",
    wa: "Hi Jay Ganesh, I'd like to book. Car model: \nService: \nPreferred day: ",
  },
];

type Msg = { from: "bot" | "me"; text: string; answer?: Answer };

const greeting: Msg = {
  from: "bot",
  text: "Namaskar! 👋 How can we help? Tap a question below, or type your own and we'll take it to WhatsApp.",
};

export default function HelpWidget() {
  const [open, setOpen] = useState(false);
  const [msgs, setMsgs] = useState<Msg[]>([greeting]);
  const [text, setText] = useState("");
  const box = useRef<HTMLDivElement>(null);
  const log = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    const onClick = (e: MouseEvent) => { if (box.current && !box.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener("keydown", onKey);
    document.addEventListener("mousedown", onClick);
    return () => { document.removeEventListener("keydown", onKey); document.removeEventListener("mousedown", onClick); };
  }, [open]);

  useEffect(() => {
    log.current?.scrollTo({ top: log.current.scrollHeight, behavior: "smooth" });
  }, [msgs]);

  const ask = (a: Answer) => { track("help_question", { question: a.q }); setMsgs((m) => [...m, { from: "me", text: a.q }, { from: "bot", text: a.a, answer: a }]); };
  const asked = new Set(msgs.filter((m) => m.from === "me").map((m) => m.text));

  const send = (e: React.FormEvent) => {
    e.preventDefault();
    const t = text.trim();
    if (!t) return;
    track("help_typed_question");
    window.open(whatsapp(`Hi Jay Ganesh, ${t}`), "_blank", "noopener,noreferrer");
    setMsgs((m) => [...m, { from: "me", text: t }, { from: "bot", text: "Opening WhatsApp with your message. Just press send there and we'll reply." }]);
    setText("");
  };

  return (
    <div className="help-widget" ref={box}>
      {open && (
        <div className="help-card" role="dialog" aria-label="Help: quick answers">
          <div className="help-head">
            <span className="help-dot" aria-hidden />
            <div>
              <strong>Quick help</strong>
              <span><span className="notranslate" translate="no">Jay Ganesh</span> · {site.hoursShort}</span>
            </div>
            <button type="button" className="help-close" onClick={() => setOpen(false)} aria-label="Close help">×</button>
          </div>

          <div className="help-log" ref={log} aria-live="polite">
            {msgs.map((m, i) => (
              <div key={i} className={`help-msg help-msg--${m.from}`}>
                {/* The visitor's own words stay as typed when the page is in Hindi. */}
                <p className={m.from === "me" ? "notranslate" : undefined} translate={m.from === "me" ? "no" : undefined}>{m.text}</p>
                {m.answer && (
                  <div className="help-actions">
                    <a className="help-wa" href={whatsapp(m.answer.wa)} target="_blank" rel="noopener noreferrer" data-track="whatsapp">
                      <IconWhatsApp /> Continue on WhatsApp
                    </a>
                    {m.answer.link && (m.answer.link.external ? (
                      <a className="help-link" href={m.answer.link.href} target="_blank" rel="noopener noreferrer">{m.answer.link.label}</a>
                    ) : (
                      <Link className="help-link" href={m.answer.link.href} onClick={() => setOpen(false)}>{m.answer.link.label}</Link>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>

          <div className="help-chips" role="group" aria-label="Common questions">
            {answers.filter((a) => !asked.has(a.q)).map((a) => (
              <button key={a.q} type="button" className="help-chip" onClick={() => ask(a)}>{a.q}</button>
            ))}
          </div>

          <form className="help-form" onSubmit={send}>
            <label className="sr-only" htmlFor="help-input">Type your question</label>
            <input
              id="help-input"
              type="text"
              value={text}
              onChange={(e) => setText(e.target.value)}
              placeholder="Type your question…"
              autoComplete="off"
              maxLength={300}
            />
            <button type="submit" aria-label="Send on WhatsApp" disabled={!text.trim()}>
              <IconWhatsApp />
            </button>
          </form>
        </div>
      )}
      <button
        type="button"
        className="help-fab"
        aria-expanded={open}
        aria-label={open ? "Close help" : "Open help"}
        onClick={() => { if (!open) track("help_open"); setOpen((v) => !v); }}
      >
        {open ? (
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden><path d="M6 6l12 12M18 6L6 18" /></svg>
        ) : (
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
            <path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.7A8 8 0 1 1 21 12Z" />
            <path d="M9.5 9.5a2.5 2.5 0 1 1 3.4 2.3c-.6.3-.9.7-.9 1.4v.3" />
            <circle cx="12" cy="16.3" r=".6" fill="currentColor" />
          </svg>
        )}
        {!open && <span className="help-fab-label">Help</span>}
      </button>
    </div>
  );
}
