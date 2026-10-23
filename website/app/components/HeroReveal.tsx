"use client";

import { useEffect, useRef, useState } from "react";

export type HeroPoster = { jpg: string; webp?: string; /** 540px-wide WebP for phones under 2x DPR (phone posters only). */ webpSmall?: string };

/** Phones at 2x DPR and up keep the full 720px poster; the rest get the 540px one.
 *  (Same strings in Hero.tsx for the preloads: a "use client" module can't export constants to the server.) */
const hiDpi = "(min-resolution: 2dppx)";
export type HeroFilm = { src: string; start: HeroPoster; end: HeroPoster };

type Props = {
  desktop: HeroFilm;
  /** Portrait film for phones, or null: phones then get a static END poster. */
  mobile: HeroFilm | null;
  alt: string;
  kicker: string;
  /** The H1 (server rendered). */
  title: React.ReactNode;
  chapters: string[];
  queries: { phone: string; still: string };
  /** Line, actions and rating (server rendered). */
  children: React.ReactNode;
};

const clamp = (n: number) => (n < 0 ? 0 : n > 1 ? 1 : n);
const motionQ = "(prefers-reduced-motion: no-preference)";

function Sources({ poster, media }: { poster: HeroPoster; media?: string }) {
  return (
    <>
      {poster.webp && poster.webpSmall && media && (
        <>
          <source media={`${media} and ${hiDpi}`} type="image/webp" srcSet={poster.webp} />
          <source media={media} type="image/webp" srcSet={poster.webpSmall} />
        </>
      )}
      {poster.webp && !(poster.webpSmall && media) && <source media={media} type="image/webp" srcSet={poster.webp} />}
      <source media={media} srcSet={poster.jpg} />
    </>
  );
}

/**
 * Scroll-scrubbed hero. The section is ~520vh tall with a sticky 100svh stage;
 * scroll progress through it (0→1) eases the film's currentTime toward
 * p × duration on requestAnimationFrame. The film never plays.
 *
 * Load order: the START poster <img> is the LCP element; the film is only
 * fetched (into a blob) after window load + idle. Reduced motion, phones without a
 * portrait film and no-JS get a static, non-pinned hero (see home.css).
 */
// Scroll progress where each shot of the film starts (cuts at 5.7, 11.1, 16.5 of 22.2 s).
const cuts = [0.26, 0.5, 0.74];
const chapterAt = (p: number) => cuts.filter((c) => p >= c).length;

