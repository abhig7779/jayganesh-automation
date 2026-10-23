import Image from "next/image";
import Link from "next/link";
import { categories, companyLinks, studioLinks } from "@/lib/catalog";
import { site } from "@/lib/site";

function IconInstagram() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
      <rect x="3" y="3" width="18" height="18" rx="5" />
      <circle cx="12" cy="12" r="4" />
      <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
    </svg>
  );
}
function IconYouTube() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12 31 31 0 0 0 1 16.8a3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.8 31 31 0 0 0-.5-4.8ZM9.7 15.1V8.9L15.5 12l-5.8 3.1Z" />
    </svg>
  );
}
function IconFacebook() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M13.5 22v-8.2h2.8l.4-3.2h-3.2V8.5c0-.9.3-1.6 1.6-1.6h1.7V4.1c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.4H7.3v3.2h2.8V22h3.4Z" />
    </svg>
  );
}

export default function Footer() {
  return (
    <footer className="footer">
      <div className="wrap">
        <div className="footer-grid">
          <div className="footer-brand">
            <Image src="/logo-176.webp" alt={site.name} width={64} height={44} />
            <p>Car accessories and a PPF &amp; detailing studio on the Pune–Solapur road, Loni Kalbhor.</p>
            <div className="footer-social">
              <a href={site.instagramHref} target="_blank" rel="noopener noreferrer" aria-label="Instagram"><IconInstagram /></a>
              <a href={site.youtubeHref} target="_blank" rel="noopener noreferrer" aria-label="YouTube"><IconYouTube /></a>
              <a href={site.facebookHref} target="_blank" rel="noopener noreferrer" aria-label="Facebook"><IconFacebook /></a>
            </div>
          </div>

          <div className="footer-cols">
            <div>
              <h2 className="label">Detailing studio</h2>
              <ul>{studioLinks.map((l) => <li key={l.href}><Link href={l.href}>{l.label}</Link></li>)}</ul>
            </div>
            <div>
              <h2 className="label">Accessories</h2>
              <ul>
                {categories.filter((c) => c.href).map((c) => <li key={c.key}><Link href={c.href!}>{c.name}</Link></li>)}
                <li><Link href="/accessories/">All accessories</Link></li>
              </ul>
            </div>
            <div>
              <h2 className="label">Shop</h2>
              <ul>
                <li><Link href="/our-work/">Our work</Link></li>
                <li><Link href="/offers/">Offers</Link></li>
                <li><Link href="/visit-us/">Visit us</Link></li>
                <li><Link href="/car-accessories-hadapsar/">Near Hadapsar</Link></li>
                <li><Link href="/car-accessories-kharadi/">Near Kharadi</Link></li>
                <li><Link href="/ppf-brands-pune/">PPF brands</Link></li>
                <li><Link href="/headlight-brands-pune/">Headlight brands</Link></li>
                <li><a href={site.reviewsHref} target="_blank" rel="noopener noreferrer">Google reviews</a></li>
              </ul>
            </div>
            <div>
              <h2 className="label">Company</h2>
              <ul>{companyLinks.map((l) => <li key={l.href}><Link href={l.href}>{l.label}</Link></li>)}</ul>
            </div>
            <div className="footer-visit">
              <h2 className="label">Visit</h2>
              <address>
                <a href={site.mapsHref} target="_blank" rel="noopener noreferrer">{site.address}</a>
                <span>{site.hours}</span>
                <a className="footer-phone" href={site.phoneHref}>{site.phoneDisplay}</a>
              </address>
            </div>
          </div>
        </div>

        <div className="footer-base">
          <span>© {new Date().getFullYear()} {site.name}</span>
          <span className="footer-legal">
            <Link href="/privacy-policy/">Privacy policy</Link>
            <Link href="/terms/">Terms &amp; conditions</Link>
          </span>
        </div>
      </div>
    </footer>
  );
}
