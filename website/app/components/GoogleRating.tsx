import "../styles/reviews.css";
import { site } from "@/lib/site";
import { countText, ratingText } from "@/lib/reviews";

const gPaths = (
  <>
    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
  </>
);

/** Google "G" logo, official four colours. Decorative. */
export function GoogleG({ size = 18, className }: { size?: number; className?: string }) {
  return (
    <svg className={className} width={size} height={size} viewBox="0 0 48 48" aria-hidden="true" focusable="false">
      {gPaths}
    </svg>
  );
}

/** Defines the "G" once per page; GoogleGUse draws it in 2 nodes instead of 5. */
export function GoogleGSymbol() {
  return (
    <svg width="0" height="0" style={{ position: "absolute" }} aria-hidden="true" focusable="false">
      <symbol id="rv-g" viewBox="0 0 48 48">{gPaths}</symbol>
    </svg>
  );
}
export function GoogleGUse({ size = 18, className }: { size?: number; className?: string }) {
  return (
    <svg className={className} width={size} height={size} aria-hidden="true" focusable="false">
      <use href="#rv-g" />
    </svg>
  );
}

/** One star (20×20 units) drawn `x` units from the left. */
const starPath = (x: number) =>
  `M${x + 10} 1.6l2.6 5.3 5.8.8-4.2 4.1 1 5.8L${x + 10} 14.9l-5.2 2.7 1-5.8L${x + 1.6} 7.7l5.8-.8L${x + 10} 1.6z`;

/**
 * Five stars in brand yellow, as one SVG with two paths (lit and unlit) rather
 * than five SVGs: the reviews carousel shows 30 of these, so it keeps the DOM
 * small. Decorative; pair with readable text.
 */
export function Stars({ count = 5, size = 16, className }: { count?: number; size?: number; className?: string }) {
  const step = 20 + (2 * 20) / size; // a 2px gap between stars, in SVG units
  const width = 4 * step + 20;
  const on = Array.from({ length: count }, (_, i) => starPath(i * step)).join("");
  const off = Array.from({ length: 5 - count }, (_, i) => starPath((count + i) * step)).join("");
  return (
    <span className={`rv-stars${className ? ` ${className}` : ""}`} aria-hidden="true">
      <svg width={(size * width) / 20} height={size} viewBox={`0 0 ${width} 20`} focusable="false">
        {on && <path className="is-on" d={on} />}
        {off && <path className="is-off" d={off} />}
      </svg>
    </span>
  );
}

type Variant = "hero" | "inline" | "compact";

/** Google rating badge: G logo, stars, live rating and exact review count, linking to Google. */
export default function GoogleRating({ variant = "inline" }: { variant?: Variant }) {
  // No aria-label: the link is named by its visible text plus screen-reader-only
  // words, so the spoken name contains what is on screen (WCAG 2.5.3):
  // "4.9 out of 5 stars, 727 Google reviews (opens Google Maps)".
  const outOf = <span className="rv-sr"> out of 5 stars, </span>;
  const opens = <span className="rv-sr"> (opens Google Maps)</span>;
  return (
    <a className={`rv-badge rv-badge--${variant}`} href={site.reviewsHref} target="_blank" rel="noopener noreferrer">
      <GoogleG size={variant === "compact" ? 14 : variant === "hero" ? 22 : 28} className="rv-badge__g" />
      {variant === "compact" ? (
        <span className="rv-badge__line">
          <strong>{ratingText}</strong>
          {outOf}
          <Stars size={12} />
          <span className="rv-badge__count">{countText} Google reviews</span>
          {opens}
        </span>
      ) : (
        <span className="rv-badge__body">
          <span className="rv-badge__top">
            <strong className="rv-badge__score">{ratingText}</strong>
            {outOf}
            <Stars size={variant === "hero" ? 15 : 17} />
          </span>
          <span className="rv-badge__count">{countText} Google reviews</span>
          {opens}
        </span>
      )}
    </a>
  );
}
