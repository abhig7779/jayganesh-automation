import Link from "next/link";
import { site, whatsapp } from "@/lib/site";
import { formatPrice, prices, type PriceKey } from "@/lib/prices";
import { imageUrl, media, srcSet, type Media, type MediaKey } from "@/lib/media";

/* ---------- Icons (inline, so nothing extra loads) ---------- */

// The official WhatsApp glyph.
export function IconWhatsApp() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z" />
    </svg>
  );
}
export function IconPhone() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z" />
    </svg>
  );
}
export function IconPin() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z" />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}
export function IconArrow() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M5 12h14M13 6l6 6-6 6" />
    </svg>
  );
}
export function IconChevron({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="1.6" aria-hidden="true">
      <path d="M2 4l4 4 4-4" />
    </svg>
  );
}

/* ---------- Buttons ---------- */

export function WhatsAppButton({
  message,
  children = "WhatsApp for a price",
  variant = "primary",
  size,
  block,
}: {
  message?: string;
  children?: React.ReactNode;
  variant?: "primary" | "ghost";
  size?: "sm";
  block?: boolean;
}) {
  const cls = ["btn", `btn--${variant}`, size && `btn--${size}`, block && "btn--block"].filter(Boolean).join(" ");
  return (
    <a className={cls} href={whatsapp(message)} target="_blank" rel="noopener noreferrer" data-track="whatsapp">
      <IconWhatsApp />
      {children}
    </a>
  );
}

export function CallButton({ variant = "ghost", size }: { variant?: "primary" | "ghost"; size?: "sm" }) {
  return (
    <a className={["btn", `btn--${variant}`, size && `btn--${size}`, "btn--call"].filter(Boolean).join(" ")} href={site.phoneHref} data-track="call">
      <IconPhone />
      Call
    </a>
  );
}

/* ---------- Price ---------- */

export function Price({ k, from = true }: { k?: PriceKey; from?: boolean }) {
  const value = k ? formatPrice(prices[k]) : null;
  if (!value) return <span className="price"><span className="price-ask">Price on WhatsApp</span></span>;
  return (
    <span className="price">
      {from && <span className="price-from">From</span>}
      <span className="price-value">{value}</span>
    </span>
  );
}

/* ---------- Photo ---------- */

export function Photo({
  label,
  m,
  src,
  alt,
  ratio = "4 / 3",
  className,
  sizes = "(min-width: 1024px) 33vw, 100vw",
  eager,
}: {
  label: string;
  m?: MediaKey;
  src?: string;
  alt?: string;
  ratio?: string;
  className?: string;
  sizes?: string;
  eager?: boolean;
}) {
  const item: Media | undefined = m ? media[m] : undefined;
  const url = src ?? item?.src;
  return (
    <figure className={["photo", className].filter(Boolean).join(" ")} style={{ ["--ratio" as string]: ratio }}>
      {url ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={imageUrl(url, 1200)}
          srcSet={srcSet(url)}
          sizes={sizes}
          alt={alt ?? item?.alt ?? label}
          loading={eager ? "eager" : "lazy"}
          fetchPriority={eager ? "high" : undefined}
          decoding="async"
          style={item?.position ? { objectPosition: item.position } : undefined}
        />
      ) : (
        <figcaption><b>Photo</b> · {label}</figcaption>
      )}
    </figure>
  );
}

/* ---------- Section heading ---------- */

export function SectionHead({ eyebrow, title, lead, as = "h2" }: { eyebrow?: string; title: string; lead?: string; as?: "h1" | "h2" }) {
  const H = as;
  return (
    <div className="section-head">
      {eyebrow && <p className="label label--yellow">{eyebrow}</p>}
      <H>{title}</H>
      {lead && <p className="lead">{lead}</p>}
    </div>
  );
}

/* ---------- FAQ ---------- */

export function Faq({ items }: { items: { q: string; a: string }[] }) {
  return (
    <div className="faq">
      {items.map((f) => (
        <details key={f.q}>
          <summary>{f.q}</summary>
          <p>{f.a}</p>
        </details>
      ))}
    </div>
  );
}

/* ---------- Breadcrumbs ---------- */

export function Crumbs({ items }: { items: { label: string; href?: string }[] }) {
  return (
    <nav aria-label="Breadcrumb">
      <ol className="crumbs label">
        <li><Link href="/">Home</Link></li>
        {items.map((c) => (
          <li key={c.label}>{c.href ? <Link href={c.href}>{c.label}</Link> : <span aria-current="page">{c.label}</span>}</li>
        ))}
      </ol>
    </nav>
  );
}

/* ---------- Trust strip ---------- */

export function Trust() {
  return (
    <div className="trust-band">
      <div className="wrap">
        <div className="trust">
          <div><strong>{site.rating}<span className="trust-star">★</span></strong><span className="label">{site.reviews} Google reviews</span></div>
          <div><strong>{site.customers}</strong><span className="label">Customers served</span></div>
          <div><strong>7 days</strong><span className="label">Open 10 am – 8 pm</span></div>
          <div><strong>{site.instagramFollowers}</strong><span className="label">On Instagram</span></div>
        </div>
      </div>
    </div>
  );
}

/* ---------- CTA band ---------- */

export function CtaBand({
  title = "Send us your car model. We'll reply with a price.",
  message,
}: {
  title?: string;
  message?: string;
}) {
  return (
    <section className="cta" aria-label="Get a price">
      <div className="wrap">
        <div className="stack">
          <p className="label">WhatsApp · {site.phoneDisplay}</p>
          <h2>{title}</h2>
        </div>
        <div className="actions">
          <WhatsAppButton message={message}>WhatsApp us</WhatsAppButton>
          <a className="btn btn--ghost" href={site.phoneHref}><IconPhone />Call the shop</a>
        </div>
      </div>
    </section>
  );
}

/* ---------- Visit strip ---------- */

export function Visit() {
  return (
    <div className="visit">
      <div>
        <p className="label label--yellow">Visit the shop</p>
        <p className="big">{site.addressShort}</p>
        <p className="label">On the Pune–Solapur road, at the toll naka</p>
      </div>
      <div>
        <p className="label">Hours</p>
        <p className="big">10 am – 8 pm</p>
        <p className="label">Every day</p>
      </div>
      <div>
        <p className="label">Directions</p>
        <a className="btn btn--ghost btn--sm" href={site.mapsHref} target="_blank" rel="noopener noreferrer" data-track="directions">
          <IconPin />Open in Google Maps
        </a>
      </div>
    </div>
  );
}

/* ---------- Structured data ---------- */

export function JsonLd({ data }: { data: object }) {
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }} />;
}
