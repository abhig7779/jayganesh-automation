"use client";

import "../styles/reels.css";
import { useCallback, useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { categoryLabel, reelEmbedUrl, reels, reelUrl, type Reel, type ReelCategory } from "@/lib/reels";
import { site } from "@/lib/site";
import { track } from "./Analytics";
import { webpSrcSet } from "@/lib/media";

type Filter = "all" | ReelCategory;

const FILTERS: { key: Filter; label: string }[] = [
  { key: "all", label: "All" },
  { key: "ppf", label: categoryLabel.ppf },
  { key: "coating", label: categoryLabel.coating },
  { key: "interior", label: categoryLabel.interior },
];

function InstagramGlyph({ size = 18 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
      <rect x="3" y="3" width="18" height="18" rx="5" />
      <circle cx="12" cy="12" r="4.2" />
      <circle cx="17.4" cy="6.6" r="1.1" fill="currentColor" stroke="none" />
    </svg>
  );
}

function Chevron({ dir }: { dir: "left" | "right" }) {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {dir === "left" ? <path d="M15 5l-7 7 7 7" /> : <path d="M9 5l7 7-7 7" />}
    </svg>
  );
}

function PlayIcon() {
  return (
    <svg width="26" height="26" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.6-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z" fill="currentColor" />
    </svg>
  );
}

function ReelVideo({ reel, onReady }: { reel: Reel; onReady: () => void }) {
  const ref = useRef<HTMLVideoElement>(null);
  const [muted, setMuted] = useState(false);

  // Start with sound (the card tap counts as a user gesture). If the browser
  // still blocks it, fall back to muted autoplay and offer "Tap for sound".
  useEffect(() => {
    const v = ref.current;
    if (!v) return;
    v.muted = false;
    v.play().catch(() => {
      v.muted = true;
      setMuted(true);
      v.play().catch(() => {
        /* the native controls still let the viewer start it */
      });
    });
  }, []);

  const unmute = () => {
    const v = ref.current;
    if (!v) return;
    v.muted = false;
    setMuted(false);
    v.play().catch(() => {});
  };

  return (
    <>
      <video
        ref={ref}
        src={reel.video}
        poster={reel.poster}
        controls
        autoPlay
        playsInline
        loop
        preload="auto"
        onLoadedData={onReady}
        onVolumeChange={(e) => setMuted(e.currentTarget.muted)}
        aria-label={reel.title}
      />
      {muted && (
        <button type="button" className="rl-sound" onClick={unmute}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M11 5L6 9H3v6h3l5 4V5z" />
            <path d="M22 9l-6 6M16 9l6 6" />
          </svg>
          Tap for sound
        </button>
      )}
    </>
  );
}

function ReelModal({
  list,
  index,
  onIndex,
  onClose,
}: {
  list: Reel[];
  index: number;
  onIndex: (i: number) => void;
  onClose: () => void;
}) {
  const reel = list[index];
  const dialogRef = useRef<HTMLDivElement>(null);
  const closeRef = useRef<HTMLButtonElement>(null);
  const [loaded, setLoaded] = useState(false);
  const hasPrev = index > 0;
  const hasNext = index < list.length - 1;

  useEffect(() => setLoaded(false), [reel.id]);

  // Lock body scroll and focus the close button while open.
  useEffect(() => {
    const { body, documentElement } = document;
    const prevOverflow = body.style.overflow;
    const prevPad = body.style.paddingRight;
    const scrollbar = window.innerWidth - documentElement.clientWidth;
    body.style.overflow = "hidden";
    if (scrollbar > 0) body.style.paddingRight = `${scrollbar}px`;
    closeRef.current?.focus();
    return () => {
      body.style.overflow = prevOverflow;
      body.style.paddingRight = prevPad;
    };
  }, []);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault();
        onClose();
      } else if (e.key === "ArrowLeft" && hasPrev) {
        onIndex(index - 1);
      } else if (e.key === "ArrowRight" && hasNext) {
        onIndex(index + 1);
      } else if (e.key === "Tab" && dialogRef.current) {
        const nodes = Array.from(
          dialogRef.current.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], iframe, [tabindex]:not([tabindex="-1"])'),
        );
        if (!nodes.length) return;
        const first = nodes[0];
        const last = nodes[nodes.length - 1];
        const active = document.activeElement;
        if (e.shiftKey && (active === first || !dialogRef.current.contains(active))) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && (active === last || !dialogRef.current.contains(active))) {
          e.preventDefault();
          first.focus();
        }
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [index, hasPrev, hasNext, onClose, onIndex]);

  return (
    <div className="rl-modal" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div
        ref={dialogRef}
        className="rl-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="rl-modal-title"
      >
        <div className="rl-modal__bar">
          <p id="rl-modal-title" className="rl-modal__title">
            <span className="rl-chip">{categoryLabel[reel.category]}</span>
            <span className="rl-modal__name">{reel.title}</span>
          </p>
          <span className="rl-modal__count" aria-live="polite">
            {index + 1} / {list.length}
          </span>
          <button ref={closeRef} type="button" className="rl-icon-btn rl-modal__close" onClick={onClose} aria-label="Close video">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </div>

        <div className="rl-modal__stage">
          <button
            type="button"
            className="rl-icon-btn rl-modal__nav rl-modal__nav--prev"
            onClick={() => onIndex(index - 1)}
            disabled={!hasPrev}
            aria-label="Previous reel"
          >
            <Chevron dir="left" />
          </button>

          <div className={`rl-modal__frame${reel.video ? " rl-modal__frame--video" : ""}`}>
            {!loaded && (
              <div className="rl-spinner" role="status">
                <span className="rl-spinner__ring" aria-hidden="true" />
                <span className="rl-sr">Loading reel</span>
              </div>
            )}
            {reel.video ? (
              <ReelVideo key={reel.id} reel={reel} onReady={() => setLoaded(true)} />
            ) : (
              <iframe
                key={reel.id}
                src={reelEmbedUrl(reel.id)}
                title={`Instagram reel: ${reel.title}`}
                allow="autoplay; encrypted-media; picture-in-picture"
                allowFullScreen
                loading="lazy"
                onLoad={() => setLoaded(true)}
                className={loaded ? "is-loaded" : undefined}
              />
            )}
          </div>

          <button
            type="button"
            className="rl-icon-btn rl-modal__nav rl-modal__nav--next"
            onClick={() => onIndex(index + 1)}
            disabled={!hasNext}
            aria-label="Next reel"
          >
            <Chevron dir="right" />
          </button>
        </div>

        <div className="rl-modal__foot">
          <a className="rl-modal__open" href={reelUrl(reel.id)} target="_blank" rel="noopener noreferrer">
            <InstagramGlyph size={16} /> Open on Instagram
          </a>
        </div>
      </div>
    </div>
  );
}

