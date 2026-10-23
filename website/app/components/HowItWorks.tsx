"use client";

import { useEffect, useRef, useState } from "react";

// Four studio steps with simple line icons. Wording stays generic on purpose:
// no film brands, warranty years or timings until the shop confirms them.

const icon = {
  inspect: (
    <>
      <circle cx="11" cy="11" r="6.5" />
      <path d="M16 16l4.5 4.5" />
      <path d="M8.5 11h5" />
    </>
  ),
  prep: (
    <>
      <path d="M12 3.5c3 3.6 5.5 6.6 5.5 9.6a5.5 5.5 0 0 1-11 0c0-3 2.5-6 5.5-9.6Z" />
      <path d="M9.5 13.5a2.6 2.6 0 0 0 2.5 2.5" />
    </>
  ),
  install: (
    <>
      <path d="M12 3l7 3v5.5c0 4.3-3 7.8-7 9.5-4-1.7-7-5.2-7-9.5V6l7-3Z" />
      <path d="M8.8 12.2l2.2 2.2 4.4-4.6" />
    </>
  ),
  handover: (
    <>
      <circle cx="8" cy="14" r="3.5" />
      <path d="M10.5 11.5L19 3M15.5 6.5l2.5 2.5M13.5 8.5l2 2" />
    </>
  ),
};

const steps: { key: keyof typeof icon; title: string; line: string }[] = [
  { key: "inspect", title: "Inspect", line: "We look over the paint and panels with you and agree what your car needs." },
  { key: "prep", title: "Prep", line: "Wash, decontaminate and polish, so the surface is clean before anything goes on." },
  { key: "install", title: "Install", line: "Film, coating or accessories, fitted by hand by our team in the studio." },
  { key: "handover", title: "Handover", line: "We walk round the car with you and explain the aftercare." },
];

// Scroll-driven (styles: "How it works" in app/styles/home.css):
// - Desktop (>= 900px wide, >= 620px tall): the section (.how-seq) is ~200vh and
//   its stage is sticky. That is pure CSS keyed on html[data-js] (set in <head>
//   by layout.tsx), so the page height is right from the first paint. Scroll progress through it fills the rail (--fill on the
//   <ol>) and moves the current step. Clicking a step scrolls to its point.
// - Phones: no pinning. A step becomes current as it crosses the middle of the
//   screen (IntersectionObserver), and the rail segment above it fills.
// - Reduced motion, no JS, or before hydration: the static look, every step
//   fully active (data-live is never set).
// Nothing is measured or re-rendered on scroll unless the section is near the
// viewport, and React only re-renders when the current step changes.

const SEQ_QUERY = "(min-width: 900px) and (min-height: 620px)";
// Fill runs from 5% to 90% of the pinned travel, so the first and last steps
// get a moment on screen before and after the rail moves.
const FILL_START = 0.05;
const FILL_SPAN = 0.85;
const last = steps.length - 1;

type Mode = "off" | "seq" | "io";

