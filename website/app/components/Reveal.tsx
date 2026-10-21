"use client";

import { createElement, useEffect, useRef, useState, type ElementType, type ReactNode } from "react";

/**
 * Fades and lifts its content in when it scrolls into view.
 *
 * Content is visible in the server HTML. Only after hydration, and only if the
 * element starts below the fold, is it "armed" (data-reveal) and hidden, so
 * nothing stays hidden if JavaScript fails. Styles live in styles/base.css;
 * prefers-reduced-motion shows everything at once.
 */
export default function Reveal({
  children,
  as,
  className,
  delay,
}: {
  children: ReactNode;
  as?: ElementType;
  className?: string;
  /** Delay in milliseconds before the transition starts. */
  delay?: number;
}) {
  const ref = useRef<HTMLElement>(null);
  const [state, setState] = useState<"idle" | "armed" | "in">("idle");

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    if (typeof IntersectionObserver === "undefined" || matchMedia("(prefers-reduced-motion: reduce)").matches) {
      setState("in");
      return;
    }

    // On screen (or already scrolled past) when this mounts: leave it as it is.
    if (el.getBoundingClientRect().top < window.innerHeight * 0.92) {
      setState("in");
      return;
    }

    setState("armed");
    const io = new IntersectionObserver(
      (entries) => {
        if (entries.some((e) => e.isIntersecting || e.boundingClientRect.top < 0)) {
          setState("in");
          io.disconnect();
        }
      },
      // Start a little before it reaches the screen, so fast scrolling never shows a gap.
      { rootMargin: "0px 0px 20% 0px", threshold: 0 },
    );
    io.observe(el);
    return () => io.disconnect();
  }, []);

  return createElement(
    as ?? "div",
    {
      ref,
      className: ["reveal", state === "in" && "is-in", className].filter(Boolean).join(" "),
      "data-reveal": state === "idle" ? undefined : "",
      style: delay ? { ["--reveal-delay" as string]: `${delay}ms` } : undefined,
    },
    children,
  );
}
