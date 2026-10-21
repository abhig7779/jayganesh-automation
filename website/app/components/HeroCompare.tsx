"use client";

import { useEffect, useRef, useState } from "react";
import { imageUrl, media, srcSet } from "@/lib/media";

// Drag (or use arrow keys) to compare the same car before and after PPF.
// While the before/after photos are one stand-in image, the "before" side is
// styled as dull, swirled paint: `simulated` adds that treatment and should be
// switched off once two real photos are in place.
const simulated = media.compareBefore.src === media.compareAfter.src;

export default function HeroCompare() {
  const [split, setSplit] = useState(50);
  const [touched, setTouched] = useState(false);
  const raf = useRef(0);

  // A short sweep on load shows that the image can be dragged.
  useEffect(() => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    const start = performance.now();
    const tick = (t: number) => {
      const k = (t - start) / 1600;
      if (k >= 1 || touched) { if (!touched) setSplit(50); return; }
      setSplit(50 + Math.sin(k * Math.PI * 2) * 16 * (1 - k));
      raf.current = requestAnimationFrame(tick);
    };
    const id = setTimeout(() => { raf.current = requestAnimationFrame(tick); }, 700);
    return () => { clearTimeout(id); cancelAnimationFrame(raf.current); };
  }, [touched]);

  const img = (key: "compareBefore" | "compareAfter") => (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={imageUrl(media[key].src, 1400)}
      srcSet={srcSet(media[key].src, [640, 960, 1400, 2000])}
      sizes="(min-width: 1320px) 1240px, 100vw"
      alt={media[key].alt}
      loading="lazy"
      decoding="async"
      draggable={false}
    />
  );

  return (
    <div className={`compare-hero${simulated ? " is-simulated" : ""}`} style={{ ["--split" as string]: `${split}%` }}>
      <div className="compare-layer compare-before">
        {img("compareBefore")}
        {simulated && <span className="compare-swirl" aria-hidden />}
      </div>
      <div className="compare-layer compare-after">
        {img("compareAfter")}
        <span className="compare-sheen" aria-hidden />
      </div>

      <span className="compare-tag compare-tag--before">Before PPF</span>
      <span className="compare-tag compare-tag--after">After PPF</span>
      <span className="compare-handle" aria-hidden><span /></span>

      <input
        type="range"
        min={0}
        max={100}
        value={split}
        onChange={(e) => { setTouched(true); setSplit(Number(e.target.value)); }}
        onPointerDown={() => setTouched(true)}
        aria-label="Drag to compare the paint before and after PPF"
      />
    </div>
  );
}