export default function HowItWorks() {
  const ref = useRef<HTMLOListElement>(null);
  const [mode, setMode] = useState<Mode>("off");
  const [active, setActive] = useState(-1);

  // Pick the mode, and follow viewport / motion-preference changes.
  useEffect(() => {
    const seq = matchMedia(SEQ_QUERY);
    const still = matchMedia("(prefers-reduced-motion: reduce)");
    const pick = () => setMode(still.matches || typeof IntersectionObserver === "undefined" ? "off" : seq.matches ? "seq" : "io");
    pick();
    seq.addEventListener("change", pick);
    still.addEventListener("change", pick);
    return () => {
      seq.removeEventListener("change", pick);
      still.removeEventListener("change", pick);
    };
  }, []);

  // Desktop: one passive scroll listener, rAF-throttled, attached only while the section is near the viewport.
  useEffect(() => {
    const ol = ref.current;
    const section = ol?.closest<HTMLElement>(".how-seq");
    if (mode !== "seq" || !ol || !section) return;
    const stage = section.querySelector<HTMLElement>(".how-stage") ?? section;
    let raf = 0;
    let lastActive = -2;

    const update = () => {
      raf = 0;
      const headerH = parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--header-h")) || 72;
      const travel = Math.max(1, section.offsetHeight - stage.offsetHeight);
      const p = Math.min(1, Math.max(0, (headerH - section.getBoundingClientRect().top) / travel));
      const fill = Math.min(1, Math.max(0, (p - FILL_START) / FILL_SPAN));
      ol.style.setProperty("--fill", fill.toFixed(4));
      const a = Math.min(last, Math.floor(fill * last + 0.001));
      if (a !== lastActive) {
        lastActive = a;
        setActive(a);
      }
    };
    const onScroll = () => {
      if (!raf) raf = requestAnimationFrame(update);
    };

    let listening = false;
    const io = new IntersectionObserver(
      ([e]) => {
        if (e.isIntersecting && !listening) {
          listening = true;
          window.addEventListener("scroll", onScroll, { passive: true });
          window.addEventListener("resize", onScroll, { passive: true });
          onScroll();
        } else if (!e.isIntersecting && listening) {
          listening = false;
          window.removeEventListener("scroll", onScroll);
          window.removeEventListener("resize", onScroll);
          update(); // settle at 0 or 1 when the section is left quickly
        }
      },
      { rootMargin: "200px 0px" },
    );
    io.observe(section);
    update();

    return () => {
      io.disconnect();
      window.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", onScroll);
      if (raf) cancelAnimationFrame(raf);
      ol.style.removeProperty("--fill");
      setActive(-1);
    };
  }, [mode]);

  // Phones: the current step is the last one whose node has crossed the middle band.
  useEffect(() => {
    const ol = ref.current;
    if (mode !== "io" || !ol) return;
    const items = Array.from(ol.children) as HTMLElement[];
    const pick = () => {
      const mid = window.innerHeight * 0.5;
      let a = -1;
      items.forEach((li, i) => {
        const node = li.querySelector(".steps-x-icon") ?? li;
        const r = node.getBoundingClientRect();
        if (r.top + r.height / 2 <= mid) a = i;
      });
      setActive(a);
    };
    const io = new IntersectionObserver(pick, { rootMargin: "-45% 0px -50% 0px", threshold: 0 });
    // The whole list too, so a fast fling past the band still settles correctly.
    const outer = new IntersectionObserver(pick, { threshold: [0, 0.25, 0.5, 0.75, 1] });
    items.forEach((li) => io.observe(li));
    outer.observe(ol);
    pick();
    return () => {
      io.disconnect();
      outer.disconnect();
      setActive(-1);
    };
  }, [mode]);

  const goTo = (i: number) => {
    const ol = ref.current;
    const section = ol?.closest<HTMLElement>(".how-seq");
    if (!ol) return;
    if (mode === "seq" && section) {
      const stage = section.querySelector<HTMLElement>(".how-stage") ?? section;
      const headerH = parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--header-h")) || 72;
      const travel = section.offsetHeight - stage.offsetHeight;
      const fill = Math.min(1, i / last + 0.02);
      const p = Math.min(1, FILL_START + FILL_SPAN * fill);
      const top = window.scrollY + section.getBoundingClientRect().top - headerH + p * travel;
      window.scrollTo({ top: Math.round(top), behavior: "smooth" });
    } else if (mode === "io") {
      const li = ol.children[i] as HTMLElement | undefined;
      const node = li?.querySelector<HTMLElement>(".steps-x-icon");
      if (!node) return;
      const r = node.getBoundingClientRect();
      window.scrollTo({ top: Math.round(window.scrollY + r.top + r.height / 2 - window.innerHeight * 0.47), behavior: "smooth" });
    }
  };

  const live = mode !== "off";
  return (
    <ol ref={ref} className="steps-x" data-live={live ? mode : undefined}>
      {steps.map((s, i) => {
        const reached = live && i <= active;
        const current = live && i === active;
        return (
          <li
            key={s.key}
            data-n={String(i + 1).padStart(2, "0")}
            className={[reached && "is-reached", current && "is-current", live && i < active && "is-seg"].filter(Boolean).join(" ") || undefined}
            aria-current={current ? "step" : undefined}
            onClick={live ? () => goTo(i) : undefined}
          >
            <span className="steps-x-icon" aria-hidden>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                {icon[s.key]}
              </svg>
            </span>
            <span className="steps-x-num" aria-hidden>{String(i + 1).padStart(2, "0")}</span>
            <h3>{s.title}</h3>
            <p>{s.line}</p>
          </li>
        );
      })}
    </ol>
  );
}