export default function HeroReveal({ desktop, mobile, alt, kicker, title, chapters, queries, children }: Props) {
  const root = useRef<HTMLElement>(null);
  const video = useRef<HTMLVideoElement>(null);
  const posterImg = useRef<HTMLImageElement>(null);
  const [armed, setArmed] = useState(false);
  const [film, setFilm] = useState<HeroFilm | null>(null);
  const [ready, setReady] = useState(false);
  const [failed, setFailed] = useState(false);

  // 1. Wait for the page to finish loading and go idle before touching the film.
  useEffect(() => {
    let idle = 0;
    let timer = 0;
    const go = () => {
      const ric = window.requestIdleCallback;
      if (ric) idle = ric(() => setArmed(true), { timeout: 2000 });
      else timer = window.setTimeout(() => setArmed(true), 200);
    };
    if (document.readyState === "complete") go();
    else window.addEventListener("load", go, { once: true });
    return () => {
      window.removeEventListener("load", go);
      if (idle) window.cancelIdleCallback?.(idle);
      if (timer) clearTimeout(timer);
    };
  }, []);

  // 2. Pick the film for this screen (none for reduced motion, or for phones
  //    without a portrait film); re-pick if the screen or setting changes.
  useEffect(() => {
    if (!armed) return;
    const still = window.matchMedia(queries.still);
    const phone = window.matchMedia(queries.phone);
    const pick = () => setFilm(still.matches ? null : phone.matches ? mobile : desktop);
    pick();
    still.addEventListener("change", pick);
    phone.addEventListener("change", pick);
    return () => {
      still.removeEventListener("change", pick);
      phone.removeEventListener("change", pick);
    };
  }, [armed, desktop, mobile, queries.still, queries.phone]);

  // Fetch the whole film into a blob before attaching it: every seek is then
  // served from memory, on any host (no HTTP Range support needed), so the
  // scrub stays smooth. The object URL is revoked when the film changes.
  const [blobUrl, setBlobUrl] = useState<string | null>(null);
  useEffect(() => {
    setReady(false);
    setFailed(false);
    setBlobUrl(null);
    if (!film) return;
    const ctrl = new AbortController();
    let url: string | null = null;
    fetch(film.src, { signal: ctrl.signal })
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.blob();
      })
      .then((b) => {
        url = URL.createObjectURL(b);
        setBlobUrl(url);
      })
      .catch(() => {
        if (!ctrl.signal.aborted) setFailed(true);
      });
    return () => {
      ctrl.abort();
      if (url) URL.revokeObjectURL(url);
    };
  }, [film]);

  // 3. Scroll → progress → eased film time, chapter and progress bar.
  useEffect(() => {
    const el = root.current;
    if (!el) return;
    let raf = 0;
    let shown = -1;
    let chapter = -1;

    const tick = () => {
      raf = 0;
      const r = el.getBoundingClientRect();
      const total = r.height - window.innerHeight;
      const p = total > 0 ? clamp(-r.top / total) : 0;

      // Ease toward the scroll position, a quarter of the way per frame.
      if (shown < 0) shown = p;
      shown += (p - shown) * 0.25;
      if (Math.abs(p - shown) < 0.0004) shown = p;
      el.style.setProperty("--p", shown.toFixed(4));

      const c = chapterAt(p);
      if (c !== chapter) el.dataset.chapter = String((chapter = c));

      const v = video.current;
      // iOS/Safari: only seek once a frame is decodable; skip while a seek is
      // in flight so seeks never queue up.
      if (v && v.readyState >= 2 && v.duration > 0 && !v.seeking) {
        const t = shown * Math.max(0, v.duration - 0.05);
        if (Math.abs(v.currentTime - t) > 0.02) v.currentTime = t;
      }

      if (shown !== p || (v && v.seeking)) raf = requestAnimationFrame(tick);
    };
    const kick = () => {
      if (!raf) raf = requestAnimationFrame(tick);
    };

    kick();
    window.addEventListener("scroll", kick, { passive: true });
    window.addEventListener("resize", kick);
    const v = video.current;
    v?.addEventListener("loadeddata", kick);
    v?.addEventListener("seeked", kick);
    return () => {
      window.removeEventListener("scroll", kick);
      window.removeEventListener("resize", kick);
      v?.removeEventListener("loadeddata", kick);
      v?.removeEventListener("seeked", kick);
      if (raf) cancelAnimationFrame(raf);
    };
  }, [blobUrl]);

  const onReady = () => {
    const v = video.current;
    if (!v) return;
    v.pause();
    setReady(true);
  };

  // The video's poster is the file the <picture> already picked (e.g. the 540px
  // phone poster), so it isn't downloaded a second time at another size.
  const start = (f: HeroFilm) => posterImg.current?.currentSrc || f.start.webp || f.start.jpg;
  const phoneMotion = `${queries.phone} and ${motionQ}`;
  const endPicture = (
    `<picture>` +
    (mobile ? `${mobile.end.webp ? `<source media="${queries.phone}" type="image/webp" srcset="${mobile.end.webp}">` : ""}<source media="${queries.phone}" srcset="${mobile.end.jpg}">` : "") +
    (desktop.end.webp ? `<source type="image/webp" srcset="${desktop.end.webp}">` : "") +
    `<img class="hero-x-bg hero-v-end" src="${desktop.end.jpg}" alt="" width="1600" height="900"></picture>`
  );
  // Without JS: static END poster, no pinning (mirrors the static rules in home.css).
  const noscript =
    `<style>.hero-v{height:auto}.hero-v-stage{position:relative;top:0;height:auto;min-height:max(560px,calc(100svh - var(--header-h)))}` +
    `.hero-v-bar{display:none}.hero-v .hero-v-chap>*{opacity:0}.hero-v .hero-v-chap>:last-child{opacity:1;transform:none}</style>` +
    endPicture;

  return (
    <section
      ref={root}
      className={["hero-x", "hero-v", mobile && "hero-v--mv", ready && "is-ready", failed && "is-failed"].filter(Boolean).join(" ")}
      aria-labelledby="hero-title"
      data-chapter="0"
    >
      <div className="hero-v-stage">
        <div className="hero-x-media">
          <picture>
            {mobile ? (
              <>
                <Sources poster={mobile.start} media={phoneMotion} />
                <Sources poster={mobile.end} media={queries.phone} />
                <Sources poster={desktop.end} media={queries.still} />
              </>
            ) : (
              <Sources poster={desktop.end} media={`${queries.phone}, ${queries.still}`} />
            )}
            <Sources poster={desktop.start} />
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              ref={posterImg}
              className="hero-x-bg hero-v-poster"
              src={desktop.start.jpg}
              alt={alt}
              width={1600}
              height={900}
              loading="eager"
              decoding="async"
              fetchPriority="high"
            />
          </picture>
          <noscript dangerouslySetInnerHTML={{ __html: noscript }} />
          {film && blobUrl && (
            <video
              key={blobUrl}
              ref={video}
              className="hero-x-bg hero-v-video"
              src={blobUrl}
              poster={start(film)}
              muted
              playsInline
              preload="auto"
              disablePictureInPicture
              disableRemotePlayback
              tabIndex={-1}
              aria-hidden
              onCanPlayThrough={onReady}
              onError={() => setFailed(true)}
            />
          )}
          {film && failed && (
            // eslint-disable-next-line @next/next/no-img-element
            <img className="hero-x-bg hero-v-end hero-v-endfade" src={film.end.webp ?? film.end.jpg} alt="" aria-hidden />
          )}
          <span className="hero-x-shade" aria-hidden />
        </div>

        <div className="wrap hero-x-inner">
          <div className="hero-x-copy">
            <div className="hero-v-head">
              <p className="hero-x-kicker">
                {/* Phones show only the first two parts so the kicker stays on one line. */}
                <span>
                  {kicker.split(" · ").map((part, i) => (
                    <span key={part} className={i > 1 ? "hero-v-kick-extra" : undefined}>
                      {i > 0 && " · "}
                      {part}
                    </span>
                  ))}
                </span>
              </p>
              {title}
              <p className="hero-v-chap" aria-hidden>
                {chapters.map((c) => (
                  <span key={c}>{c}</span>
                ))}
              </p>
            </div>
            {children}
          </div>
        </div>

        <span className="hero-v-bar" aria-hidden>
          <span />
        </span>
        <div className="hero-v-cue-wrap">
          <a className="hero-x-cue" href="#studio">
            <span className="sr-only">Scroll to the studio services</span>
            <span className="hero-x-cue-line" aria-hidden />
          </a>
        </div>
      </div>
    </section>
  );
}
