"use client";

import "../styles/reviews.css";
import { useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";
import { site } from "@/lib/site";
import { visibleReviews, type VisibleReview } from "@/lib/reviews";
import { GoogleG, Stars } from "./GoogleRating";

const INTERVAL_MS = 3000;
const RESUME_AFTER_TOUCH_MS = 4000;

// useLayoutEffect warns during server render; fall back to useEffect there.
const useIsoLayoutEffect = typeof window === "undefined" ? useEffect : useLayoutEffect;

function ReviewCard({
  review,
  index,
  total,
  expanded,
  onToggle,
}: {
  review: VisibleReview;
  index: number;
  total: number;
  expanded: boolean;
  onToggle: () => void;
}) {
  const textRef = useRef<HTMLParagraphElement>(null);
  const [clamped, setClamped] = useState(false);
  const textId = `rv-text-${review.id}`;

  // Show "Read more" only when the text is actually cut off. The button row is
  // always reserved, so measuring causes no layout shift.
  useIsoLayoutEffect(() => {
    const el = textRef.current;
    if (!el) return;
    const measure = () => {
      if (el.classList.contains("is-expanded")) return;
      setClamped(el.scrollHeight - el.clientHeight > 2);
    };
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(el);
    return () => ro.disconnect();
  }, []);

  return (
    <li
      className="rv-card"
      aria-roledescription="slide"
      aria-label={`Review ${index + 1} of ${total}`}
      data-index={index}
    >
      <article className="rv-card__inner">
        <header className="rv-card__head">
          <Stars count={review.stars} size={17} />
          <span className="rv-sr">{review.stars} out of 5 stars</span>
          <GoogleG size={20} className="rv-card__g" />
        </header>
        <p id={textId} ref={textRef} className={`rv-card__text${expanded ? " is-expanded" : ""}`}>
          {review.text}
        </p>
        <div className="rv-card__more">
          {(clamped || expanded) && (
            <button type="button" className="rv-more" aria-expanded={expanded} aria-controls={textId} onClick={onToggle}>
              {expanded ? "Show less" : "Read more"}
            </button>
          )}
        </div>
        <footer className="rv-card__foot">
          <span className="rv-avatar" aria-hidden="true">
            {review.shortName.charAt(0)}
          </span>
          <span className="rv-card__who">
            <span className="rv-card__name">{review.shortName}</span>
            <span className="rv-card__when">{review.relative} · Google</span>
          </span>
        </footer>
      </article>
    </li>
  );
}

/** Auto-scrolling carousel of Google reviews. No heading: the page adds its own. */
export default function ReviewsCarousel() {
  const reviews = visibleReviews;
  const trackRef = useRef<HTMLUListElement>(null);
  const rootRef = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState(0);
  const [pages, setPages] = useState(reviews.length);
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [hovered, setHovered] = useState(false);
  const [focused, setFocused] = useState(false);
  const [touchPaused, setTouchPaused] = useState(false);
  const [inView, setInView] = useState(false);
  const [reducedMotion, setReducedMotion] = useState(true); // assume reduced until we know
  const touchTimer = useRef<number | undefined>(undefined);
  const drag = useRef<{ x: number; left: number; moved: boolean; id: number } | null>(null);
  const suppressClick = useRef(false);

  const paused = hovered || focused || touchPaused || expandedId !== null || !inView;
  const autoplay = !reducedMotion && reviews.length > 1;

  /** Card step (width + gap) and how many cards fit. */
  const metrics = useCallback(() => {
    const track = trackRef.current;
    const first = track?.querySelector<HTMLElement>(".rv-card");
    if (!track || !first) return { step: 1, perView: 1, max: 0 };
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    const step = first.offsetWidth + gap;
    const perView = Math.max(1, Math.round((track.clientWidth + gap) / step));
    return { step, perView, max: Math.max(0, reviews.length - perView) };
  }, [reviews.length]);

  const goTo = useCallback(
    (i: number) => {
      const track = trackRef.current;
      if (!track) return;
      const { step, max } = metrics();
      const target = i > max ? 0 : i < 0 ? max : i;
      track.scrollTo({ left: target * step, behavior: reducedMotion ? "auto" : "smooth" });
    },
    [metrics, reducedMotion],
  );

  // Reduced-motion preference.
  useEffect(() => {
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
    const update = () => setReducedMotion(mq.matches);
    update();
    mq.addEventListener("change", update);
    return () => mq.removeEventListener("change", update);
  }, []);

  // Only run while on screen.
  useEffect(() => {
    const el = rootRef.current;
    if (!el) return;
    const io = new IntersectionObserver(([e]) => setInView(e.isIntersecting), { threshold: 0.25 });
    io.observe(el);
    return () => io.disconnect();
  }, []);

  // Track position → active dot; resize → number of dots.
  useEffect(() => {
    const track = trackRef.current;
    if (!track) return;
    let raf = 0;
    const sync = () => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        const { step, max } = metrics();
        setPages(max + 1);
        const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
        setActive(atEnd ? max : Math.min(max, Math.round(track.scrollLeft / step)));
      });
    };
    sync();
    track.addEventListener("scroll", sync, { passive: true });
    const ro = new ResizeObserver(sync);
    ro.observe(track);
    return () => {
      cancelAnimationFrame(raf);
      track.removeEventListener("scroll", sync);
      ro.disconnect();
    };
  }, [metrics]);

  // Auto-advance.
  useEffect(() => {
    if (!autoplay || paused) return;
    const id = window.setInterval(() => {
      if (document.hidden) return;
      goTo(active + 1);
    }, INTERVAL_MS);
    return () => window.clearInterval(id);
  }, [autoplay, paused, active, goTo]);

  useEffect(() => () => window.clearTimeout(touchTimer.current), []);

  const onTouchStart = () => {
    window.clearTimeout(touchTimer.current);
    setTouchPaused(true);
  };
  const onTouchEnd = () => {
    window.clearTimeout(touchTimer.current);
    touchTimer.current = window.setTimeout(() => setTouchPaused(false), RESUME_AFTER_TOUCH_MS);
  };

  // Mouse drag (touch uses native scrolling + snap).
  const onPointerDown = (e: React.PointerEvent<HTMLUListElement>) => {
    if (e.pointerType !== "mouse" || e.button !== 0) return;
    const track = trackRef.current;
    if (!track) return;
    drag.current = { x: e.clientX, left: track.scrollLeft, moved: false, id: e.pointerId };
  };
  const onPointerMove = (e: React.PointerEvent<HTMLUListElement>) => {
    const d = drag.current;
    const track = trackRef.current;
    if (!d || !track) return;
    const dx = e.clientX - d.x;
    if (!d.moved && Math.abs(dx) > 5) {
      d.moved = true;
      track.setPointerCapture(d.id);
      track.classList.add("is-dragging");
    }
    if (d.moved) track.scrollLeft = d.left - dx;
  };
  const endDrag = () => {
    const d = drag.current;
    const track = trackRef.current;
    drag.current = null;
    if (!d || !track || !d.moved) return;
    track.classList.remove("is-dragging");
    if (track.hasPointerCapture(d.id)) track.releasePointerCapture(d.id);
    suppressClick.current = true;
    window.setTimeout(() => (suppressClick.current = false), 0);
    const { step } = metrics();
    goTo(Math.round(track.scrollLeft / step));
  };

  const onKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === "ArrowRight") {
      e.preventDefault();
      goTo(active + 1);
    } else if (e.key === "ArrowLeft") {
      e.preventDefault();
      goTo(active - 1);
    }
  };

  if (!reviews.length) return null;

  return (
    <div
      ref={rootRef}
      className="rv-carousel"
      role="region"
      aria-roledescription="carousel"
      aria-label="Google reviews"
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      onFocus={() => setFocused(true)}
      onBlur={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setFocused(false);
      }}
      onTouchStart={onTouchStart}
      onTouchEnd={onTouchEnd}
      onTouchCancel={onTouchEnd}
    >
      <ul
        ref={trackRef}
        className="rv-track"
        tabIndex={0}
        aria-live={autoplay && !paused ? "off" : "polite"}
        aria-label="Reviews. Use the left and right arrow keys to move."
        onKeyDown={onKeyDown}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={endDrag}
        onPointerCancel={endDrag}
        onClickCapture={(e) => {
          if (suppressClick.current) {
            e.preventDefault();
            e.stopPropagation();
          }
        }}
        onDragStart={(e) => e.preventDefault()}
      >
        {reviews.map((r, i) => (
          <ReviewCard
            key={r.id}
            review={r}
            index={i}
            total={reviews.length}
            expanded={expandedId === r.id}
            onToggle={() => setExpandedId((cur) => (cur === r.id ? null : r.id))}
          />
        ))}
      </ul>

      <div className="rv-controls">
        <button type="button" className="rv-nav" aria-label="Previous review" onClick={() => goTo(active - 1)}>
          <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
            <path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </button>
        <div className="rv-dots" role="group" aria-label="Choose review">
          {Array.from({ length: pages }, (_, i) => (
            <button
              key={i}
              type="button"
              className={`rv-dot${i === active ? " is-active" : ""}${i === active && autoplay && !paused ? " is-running" : ""}`}
              aria-label={`Go to review ${i + 1}`}
              aria-current={i === active ? "true" : undefined}
              onClick={() => goTo(i)}
            >
              <span style={{ animationDuration: `${INTERVAL_MS}ms` }} />
            </button>
          ))}
        </div>
        <button type="button" className="rv-nav" aria-label="Next review" onClick={() => goTo(active + 1)}>
          <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
            <path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </button>
      </div>

      <div className="rv-cta">
        <a className="rv-cta__link" href={site.reviewsHref} target="_blank" rel="noopener noreferrer">
          <GoogleG size={18} />
          Read all reviews on Google
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
            <path d="M7 17L17 7M9 7h8v8" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </a>
      </div>
    </div>
  );
}
