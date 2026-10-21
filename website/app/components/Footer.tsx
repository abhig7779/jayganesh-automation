import Image from "next/image";
import Link from "next/link";
import { categories, studioLinks } from "@/lib/catalog";
import { site } from "@/lib/site";

export default function Footer() {
  return (
    <footer className="footer">
      <div className="wrap">
        <div className="footer-grid">
          <div className="footer-brand">
            <Image src="/logo.png" alt={site.name} width={81} height={56} />
            <p style={{ color: "var(--ink-muted)" }}>
              Car accessories and a PPF &amp; detailing studio on the Pune–Solapur road, Loni Kalbhor.
            </p>
            <address style={{ fontStyle: "normal" }} className="stack">
              <span>{site.address}</span>
              <a href={site.phoneHref}>{site.phoneDisplay}</a>
              <span className="label">{site.hours}</span>
            </address>
          </div>

          <div className="footer-cols">
            <div>
              <h4 className="label label--yellow">Detailing studio</h4>
              <ul>{studioLinks.map((l) => <li key={l.href}><Link href={l.href}>{l.label}</Link></li>)}</ul>
            </div>
            <div>
              <h4 className="label label--yellow">Accessories</h4>
              <ul>
                {categories.slice(0, 6).map((c) => <li key={c.key}><Link href={c.href ?? `/accessories/#${c.key}`}>{c.name}</Link></li>)}
                <li><Link href="/accessories/">All accessories</Link></li>
              </ul>
            </div>
            <div>
              <h4 className="label label--yellow">Shop</h4>
              <ul>
                <li><Link href="/our-work/">Our work</Link></li>
                <li><Link href="/offers/">Offers</Link></li>
                <li><Link href="/visit-us/">Visit us</Link></li>
                <li><a href={site.reviewsHref} target="_blank" rel="noopener noreferrer">Google reviews</a></li>
              </ul>
            </div>
            <div>
              <h4 className="label label--yellow">Follow</h4>
              <ul>
                <li><a href={site.instagramHref} target="_blank" rel="noopener noreferrer">Instagram</a></li>
                <li><a href={site.youtubeHref} target="_blank" rel="noopener noreferrer">YouTube</a></li>
                <li><a href={site.facebookHref} target="_blank" rel="noopener noreferrer">Facebook</a></li>
              </ul>
            </div>
          </div>
        </div>

        <div className="footer-base label">
          <span>© {new Date().getFullYear()} {site.name}</span>
          <span style={{ display: "flex", gap: 20 }}>
            <Link href="/privacy-policy/">Privacy policy</Link>
            <Link href="/terms/">Terms &amp; conditions</Link>
          </span>
        </div>
      </div>
    </footer>
  );
}
