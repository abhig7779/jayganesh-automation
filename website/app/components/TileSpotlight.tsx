"use client";

import { useEffect } from "react";

// Feeds the pointer position to tiles matching `selector` as --mx / --my (px),
// so their CSS border glow can follow the cursor. Mouse/pen only; touch
// devices keep the calm static tiles. Renders nothing.
export default function TileSpotlight({ selector }: { selector: string }) {
  useEffect(() => {
    if (!window.matchMedia("(hover: hover)").matches) return;
    const onMove = (e: PointerEvent) => {
      const tile = (e.target as Element | null)?.closest?.<HTMLElement>(selector);
      if (!tile) return;
      const r = tile.getBoundingClientRect();
      tile.style.setProperty("--mx", `${e.clientX - r.left}px`);
      tile.style.setProperty("--my", `${e.clientY - r.top}px`);
    };
    document.addEventListener("pointermove", onMove, { passive: true });
    return () => document.removeEventListener("pointermove", onMove);
  }, [selector]);
  return null;
}