export default function ReelsShowcase() {
  const [filter, setFilter] = useState<Filter>("all");
  const [openIndex, setOpenIndex] = useState<number | null>(null);
  const [edge, setEdge] = useState({ start: true, end: false });
  const railRef = useRef<HTMLUListElement>(null);
  const triggerRef = useRef<HTMLElement | null>(null);

  const list = filter === "all" ? reels : reels.filter((r) => r.category === filter);

  const updateEdges = useCallback(() => {
    const el = railRef.current;
    if (!el) return;
    setEdge({ start: el.scrollLeft <= 4, end: el.scrollLeft + el.clientWidth >= el.scrollWidth - 4 });
  }, []);

  useEffect(() => {
    const el = railRef.current;
    if (!el) return;
    el.scrollTo({ left: 0 });
    updateEdges();
    el.addEventListener("scroll", updateEdges, { passive: true });
    window.addEventListener("resize", updateEdges);
    return () => {
      el.removeEventListener("scroll", updateEdges);
      window.removeEventListener("resize", updateEdges);
    };
  }, [filter, updateEdges]);

  const scrollBy = (dir: 1 | -1) => {
    const el = railRef.current;
    if (!el) return;
    const card = el.querySelector<HTMLElement>(".rl-card");
    const step = card ? card.offsetWidth + 16 : 256;
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    el.scrollBy({ left: dir * step * 2, behavior: reduce ? "auto" : "smooth" });
  };

  const open = (i: number, target: HTMLElement) => {
    triggerRef.current = target;
    track("reel_play", { reel: (target.textContent || "").trim().slice(0, 80) });
    setOpenIndex(i);
  };

  const close = useCallback(() => {
    setOpenIndex(null);
    const t = triggerRef.current;
    if (t) requestAnimationFrame(() => t.focus());
  }, []);

  const onIndex = useCallback(
    (i: number) => {
      setOpenIndex(i);
      // Keep focus restoration pointing at the card for the reel now showing.
      const card = railRef.current?.querySelectorAll<HTMLElement>(".rl-card")[i];
      if (card) {
        triggerRef.current = card;
        card.scrollIntoView({ block: "nearest", inline: "nearest" });
      }
    },
    [],
  );

  return (
    <div className="rl">
      <div className="rl__top">
        <div className="rl-tabs" role="group" aria-label="Filter reels">
          {FILTERS.map((f) => (
            <button
              key={f.key}
              type="button"
              className="rl-tab"
              aria-pressed={filter === f.key}
              onClick={() => setFilter(f.key)}
            >
              {f.label}
            </button>
          ))}
        </div>
        <div className="rl-arrows">
          <button type="button" className="rl-icon-btn" onClick={() => scrollBy(-1)} disabled={edge.start} aria-label="Scroll reels left">
            <Chevron dir="left" />
          </button>
          <button type="button" className="rl-icon-btn" onClick={() => scrollBy(1)} disabled={edge.end} aria-label="Scroll reels right">
            <Chevron dir="right" />
          </button>
        </div>
      </div>

      <ul ref={railRef} className="rl-rail" aria-label="Instagram reels">
        {list.map((r, i) => (
          <li key={r.id} className="rl-rail__item">
            <button type="button" className="rl-card" onClick={(e) => open(i, e.currentTarget)}>
              {r.poster ? (
                <picture>
                  <source type="image/webp" srcSet={webpSrcSet(r.poster)} sizes="(max-width: 720px) 72vw, (max-width: 1100px) 220px, 240px" />
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img className="rl-card__poster" src={r.poster} alt="" width={405} height={720} loading="lazy" decoding="async" />
                </picture>
              ) : null}
              <span className="rl-card__bg" aria-hidden="true" />
              <span className="rl-card__top">
                <span className="rl-chip">{categoryLabel[r.category]}</span>
                <span className="rl-card__ig" aria-hidden="true">
                  <InstagramGlyph />
                </span>
              </span>
              <span className="rl-card__play" aria-hidden="true">
                <PlayIcon />
              </span>
              <span className="rl-card__body">
                <span className="rl-card__title">{r.title}</span>
                <span className="rl-card__cta">Watch reel</span>
              </span>
            </button>
          </li>
        ))}
      </ul>

      <div className="rl__follow">
        <a className="rl-follow" href={site.instagramHref} target="_blank" rel="noopener noreferrer">
          <InstagramGlyph /> Follow {site.instagram}
        </a>
      </div>

      {openIndex !== null &&
        list[openIndex] &&
        createPortal(<ReelModal list={list} index={openIndex} onIndex={onIndex} onClose={close} />, document.body)}
    </div>
  );
}
